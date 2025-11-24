<?php

declare(strict_types=1);

namespace Mhrlife\PhpaiKit\Callbacks;

use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\SDK\Trace\TracerProviderInterface;
use OpenTelemetry\Context\Context;
use OpenTelemetry\API\Trace\Span;

class LangfuseCallback implements AgentCallback
{
    private mixed $traceSpan = null;
    private mixed $rootSpan = null;
    private mixed $currentGenerationSpan = null;
    /** @var array<string, mixed> */
    private array $toolSpans = [];

    // Context management - mimicking Python's approach
    private ?Context $traceContext = null;
    private mixed $traceContextToken = null;
    private ?Context $rootSpanContext = null;
    private mixed $rootContextToken = null;

    public function __construct(
        private readonly TracerProviderInterface $tracerProvider,
        private readonly string $serviceName = 'phpai-kit',
        ?string $traceId = null,
        ?Context $parentContext = null
    ) {
        // Create trace span
        $this->initializeTrace($traceId, $parentContext);
    }

    /**
     * Initialize trace span for this execution
     * Mimics Python's context attachment pattern
     */
    private function initializeTrace(?string $traceId, ?Context $parentContext): void
    {
        $tracer = $this->tracerProvider->getTracer($this->serviceName, '1.0.0');

        $spanBuilder = $tracer->spanBuilder('trace')
            ->setSpanKind(SpanKind::KIND_INTERNAL);

        // If parent context exists, use it
        if ($parentContext !== null) {
            $spanBuilder->setParent($parentContext);
        }

        $this->traceSpan = $spanBuilder->startSpan();

        // Store trace context and attach it (like Python's attach())
        $this->traceContext = $this->traceSpan->storeInContext(
            $parentContext ?? Context::getCurrent()
        );

        // Attach the context to make it current using ContextStorage
        $this->traceContextToken = Context::storage()->attach($this->traceContext);

        // Store trace ID if provided
        if ($traceId !== null) {
            $this->traceSpan->setAttribute('trace_id', $traceId);
        }
    }

    public function onRunStart(array $context): void
    {
        $tracer = $this->tracerProvider->getTracer($this->serviceName, '1.0.0');

        // Start root span - it will automatically use current context (trace context)
        $this->rootSpan = $tracer->spanBuilder('agent.run')
            ->setSpanKind(SpanKind::KIND_INTERNAL)
            ->startSpan();

        // Store root span context and attach it (like Python's _attach_observation)
        $this->rootSpanContext = $this->rootSpan->storeInContext(Context::getCurrent());
        $this->rootContextToken = Context::storage()->attach($this->rootSpanContext);

        $this->rootSpan->setAttribute('langfuse.observation.model.name', $context['model']);
        $this->rootSpan->setAttribute('langfuse.observation.input', json_encode($context['input']));

        if ($context['has_output_class']) {
            $this->rootSpan->setAttribute('has_structured_output', true);
        }
    }

    public function onRunEnd(array $context): void
    {
        if ($this->rootSpan === null) {
            return;
        }

        $this->rootSpan->setAttribute('langfuse.observation.output', json_encode($context['output']));
        $this->rootSpan->setAttribute('total_iterations', $context['total_iterations']);
        $this->rootSpan->setStatus(StatusCode::STATUS_OK);
        $this->rootSpan->end();

        // Detach root context (like Python's _detach_observation)
        if ($this->rootContextToken !== null) {
            $this->rootContextToken->detach();
            $this->rootContextToken = null;
        }

        // End trace span if it exists
        if ($this->traceSpan !== null) {
            $this->traceSpan->setStatus(StatusCode::STATUS_OK);
            $this->traceSpan->end();

            // Detach trace context
            if ($this->traceContextToken !== null) {
                $this->traceContextToken->detach();
                $this->traceContextToken = null;
            }
        }
    }

    public function onGenerationStart(array $context): void
    {
        if ($this->rootSpan === null) {
            return;
        }

        $tracer = $this->tracerProvider->getTracer($this->serviceName, '1.0.0');

        // Start generation span - will automatically use current context (root span context)
        $this->currentGenerationSpan = $tracer->spanBuilder('llm.generation')
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->startSpan();

        $this->currentGenerationSpan->setAttribute('langfuse.observation.model.name', $context['model']);
        $this->currentGenerationSpan->setAttribute('gen_ai.request.model', $context['model']);
        $this->currentGenerationSpan->setAttribute('iteration', $context['iteration']);
        $this->currentGenerationSpan->setAttribute('langfuse.observation.input', json_encode($context['messages']));
    }

    public function onGenerationEnd(array $context): void
    {
        if ($this->currentGenerationSpan === null) {
            return;
        }

        $this->currentGenerationSpan->setAttribute('finish_reason', $context['finish_reason']);

        // Build complete output including tool calls if present
        $output = [];

        if (!empty($context['content'])) {
            $output['content'] = $context['content'];
        }

        // Add tool calls to output if present
        if (!empty($context['tool_calls'])) {
            $output['tool_calls'] = array_map(function ($toolCall) {
                return [
                    'id' => $toolCall->id,
                    'type' => $toolCall->type,
                    'function' => [
                        'name' => $toolCall->function->name,
                        'arguments' => $toolCall->function->arguments,
                    ],
                ];
            }, $context['tool_calls']);

            $this->currentGenerationSpan->setAttribute('has_tool_calls', true);
            $this->currentGenerationSpan->setAttribute('tool_calls_count', count($context['tool_calls']));
        }

        // Set output with both content and tool calls
        $this->currentGenerationSpan->setAttribute(
            'langfuse.observation.output',
            json_encode($output)
        );

        // Add usage information if available
        if (isset($context['usage'])) {
            $usage = $context['usage'];
            $usageDetails = [
                'prompt_tokens' => $usage->promptTokens ?? 0,
                'completion_tokens' => $usage->completionTokens ?? 0,
                'total_tokens' => $usage->totalTokens ?? 0,
            ];
            $this->currentGenerationSpan->setAttribute('langfuse.observation.usage_details', json_encode($usageDetails));
        }

        $this->currentGenerationSpan->setStatus(StatusCode::STATUS_OK);
        $this->currentGenerationSpan->end();
        $this->currentGenerationSpan = null;
    }

    public function onToolCallStart(array $context): void
    {
        if ($this->rootSpan === null) {
            return;
        }

        $tracer = $this->tracerProvider->getTracer($this->serviceName, '1.0.0');

        // Start tool span - will automatically use current context (root span context)
        $toolSpan = $tracer->spanBuilder("tool.{$context['tool_name']}")
            ->setSpanKind(SpanKind::KIND_INTERNAL)
            ->startSpan();

        $toolSpan->setAttribute('tool.name', $context['tool_name']);
        $toolSpan->setAttribute('langfuse.observation.input', json_encode($context['arguments']));
        $toolSpan->setAttribute('tool_call_id', $context['tool_call_id']);

        $this->toolSpans[$context['tool_call_id']] = $toolSpan;
    }

    public function onToolCallEnd(array $context): void
    {
        $toolCallId = $context['tool_call_id'];

        if (!isset($this->toolSpans[$toolCallId])) {
            return;
        }

        $toolSpan = $this->toolSpans[$toolCallId];

        $toolSpan->setAttribute('langfuse.observation.output', json_encode($context['result']));
        $toolSpan->setStatus(StatusCode::STATUS_OK);
        $toolSpan->end();

        unset($this->toolSpans[$toolCallId]);
    }

    public function onError(array $context): void
    {
        // End all open spans with error status
        if ($this->currentGenerationSpan !== null) {
            $this->currentGenerationSpan->recordException($context['exception']);
            $this->currentGenerationSpan->setStatus(StatusCode::STATUS_ERROR, $context['error']);
            $this->currentGenerationSpan->end();
            $this->currentGenerationSpan = null;
        }

        foreach ($this->toolSpans as $toolSpan) {
            $toolSpan->recordException($context['exception']);
            $toolSpan->setStatus(StatusCode::STATUS_ERROR, $context['error']);
            $toolSpan->end();
        }
        $this->toolSpans = [];

        if ($this->rootSpan !== null) {
            $this->rootSpan->recordException($context['exception']);
            $this->rootSpan->setStatus(StatusCode::STATUS_ERROR, $context['error']);
            $this->rootSpan->end();

            // Detach root context on error
            if ($this->rootContextToken !== null) {
                $this->rootContextToken->detach();
                $this->rootContextToken = null;
            }

            $this->rootSpan = null;
        }

        // End trace span if it exists
        if ($this->traceSpan !== null) {
            $this->traceSpan->recordException($context['exception']);
            $this->traceSpan->setStatus(StatusCode::STATUS_ERROR, $context['error']);
            $this->traceSpan->end();

            // Detach trace context on error
            if ($this->traceContextToken !== null) {
                $this->traceContextToken->detach();
                $this->traceContextToken = null;
            }

            $this->traceSpan = null;
        }
    }

    /**
     * Get the current trace context for creating child callbacks
     */
    public function getTraceContext(): ?Context
    {
        return $this->traceContext;
    }
}
