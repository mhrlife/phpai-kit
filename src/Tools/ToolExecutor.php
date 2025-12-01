<?php

declare(strict_types=1);

namespace Mhrlife\PhpaiKit\Tools;

use Mhrlife\PhpaiKit\Exceptions\ToolException;

class ToolExecutor
{
    public function __construct(
        private readonly ToolRegistry $registry
    ) {
    }

    /**
     * Execute a tool call
     *
     * @param string $toolName
     * @param array<string, mixed> $arguments
     * @return mixed
     * @throws ToolException
     */
    public function execute(string $toolName, array $arguments): mixed
    {
        $tool = $this->registry->get($toolName);

        // Convert arguments array to parameter object
        $paramObject = $this->arrayToObject($arguments, $tool);

        // Execute the tool
        try {
            return call_user_func($tool->callable, $paramObject);
        } catch (\Throwable $e) {
            throw new ToolException(
                "Error executing tool '{$toolName}': {$e->getMessage()}",
                previous: $e
            );
        }
    }

    /**
     * Convert arguments array to parameter object
     *
     * @param array<string, mixed> $arguments
     * @param ToolDefinition $tool
     * @return object
     * @throws ToolException
     */
    private function arrayToObject(array $arguments, ToolDefinition $tool): object
    {
        // Get parameter class name from callable reflection
        $reflection = new \ReflectionFunction($tool->callable);
        $parameters = $reflection->getParameters();

        if (empty($parameters)) {
            throw new ToolException('Tool function has no parameters');
        }

        $param = $parameters[0];
        $paramType = $param->getType();

        if ($paramType === null) {
            throw new ToolException('Tool parameter has no type');
        }

        $className = method_exists($paramType, 'getName') ? $paramType->getName() : null;
        if ($className === null || !class_exists($className)) {
            throw new ToolException('Tool parameter type is not a valid class');
        }

        // Create instance and populate properties
        try {
            $instance = new $className();
            $classReflection = new \ReflectionClass($className);

            foreach ($arguments as $key => $value) {
                if (property_exists($instance, $key)) {
                    $property = $classReflection->getProperty($key);
                    $propertyType = $property->getType();

                    // Handle enum conversion
                    if ($propertyType !== null && method_exists($propertyType, 'getName')) {
                        $typeName = $propertyType->getName();
                        if (enum_exists($typeName)) {
                            $enumReflection = new \ReflectionEnum($typeName);
                            $backingType = $enumReflection->getBackingType();

                            // Handle backed enums (string or int)
                            if ($backingType !== null && (is_string($value) || is_int($value))) {
                                $instance->$key = $typeName::from($value);
                                continue;
                            }

                            // Handle pure enums (use case name)
                            if ($backingType === null && is_string($value)) {
                                foreach ($enumReflection->getCases() as $case) {
                                    if ($case->getName() === $value) {
                                        $instance->$key = $case->getValue();
                                        break;
                                    }
                                }
                                continue;
                            }
                        }
                    }

                    $instance->$key = $value;
                }
            }

            return $instance;
        } catch (\Throwable $e) {
            throw new ToolException(
                "Failed to create parameter object: {$e->getMessage()}",
                previous: $e
            );
        }
    }
}
