<?php

declare(strict_types=1);

namespace Mhrlife\PhpaiKit\Callbacks;

use OpenTelemetry\SDK\Common\Export\TransportInterface;
use OpenTelemetry\SDK\Common\Future\CancellationInterface;
use OpenTelemetry\SDK\Common\Future\FutureInterface;

/**
 * Wrapper for Transport that ignores Langfuse response
 *
 * Problem: Langfuse accepts Protobuf requests but returns JSON responses
 * Solution: Ignore the response since we only care about sending traces
 */
class LangfuseTransportWrapper implements TransportInterface
{
    public function __construct(
        private readonly TransportInterface $innerTransport
    ) {
    }

    public function contentType(): string
    {
        return $this->innerTransport->contentType();
    }

    public function send(string $payload, ?CancellationInterface $cancellation = null): FutureInterface
    {
        $future = $this->innerTransport->send($payload, $cancellation);
        return $future
            ->map(function ($response) {
                return null;
            })
            ->catch(function (\Throwable $e) {
                $isParsingError = (
                    strpos($e->getMessage(), 'parsing') !== false ||
                    strpos($e->getMessage(), 'Unexpected wire type') !== false ||
                    strpos($e->getMessage(), 'mergeFromString') !== false ||
                    $e instanceof \Google\Protobuf\Internal\GPBDecodeException
                );

                if ($isParsingError) {
                    return null;
                }

                throw $e;
            });
    }

    public function shutdown(?CancellationInterface $cancellation = null): bool
    {
        return $this->innerTransport->shutdown($cancellation);
    }

    public function forceFlush(?CancellationInterface $cancellation = null): bool
    {
        return $this->innerTransport->forceFlush($cancellation);
    }
}
