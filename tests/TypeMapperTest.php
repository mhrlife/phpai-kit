<?php

declare(strict_types=1);

namespace Mhrlife\PhpaiKit\Tests;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/TestRunner.php';

use Mhrlife\PhpaiKit\Schema\TypeMapper;
use ReflectionClass;

echo "Testing TypeMapper...\n";
$test = new TestRunner();

// Test basic PHP types
class TypeMapperTestClass
{
    public string $stringProp;
    public int $intProp;
    public float $floatProp;
    public bool $boolProp;
    public array $arrayProp;
    public ?string $nullableString;
    public string|int $unionType;
}

$reflection = new ReflectionClass(TypeMapperTestClass::class);

// Test string type
$prop = $reflection->getProperty('stringProp');
$schema = TypeMapper::toJsonSchemaType($prop->getType());
$test->assertEquals('string', $schema['type'], 'String type should map to "string"');

// Test int type
$prop = $reflection->getProperty('intProp');
$schema = TypeMapper::toJsonSchemaType($prop->getType());
$test->assertEquals('integer', $schema['type'], 'Int type should map to "integer"');

// Test float type
$prop = $reflection->getProperty('floatProp');
$schema = TypeMapper::toJsonSchemaType($prop->getType());
$test->assertEquals('number', $schema['type'], 'Float type should map to "number"');

// Test bool type
$prop = $reflection->getProperty('boolProp');
$schema = TypeMapper::toJsonSchemaType($prop->getType());
$test->assertEquals('boolean', $schema['type'], 'Bool type should map to "boolean"');

// Test array type
$prop = $reflection->getProperty('arrayProp');
$schema = TypeMapper::toJsonSchemaType($prop->getType());
$test->assertEquals('array', $schema['type'], 'Array type should map to "array"');

// Test nullable type
$prop = $reflection->getProperty('nullableString');
$schema = TypeMapper::toJsonSchemaType($prop->getType());
$test->assertEquals('string', $schema['type'], 'Nullable string should have type "string"');
$test->assertTrue($schema['nullable'], 'Nullable string should have nullable flag');

// Test union type
$prop = $reflection->getProperty('unionType');
$schema = TypeMapper::toJsonSchemaType($prop->getType());
$test->assertTrue(isset($schema['anyOf']), 'Union type should have anyOf property');
$test->assertEquals(2, count($schema['anyOf']), 'Union type should have 2 options');

// Test PHPDoc parsing
$phpDoc = '/** @var array<string> */';
$schema = TypeMapper::parsePhpDocType($phpDoc);
$test->assertEquals('array', $schema['type'], 'PHPDoc array<string> should have type "array"');
$test->assertEquals('string', $schema['items']['type'], 'PHPDoc array<string> items should be "string"');

// Test PHPDoc with ?
$phpDoc = '/** @var ?int */';
$schema = TypeMapper::parsePhpDocType($phpDoc);
$test->assertEquals('integer', $schema['type'], 'PHPDoc ?int should have type "integer"');
$test->assertTrue($schema['nullable'], 'PHPDoc ?int should be nullable');

// Test PHPDoc with union
$phpDoc = '/** @var string|int */';
$schema = TypeMapper::parsePhpDocType($phpDoc);
$test->assertTrue(isset($schema['anyOf']), 'PHPDoc union should have anyOf');
$test->assertEquals(2, count($schema['anyOf']), 'PHPDoc union should have 2 options');

// Test PHPDoc with array notation
$phpDoc = '/** @var int[] */';
$schema = TypeMapper::parsePhpDocType($phpDoc);
$test->assertEquals('array', $schema['type'], 'PHPDoc int[] should have type "array"');
$test->assertEquals('integer', $schema['items']['type'], 'PHPDoc int[] items should be "integer"');

$test->report();
