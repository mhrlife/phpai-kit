<?php

declare(strict_types=1);

namespace Mhrlife\PhpaiKit\Schema;

use Mhrlife\PhpaiKit\Exceptions\SchemaException;
use ReflectionClass;
use ReflectionProperty;

class SchemaGenerator
{
    /**
     * Generate JSON Schema from a PHP class
     *
     * @param class-string $className
     * @return array<string, mixed>
     * @throws SchemaException
     */
    public static function generate(string $className): array
    {
        if (!class_exists($className)) {
            throw new SchemaException("Class {$className} does not exist");
        }

        $reflection = new ReflectionClass($className);
        $properties = [];
        $required = [];

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $propertyName = $property->getName();
            $propertySchema = self::getPropertySchema($property);

            $properties[$propertyName] = $propertySchema;

            // If property doesn't have a default value and is not nullable, it's required
            if (!$property->hasDefaultValue() && !self::isNullable($property)) {
                $required[] = $propertyName;
            }
        }

        $schema = [
            'type' => 'object',
            'properties' => $properties,
        ];

        if (!empty($required)) {
            $schema['required'] = $required;
        }

        return $schema;
    }

    /**
     * Get schema for a single property
     *
     * @param ReflectionProperty $property
     * @return array<string, mixed>
     */
    private static function getPropertySchema(ReflectionProperty $property): array
    {
        // First try to get schema from type hint
        $type = $property->getType();
        $schema = TypeMapper::toJsonSchemaType($type);

        // Try to enhance with PHPDoc information
        $docComment = $property->getDocComment();
        if ($docComment !== false) {
            $phpDocSchema = TypeMapper::parsePhpDocType($docComment);
            if ($phpDocSchema !== null) {
                // PHPDoc provides more detailed type info, use it
                $schema = array_merge($schema, $phpDocSchema);
            }

            // Extract description from PHPDoc
            $description = self::extractDescription($docComment);
            if ($description !== null) {
                $schema['description'] = $description;
            }
        }

        // If the type is a class (object), recursively generate its schema
        if (isset($schema['type']) && $schema['type'] === 'object' && $type !== null) {
            $typeName = method_exists($type, 'getName') ? $type->getName() : null;
            if ($typeName !== null && class_exists($typeName)) {
                $schema = self::generate($typeName);
            }
        }

        return $schema;
    }

    /**
     * Check if a property is nullable
     */
    private static function isNullable(ReflectionProperty $property): bool
    {
        $type = $property->getType();
        return $type !== null && $type->allowsNull();
    }

    /**
     * Extract description from PHPDoc comment
     */
    private static function extractDescription(string $docComment): ?string
    {
        // Remove /** and */ and trim
        $lines = explode("\n", $docComment);
        $description = [];

        foreach ($lines as $line) {
            $line = trim($line);
            // Remove leading /** or /*
            $line = preg_replace('/^\/\*+\s*/', '', $line);
            // Remove trailing */
            $line = preg_replace('/\s*\*+\/$/', '', $line);
            // Remove leading * and whitespace
            $line = preg_replace('/^\*+\s*/', '', $line);

            $line = trim($line);

            // Skip empty lines and @annotations
            if ($line === '' || str_starts_with($line, '@')) {
                continue;
            }
            $description[] = $line;
        }

        $result = implode(' ', $description);
        return $result !== '' ? $result : null;
    }
}
