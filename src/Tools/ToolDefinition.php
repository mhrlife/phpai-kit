<?php

declare(strict_types=1);

namespace Mhrlife\PhpaiKit\Tools;

class ToolDefinition
{
    /**
     * @param string $name
     * @param string $description
     * @param array<string, mixed> $parameters JSON Schema for parameters
     * @param callable $callable The actual function to call
     */
    public function __construct(
        public readonly string $name,
        public readonly string $description,
        public readonly array $parameters,
        public readonly mixed $callable
    ) {
    }

    /**
     * Convert to OpenAI tool format
     *
     * @return array<string, mixed>
     */
    public function toOpenAIFormat(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name,
                'description' => $this->description,
                'parameters' => $this->parameters,
            ],
        ];
    }
}
