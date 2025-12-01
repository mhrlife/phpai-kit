<?php

declare(strict_types=1);

namespace Mhrlife\PhpaiKit\Tools;

use Mhrlife\PhpaiKit\Attributes\Tool;
use Mhrlife\PhpaiKit\Exceptions\ToolException;
use Mhrlife\PhpaiKit\Schema\SchemaGenerator;
use ReflectionFunction;
use ReflectionMethod;

class ToolRegistry
{
    /**
     * @var array<string, ToolDefinition>
     */
    private array $tools = [];

    /**
     * Register a tool function
     *
     * @param callable $callable
     * @throws ToolException
     */
    public function register(callable $callable): void
    {
        $reflection = $this->getReflection($callable);

        // Get Tool attribute
        $attributes = $reflection->getAttributes(Tool::class);
        if (empty($attributes)) {
            throw new ToolException('Function must have #[Tool] attribute');
        }

        /** @var Tool $tool */
        $tool = $attributes[0]->newInstance();

        // Get parameter type from function signature
        $parameters = $reflection->getParameters();
        if (count($parameters) !== 1) {
            throw new ToolException('Tool function must have exactly one parameter');
        }

        $param = $parameters[0];
        $paramType = $param->getType();

        if ($paramType === null) {
            throw new ToolException('Tool function parameter must have a type hint');
        }

        $typeName = method_exists($paramType, 'getName') ? $paramType->getName() : null;
        if ($typeName === null || !class_exists($typeName)) {
            throw new ToolException('Tool function parameter must be a class type');
        }

        // Generate JSON Schema from parameter class
        $schema = SchemaGenerator::generate($typeName);

        // Create tool definition
        $definition = new ToolDefinition(
            name: $tool->name,
            description: $tool->description,
            parameters: $schema,
            callable: $callable
        );

        $this->tools[$tool->name] = $definition;
    }

    /**
     * Register multiple tools
     *
     * @param array<callable> $callables
     * @throws ToolException
     */
    public function registerMany(array $callables): void
    {
        foreach ($callables as $callable) {
            $this->register($callable);
        }
    }

    /**
     * Get a tool by name
     *
     * @throws ToolException
     */
    public function get(string $name): ToolDefinition
    {
        if (!isset($this->tools[$name])) {
            throw new ToolException("Tool '{$name}' not found");
        }

        return $this->tools[$name];
    }

    /**
     * Get all registered tools
     *
     * @return array<string, ToolDefinition>
     */
    public function all(): array
    {
        return $this->tools;
    }

    /**
     * Get all tools in OpenAI format
     *
     * @return array<array<string, mixed>>
     */
    public function toOpenAIFormat(): array
    {
        return array_map(
            fn (ToolDefinition $tool) => $tool->toOpenAIFormat(),
            array_values($this->tools)
        );
    }

    /**
     * Get reflection for callable
     *
     * @return ReflectionFunction|ReflectionMethod
     * @throws ToolException
     */
    private function getReflection(callable $callable): ReflectionFunction|ReflectionMethod
    {
        if (is_array($callable)) {
            return new ReflectionMethod($callable[0], $callable[1]);
        }

        if (is_string($callable) && str_contains($callable, '::')) {
            return new ReflectionMethod($callable);
        }

        return new ReflectionFunction($callable);
    }
}
