<?php

declare(strict_types=1);

namespace Mhrlife\PhpaiKit\Schema;

use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;
use ReflectionEnum;

class TypeMapper
{
    /**
     * Convert PHP type to JSON Schema type
     *
     * @param ReflectionType|null $type
     * @return array{type?: string, anyOf?: array, nullable?: bool}
     */
    public static function toJsonSchemaType(?ReflectionType $type): array
    {
        if ($type === null) {
            return ['type' => 'string'];
        }

        return match ($type::class) {
            ReflectionNamedType::class => self::handleNamedType($type),
            ReflectionUnionType::class => self::handleUnionType($type),
            ReflectionIntersectionType::class => self::handleIntersectionType($type),
            default => ['type' => 'string'],
        };
    }

    /**
     * @param ReflectionNamedType $type
     * @return array{type: string, nullable?: bool}
     */
    private static function handleNamedType(ReflectionNamedType $type): array
    {
        $typeName = $type->getName();
        $schema = ['type' => self::mapPhpTypeToJsonSchemaType($typeName)];

        if ($type->allowsNull()) {
            $schema['nullable'] = true;
        }

        return $schema;
    }

    /**
     * @param ReflectionUnionType $type
     * @return array{anyOf: array, nullable?: bool}
     */
    private static function handleUnionType(ReflectionUnionType $type): array
    {
        $types = [];
        $allowsNull = false;

        foreach ($type->getTypes() as $subType) {
            if ($subType instanceof ReflectionNamedType && $subType->getName() === 'null') {
                $allowsNull = true;
                continue;
            }

            $types[] = self::toJsonSchemaType($subType);
        }

        $schema = ['anyOf' => $types];
        if ($allowsNull) {
            $schema['nullable'] = true;
        }

        return $schema;
    }

    /**
     * @param ReflectionIntersectionType $type
     * @return array{type: string}
     */
    private static function handleIntersectionType(ReflectionIntersectionType $type): array
    {
        // Intersection types are complex, treat as object
        return ['type' => 'object'];
    }

    /**
     * Map PHP built-in types to JSON Schema types
     */
    private static function mapPhpTypeToJsonSchemaType(string $phpType): string
    {
        return match ($phpType) {
            'int' => 'integer',
            'float' => 'number',
            'bool' => 'boolean',
            'array' => 'array',
            'string' => 'string',
            'object', 'mixed' => 'object',
            default => 'object', // Assume classes are objects
        };
    }

    /**
     * Check if a type is an enum and extract its schema
     *
     * @param string $typeName
     * @return array{type: string, enum?: array, nullable?: bool}|null
     */
    public static function getEnumSchema(string $typeName): ?array
    {
        if (!enum_exists($typeName)) {
            return null;
        }

        try {
            $reflection = new ReflectionEnum($typeName);
            $cases = $reflection->getCases();

            if (empty($cases)) {
                return null;
            }

            // Check if it's a backed enum (string or int)
            $backingType = $reflection->getBackingType();
            if ($backingType === null) {
                // Pure enum - extract case names
                $values = array_map(fn($case) => $case->getName(), $cases);
                return ['type' => 'string', 'enum' => $values];
            }

            $backingTypeName = $backingType->getName();
            if ($backingTypeName === 'string') {
                $values = array_map(fn($case) => $case->getBackingValue(), $cases);
                return ['type' => 'string', 'enum' => $values];
            }

            if ($backingTypeName === 'int') {
                $values = array_map(fn($case) => $case->getBackingValue(), $cases);
                return ['type' => 'integer', 'enum' => $values];
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Parse PHPDoc type annotation and return JSON Schema type
     *
     * @param string|null $docComment
     * @return array{type?: string, items?: array, nullable?: bool}|null
     */
    public static function parsePhpDocType(?string $docComment): ?array
    {
        if ($docComment === null) {
            return null;
        }

        // Match @var type patterns
        if (preg_match('/@var\s+([^\s]+)/', $docComment, $matches)) {
            $typeString = $matches[1];
            return self::parseTypeString($typeString);
        }

        return null;
    }

    /**
     * Parse type string like "array<string>", "?int", "string|int"
     *
     * @param string $typeString
     * @return array{type?: string, items?: array, anyOf?: array, nullable?: bool}
     */
    private static function parseTypeString(string $typeString): array
    {
        // Handle nullable with ? prefix
        if (str_starts_with($typeString, '?')) {
            $baseType = self::parseTypeString(substr($typeString, 1));
            $baseType['nullable'] = true;
            return $baseType;
        }

        // Handle union types (string|int)
        if (str_contains($typeString, '|')) {
            $types = explode('|', $typeString);
            $schemas = array_map(fn($t) => self::parseTypeString(trim($t)), $types);

            $hasNull = false;
            $nonNullSchemas = [];

            foreach ($schemas as $schema) {
                if (isset($schema['type']) && $schema['type'] === 'null') {
                    $hasNull = true;
                } else {
                    $nonNullSchemas[] = $schema;
                }
            }

            $result = ['anyOf' => $nonNullSchemas];
            if ($hasNull) {
                $result['nullable'] = true;
            }

            return $result;
        }

        // Handle array<Type> or Type[]
        if (preg_match('/^array<(.+)>$/', $typeString, $matches)) {
            return [
                'type' => 'array',
                'items' => self::parseTypeString($matches[1]),
            ];
        }

        if (str_ends_with($typeString, '[]')) {
            $itemType = substr($typeString, 0, -2);
            return [
                'type' => 'array',
                'items' => self::parseTypeString($itemType),
            ];
        }

        // Simple type
        return ['type' => self::mapPhpTypeToJsonSchemaType($typeString)];
    }
}
