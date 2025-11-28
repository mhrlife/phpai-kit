<?php

declare(strict_types=1);

namespace Mhrlife\PhpaiKit\Callbacks;

use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\SDK\Trace\TracerProviderInterface;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\Context\ScopeInterface;

/**
 * Manages OpenTelemetry spans with proper hierarchy and context handling.
 * Uses activate() to properly establish parent-child relationships.
 */
class SpanManager
{
    /** @var array<array{span: Span, scope: ScopeInterface}> */
    private array $spanStack = [];

    public function __construct(
        private readonly TracerProviderInterface $tracerProvider,
        private readonly string $serviceName = 'phpai-kit'
    ) {
    }

    /**
     * Start a new span - automatically becomes child of current span if one exists
     */
    public function startSpan(string $name, int $kind = SpanKind::KIND_INTERNAL): Span
    {
        $tracer = $this->tracerProvider->getTracer($this->serviceName, '1.0.0');

        // Create span - it will automatically use current context (which has current span)
        $span = $tracer->spanBuilder($name)
            ->setSpanKind($kind)
            ->startSpan();

        // Activate the span to make it current - this is the KEY!
        $scope = $span->activate();

        // Store both span and scope
        $this->spanStack[] = [
            'span' => $span,
            'scope' => $scope,
        ];

        return $span;
    }

    /**
     * Get the current (top) span
     */
    public function getCurrentSpan(): ?Span
    {
        if (empty($this->spanStack)) {
            return null;
        }

        return $this->spanStack[array_key_last($this->spanStack)]['span'];
    }

    /**
     * End the current span and pop it from the stack
     */
    public function endCurrentSpan(string $statusCode = StatusCode::STATUS_OK, ?string $description = null): void
    {
        if (empty($this->spanStack)) {
            return;
        }

        $current = array_pop($this->spanStack);
        $current['span']->setStatus($statusCode, $description);
        $current['span']->end();
        $current['scope']->detach();
    }

    /**
     * Update metadata on the current span
     */
    public function updateMetadata(string $key, mixed $value): void
    {
        $span = $this->getCurrentSpan();
        if ($span !== null) {
            $span->setAttribute($key, $value);
        }
    }

    /**
     * Update multiple metadata values at once
     */
    public function updateMetadataBatch(array $attributes): void
    {
        $span = $this->getCurrentSpan();
        if ($span !== null) {
            foreach ($attributes as $key => $value) {
                $span->setAttribute($key, $value);
            }
        }
    }

    /**
     * Record an exception on the current span
     */
    public function recordException(\Throwable $exception): void
    {
        $span = $this->getCurrentSpan();
        if ($span !== null) {
            $span->recordException($exception);
        }
    }

    /**
     * Get the span stack depth (for debugging/testing)
     */
    public function getDepth(): int
    {
        return count($this->spanStack);
    }

    /**
     * Clean up all remaining spans (on error or shutdown)
     */
    public function cleanupAll(): void
    {
        while (!empty($this->spanStack)) {
            $current = array_pop($this->spanStack);
            $current['span']->setStatus(StatusCode::STATUS_UNSET);
            $current['span']->end();
            $current['scope']->detach();
        }
    }
}
