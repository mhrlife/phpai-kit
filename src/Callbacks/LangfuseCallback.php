<?php

declare(strict_types=1);

namespace Mhrlife\PhpaiKit\Callbacks;

use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\SDK\Trace\TracerProviderInterface;

class LangfuseCallback implements AgentCallback
{
    private SpanManager $spanManager;
    /** @var array<string, string> */
    private array $toolSpanIds = [];

    public function __construct(
        private readonly TracerProviderInterface $tracerProvider,
        private readonly string $serviceName = 'phpai-kit',
        ?string $traceId = null,
    ) {
        $this->spanManager = new SpanManager($tracerProvider, $serviceName);

        // Initialize with trace span
        $this->spanManager->startSpan('trace', SpanKind::KIND_INTERNAL);
        if ($traceId !== null) {
            $this->spanManager->updateMetadata('trace_id', $traceId);
        }
    }

    public function onRunStart(array $context): void
    {
        $this->spanManager->startSpan('agent.run', SpanKind::KIND_INTERNAL);

        $this->spanManager->updateMetadataBatch([
            'langfuse.observation.model.name' => $context['model'],
            'langfuse.observation.input' => json_encode($context['input']),
            'has_structured_output' => $context['has_output_class'] ?? false,
        ]);
    }

    public function onRunEnd(array $context): void
    {
        $this->spanManager->updateMetadataBatch([
            'langfuse.observation.output' => json_encode($context['output']),
            'total_iterations' => $context['total_iterations'],
        ]);

        // End iteration span if still open
        if ($this->spanManager->getDepth() > 2) {
            $this->spanManager->endCurrentSpan(StatusCode::STATUS_OK);
        }

        // End agent.run span
        $this->spanManager->endCurrentSpan(StatusCode::STATUS_OK);

        // End trace span
        $this->spanManager->endCurrentSpan(StatusCode::STATUS_OK);
    }

    public function onGenerationStart(array $context): void
    {
        // End previous iteration span if it exists
        if ($this->spanManager->getDepth() > 2) {
            $this->spanManager->endCurrentSpan(StatusCode::STATUS_OK);
        }

        // Start new iteration span (wraps both generation and tool calls)
        $iteration = $context['iteration'];
        $this->spanManager->startSpan("iteration.$iteration", SpanKind::KIND_INTERNAL);

        // Start generation span (sibling to tool calls, both under iteration)
        $this->spanManager->startSpan('llm.generation', SpanKind::KIND_CLIENT);

        $this->spanManager->updateMetadataBatch([
            'langfuse.observation.model.name' => $context['model'],
            'gen_ai.request.model' => $context['model'],
            'iteration' => $iteration,
            'langfuse.observation.input' => json_encode($context['messages']),
        ]);
    }

    public function onGenerationEnd(array $context): void
    {
        $output = [
            'role' => 'assistant',
            'content' => $context['content'],
            'tool_calls' => $context['tool_calls'] ?? [],
        ];

        $this->spanManager->updateMetadataBatch([
            'finish_reason' => $context['finish_reason'],
            'has_tool_calls' => !empty($context['tool_calls']),
            'tool_calls_count' => count($context['tool_calls'] ?? []),
            'langfuse.observation.output' => json_encode($output),
        ]);

        // Add usage information if available
        if (isset($context['usage'])) {
            $usage = $context['usage'];
            $usageDetails = [
                'prompt_tokens' => $usage->promptTokens ?? 0,
                'completion_tokens' => $usage->completionTokens ?? 0,
                'total_tokens' => $usage->totalTokens ?? 0,
            ];
            $this->spanManager->updateMetadata(
                'langfuse.observation.usage_details',
                json_encode($usageDetails)
            );
        }

        // End generation span (tool calls are siblings, not children)
        $this->spanManager->endCurrentSpan(StatusCode::STATUS_OK);
    }

    public function onToolCallStart(array $context): void
    {
        $toolCallId = $context['tool_call_id'];
        $toolName = $context['tool_name'];

        // Start tool span as sibling to generation (both under iteration)
        $this->spanManager->startSpan("tool.{$toolName}", SpanKind::KIND_INTERNAL);

        $this->spanManager->updateMetadataBatch([
            'tool.name' => $toolName,
            'langfuse.observation.input' => json_encode($context['arguments']),
            'tool_call_id' => $toolCallId,
        ]);

        // Track which span corresponds to this tool call
        $this->toolSpanIds[$toolCallId] = $toolCallId;
    }

    public function onToolCallEnd(array $context): void
    {
        $toolCallId = $context['tool_call_id'];

        if (!isset($this->toolSpanIds[$toolCallId])) {
            return;
        }

        $this->spanManager->updateMetadata(
            'langfuse.observation.output',
            json_encode($context['result'])
        );

        $this->spanManager->endCurrentSpan(StatusCode::STATUS_OK);
        unset($this->toolSpanIds[$toolCallId]);
    }

    public function onError(array $context): void
    {
        $this->spanManager->recordException($context['exception']);
        $this->spanManager->endCurrentSpan(
            StatusCode::STATUS_ERROR,
            $context['error']
        );

        // Clean up any remaining spans
        while ($this->spanManager->getDepth() > 0) {
            $this->spanManager->recordException($context['exception']);
            $this->spanManager->endCurrentSpan(
                StatusCode::STATUS_ERROR,
                $context['error']
            );
        }
    }

    /**
     * Cleanup on destruction to prevent scope leaks
     * This ensures all OpenTelemetry scopes are properly detached even if
     * the callback is destroyed before onRunEnd/onError is called.
     */
    public function __destruct()
    {
        if (isset($this->spanManager) && $this->spanManager->getDepth() > 0) {
            try {
                $this->spanManager->cleanupAll();
            } catch (\Throwable $e) {
            }
        }
    }
}
