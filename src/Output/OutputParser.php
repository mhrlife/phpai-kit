<?php

declare(strict_types=1);

namespace Mhrlife\PhpaiKit\Output;

use Mhrlife\PhpaiKit\Exceptions\AgentException;

class OutputParser
{
    /**
     * Parse output string into typed object
     *
     * @template T of object
     * @param string|null $content
     * @param class-string<T> $className
     * @return T
     * @throws AgentException
     */
    public static function parse(?string $content, string $className): object
    {
        if ($content === null) {
            throw new AgentException("Cannot parse null content");
        }

        if (!class_exists($className)) {
            throw new AgentException("Output class {$className} does not exist");
        }

        // Try to parse as JSON first
        $data = json_decode($content, true);

        if ($data === null) {
            // If not JSON, try to extract JSON from markdown code blocks
            if (preg_match('/```json\s*(\{.*?\})\s*```/s', $content, $matches)) {
                $data = json_decode($matches[1], true);
            } elseif (preg_match('/\{.*\}/s', $content, $matches)) {
                // Try to find any JSON object in the content
                $data = json_decode($matches[0], true);
            }
        }


        if ($data === null || !is_array($data)) {
            throw new AgentException("Could not parse output as JSON");
        }

        // Create instance and populate
        try {
            $instance = new $className();

            foreach ($data as $key => $value) {
                if (property_exists($instance, $key)) {
                    $instance->$key = $value;
                }
            }

            return $instance;
        } catch (\Throwable $e) {
            throw new AgentException(
                "Failed to create output object: {$e->getMessage()}",
                previous: $e
            );
        }
    }
}
