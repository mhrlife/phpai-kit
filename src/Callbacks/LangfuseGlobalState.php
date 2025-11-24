<?php

declare(strict_types=1);

namespace Mhrlife\PhpaiKit\Callbacks;

use OpenTelemetry\SDK\Trace\TracerProviderInterface;

/**
 * Global state manager for Langfuse tracing
 * Provides singleton-like access to LangfuseCallback and trace context
 */
class LangfuseGlobalState
{
    private static ?self $instance = null;
    private ?LangfuseCallback $callback = null;
    private ?TracerProviderInterface $tracerProvider = null;
    private ?string $currentTraceId = null;
    private ?string $currentParentObservationId = null;
    private string $serviceName = 'phpai-kit';

    private function __construct()
    {
    }

    /**
     * Get the singleton instance
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Initialize Langfuse with credentials
     *
     * @param string|null $publicKey Langfuse public key (or uses env)
     * @param string|null $secretKey Langfuse secret key (or uses env)
     * @param string $serviceName Service name for traces
     * @param string $endpoint Langfuse endpoint URL
     * @return self
     */
    public function initialize(
        ?string $publicKey = null,
        ?string $secretKey = null,
        string $serviceName = 'phpai-kit',
        string $endpoint = 'https://cloud.langfuse.com/api/public/otel/v1/traces'
    ): self {
        $this->serviceName = $serviceName;
        $this->tracerProvider = LangfuseHelper::createTracerProvider($publicKey, $secretKey, $endpoint);

        // Register shutdown function to flush traces
        register_shutdown_function(function () {
            if ($this->tracerProvider !== null) {
                $this->tracerProvider->shutdown();
            }
        });

        $this->callback = new LangfuseCallback($this->tracerProvider, $this->serviceName);

        return $this;
    }

    /**
     * Check if Langfuse has been initialized
     */
    public function isInitialized(): bool
    {
        return $this->callback !== null;
    }

    /**
     * Get the current LangfuseCallback instance
     * Automatically initializes if not already done
     *
     * @throws \RuntimeException if not initialized and auto-init fails
     */
    public function getCallback(): LangfuseCallback
    {
        if ($this->callback === null) {
            // Auto-initialize with environment variables
            $this->initialize();
        }

        return $this->callback;
    }

    /**
     * Set a custom LangfuseCallback instance
     */
    public function setCallback(LangfuseCallback $callback): self
    {
        $this->callback = $callback;

        return $this;
    }

    /**
     * Get current trace ID
     */
    public function getCurrentTraceId(): ?string
    {
        return $this->currentTraceId;
    }

    /**
     * Set current trace ID
     */
    public function setCurrentTraceId(?string $traceId): self
    {
        $this->currentTraceId = $traceId;

        return $this;
    }

    /**
     * Get current parent observation ID
     */
    public function getCurrentParentObservationId(): ?string
    {
        return $this->currentParentObservationId;
    }

    /**
     * Set current parent observation ID
     */
    public function setCurrentParentObservationId(?string $observationId): self
    {
        $this->currentParentObservationId = $observationId;

        return $this;
    }

    /**
     * Get the TracerProvider instance
     */
    public function getTracerProvider(): ?TracerProviderInterface
    {
        return $this->tracerProvider;
    }

    /**
     * Reset the global state (useful for testing)
     */
    public function reset(): void
    {
        $this->callback = null;
        $this->tracerProvider = null;
        $this->currentTraceId = null;
        $this->currentParentObservationId = null;
        $this->serviceName = 'phpai-kit';
    }

    /**
     * Reset the singleton instance (useful for testing)
     */
    public static function resetInstance(): void
    {
        self::$instance = null;
    }
}
