<?php

declare(strict_types=1);

namespace Mhrlife\PhpaiKit\Callbacks;

use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\SDK\Common\Time\ClockFactory;
use OpenTelemetry\SDK\Trace\SpanProcessor\BatchSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\SDK\Trace\TracerProviderInterface;

class LangfuseHelper
{
    /**
     * Create a default TracerProvider configured for Langfuse
     *
     * @param string|null $publicKey Langfuse public key (or uses LANGFUSE_PUBLIC_KEY env)
     * @param string|null $secretKey Langfuse secret key (or uses LANGFUSE_SECRET_KEY env)
     * @param string $endpoint Langfuse endpoint URL
     * @return TracerProviderInterface
     */
    public static function createTracerProvider(
        ?string $publicKey = null,
        ?string $secretKey = null,
        string  $endpoint = 'https://cloud.langfuse.com/api/public/otel/v1/traces'
    ): TracerProviderInterface
    {
        // Get keys from parameters or environment
        $publicKey = $publicKey ?? getenv('LANGFUSE_PUBLIC_KEY');
        $secretKey = $secretKey ?? getenv('LANGFUSE_SECRET_KEY');

        if (!$publicKey || !$secretKey) {
            throw new \RuntimeException(
                'Langfuse API keys not provided. ' .
                'Set LANGFUSE_PUBLIC_KEY and LANGFUSE_SECRET_KEY environment variables ' .
                'or pass them to createTracerProvider()'
            );
        }

        $authString = base64_encode($publicKey . ':' . $secretKey);

        $innerTransport = (new OtlpHttpTransportFactory())->create(
            $endpoint,
            'application/x-protobuf',
            ['Authorization' => 'Basic ' . $authString]
        );

        // Wrap transport to ignore JSON responses from Langfuse
        // Langfuse accepts protobuf but returns JSON, causing parsing errors
        $transport = new LangfuseTransportWrapper($innerTransport);
        $exporter = new SpanExporter($transport);

        $clock = ClockFactory::getDefault();

        // Use BatchSpanProcessor for efficient trace export
        $batchProcessor = new BatchSpanProcessor(
            $exporter,
            $clock,
            2048,  // max queue size
            5000,  // schedule delay millis (5 seconds)
            30000, // export timeout millis (30 seconds)
            512,   // max export batch size
        );

        return new TracerProvider($batchProcessor);
    }
}
