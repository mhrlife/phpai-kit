<?php

declare(strict_types=1);

namespace Mhrlife\PhpaiKit\Callbacks;

use OpenTelemetry\Context\Context;

/**
 * Create a new LangfuseCallback for a run
 * Each call creates a fresh callback with its own trace
 *
 * @param string|null $publicKey Langfuse public key (or uses env)
 * @param string|null $secretKey Langfuse secret key (or uses env)
 * @param string $serviceName Service name for traces
 * @param string $endpoint Langfuse endpoint URL
 * @param string|null $traceId Optional trace ID to group related runs
 * @param Context|null $parentContext Optional parent context for nested spans
 * @return LangfuseCallback
 */
function create_langfuse_callback(
    ?string $publicKey = null,
    ?string $secretKey = null,
    string $serviceName = 'phpai-kit',
    string $endpoint = 'https://cloud.langfuse.com/api/public/otel/v1/traces',
    ?string $traceId = null,
    ?Context $parentContext = null
): LangfuseCallback {
    // Get or create tracer provider
    $state = LangfuseGlobalState::getInstance();

    if (!$state->isInitialized()) {
        $state->initialize($publicKey, $secretKey, $serviceName, $endpoint);
    }

    $tracerProvider = $state->getTracerProvider();

    if ($tracerProvider === null) {
        throw new \RuntimeException('Failed to get TracerProvider');
    }

    return new LangfuseCallback($tracerProvider, $serviceName, $traceId, $parentContext);
}

/**
 * Initialize Langfuse global state with credentials
 * This sets up the tracer provider but doesn't create a callback
 *
 * @param string|null $publicKey Langfuse public key (or uses env)
 * @param string|null $secretKey Langfuse secret key (or uses env)
 * @param string $serviceName Service name for traces
 * @param string $endpoint Langfuse endpoint URL
 * @return void
 */
function initialize_langfuse(
    ?string $publicKey = null,
    ?string $secretKey = null,
    string $serviceName = 'phpai-kit',
    string $endpoint = 'https://cloud.langfuse.com/api/public/otel/v1/traces'
): void {
    LangfuseGlobalState::getInstance()->initialize($publicKey, $secretKey, $serviceName, $endpoint);
}

/**
 * Create a child LangfuseCallback that continues from a parent callback's trace
 * Use this when you need nested agent runs under the same trace
 *
 * @param LangfuseCallback $parentCallback The parent callback to inherit trace from
 * @param string|null $publicKey Langfuse public key (or uses env)
 * @param string|null $secretKey Langfuse secret key (or uses env)
 * @param string $serviceName Service name for traces
 * @return LangfuseCallback
 */
function create_child_langfuse_callback(
    LangfuseCallback $parentCallback,
    ?string $publicKey = null,
    ?string $secretKey = null,
    string $serviceName = 'phpai-kit'
): LangfuseCallback {
    $parentContext = $parentCallback->getTraceContext();

    return create_langfuse_callback(
        $publicKey,
        $secretKey,
        $serviceName,
        'https://cloud.langfuse.com/api/public/otel/v1/traces',
        null,
        $parentContext
    );
}
