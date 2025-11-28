<?php

declare(strict_types=1);

namespace Mhrlife\PhpaiKit\Tests;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/TestRunner.php';

use Mhrlife\PhpaiKit\Schema\SchemaGenerator;
use Mhrlife\PhpaiKit\Exceptions\SchemaException;

echo "Testing SchemaGenerator...\n";
$test = new TestRunner();

// Test class for schema generation
class SimpleClass
{
    public string $name;
    public int $age;
    public ?string $email = null;
}

// Test simple class schema
$schema = SchemaGenerator::generate(SimpleClass::class);

$test->assertEquals('object', $schema['type'], 'Schema should have type "object"');
$test->assertTrue(isset($schema['properties']), 'Schema should have properties');
$test->assertEquals(3, count($schema['properties']), 'Schema should have 3 properties');

// Check name property
$test->assertTrue(isset($schema['properties']['name']), 'Schema should have "name" property');
$test->assertEquals('string', $schema['properties']['name']['type'], 'Name should be string type');

// Check age property
$test->assertTrue(isset($schema['properties']['age']), 'Schema should have "age" property');
$test->assertEquals('integer', $schema['properties']['age']['type'], 'Age should be integer type');

// Check email property
$test->assertTrue(isset($schema['properties']['email']), 'Schema should have "email" property');
$test->assertEquals('string', $schema['properties']['email']['type'], 'Email should be string type');
$test->assertTrue($schema['properties']['email']['nullable'], 'Email should be nullable');

// Check required fields
$test->assertTrue(isset($schema['required']), 'Schema should have required array');
$test->assertTrue(in_array('name', $schema['required']), 'Name should be required');
$test->assertTrue(in_array('age', $schema['required']), 'Age should be required');
$test->assertFalse(in_array('email', $schema['required']), 'Email should not be required (has default)');

// Test class with PHPDoc
class ClassWithDocs
{
    /**
     * User's full name
     */
    public string $name;

    /**
     * List of tags
     * @var array<string>
     */
    public array $tags;
}

$schema = SchemaGenerator::generate(ClassWithDocs::class);

// Check description
$test->assertTrue(isset($schema['properties']['name']['description']), 'Name should have description');
$test->assertEquals("User's full name", $schema['properties']['name']['description'], 'Description should match PHPDoc');

// Check array type from PHPDoc
$test->assertEquals('array', $schema['properties']['tags']['type'], 'Tags should be array type');
$test->assertTrue(isset($schema['properties']['tags']['items']), 'Tags should have items');
$test->assertEquals('string', $schema['properties']['tags']['items']['type'], 'Tag items should be string');

// Test nested objects
class Address
{
    public string $street;
    public string $city;
}

class PersonWithAddress
{
    public string $name;
    public Address $address;
}

$schema = SchemaGenerator::generate(PersonWithAddress::class);

$test->assertTrue(isset($schema['properties']['address']), 'Schema should have address property');
$test->assertEquals('object', $schema['properties']['address']['type'], 'Address should be object type');
$test->assertTrue(isset($schema['properties']['address']['properties']), 'Address should have nested properties');
$test->assertTrue(isset($schema['properties']['address']['properties']['street']), 'Address should have street');
$test->assertTrue(isset($schema['properties']['address']['properties']['city']), 'Address should have city');

// Test invalid class
$test->assertThrows(
    fn() => SchemaGenerator::generate('NonExistentClass'),
    SchemaException::class,
    'Should throw SchemaException for non-existent class'
);

// Test class with default values
class ClassWithDefaults
{
    public string $required;
    public string $optional = 'default';
    public int $count = 0;
}

$schema = SchemaGenerator::generate(ClassWithDefaults::class);

$test->assertEquals(1, count($schema['required']), 'Only properties without defaults should be required');
$test->assertTrue(in_array('required', $schema['required']), 'Required should be in required array');
$test->assertFalse(in_array('optional', $schema['required']), 'Optional should not be required');
$test->assertFalse(in_array('count', $schema['required']), 'Count should not be required');

// Test string enum
enum StatusEnum: string
{
    case PENDING = 'pending';
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
}

class ClassWithStringEnum
{
    public StatusEnum $status;
}

$schema = SchemaGenerator::generate(ClassWithStringEnum::class);

$test->assertTrue(isset($schema['properties']['status']), 'Schema should have status property');
$test->assertEquals('string', $schema['properties']['status']['type'], 'Status should be string type');
$test->assertTrue(isset($schema['properties']['status']['enum']), 'Status should have enum values');
$test->assertEquals(['pending', 'active', 'inactive'], $schema['properties']['status']['enum'], 'Enum values should match');

// Test int enum
enum PriorityEnum: int
{
    case LOW = 1;
    case MEDIUM = 2;
    case HIGH = 3;
}

class ClassWithIntEnum
{
    public PriorityEnum $priority;
}

$schema = SchemaGenerator::generate(ClassWithIntEnum::class);

$test->assertTrue(isset($schema['properties']['priority']), 'Schema should have priority property');
$test->assertEquals('integer', $schema['properties']['priority']['type'], 'Priority should be integer type');
$test->assertTrue(isset($schema['properties']['priority']['enum']), 'Priority should have enum values');
$test->assertEquals([1, 2, 3], $schema['properties']['priority']['enum'], 'Enum values should match');

// Test nullable enum
class ClassWithNullableEnum
{
    public ?StatusEnum $status = null;
}

$schema = SchemaGenerator::generate(ClassWithNullableEnum::class);

$test->assertTrue(isset($schema['properties']['status']), 'Schema should have status property');
$test->assertEquals('string', $schema['properties']['status']['type'], 'Status should be string type');
$test->assertTrue(isset($schema['properties']['status']['nullable']), 'Status should be nullable');
$test->assertTrue($schema['properties']['status']['nullable'], 'Nullable should be true');

// Test pure enum (no backing type)
enum ColorEnum
{
    case RED;
    case GREEN;
    case BLUE;
}

class ClassWithPureEnum
{
    public ColorEnum $color;
}

$schema = SchemaGenerator::generate(ClassWithPureEnum::class);

$test->assertTrue(isset($schema['properties']['color']), 'Schema should have color property');
$test->assertEquals('string', $schema['properties']['color']['type'], 'Color should be string type');
$test->assertTrue(isset($schema['properties']['color']['enum']), 'Color should have enum values');
$test->assertEquals(['RED', 'GREEN', 'BLUE'], $schema['properties']['color']['enum'], 'Pure enum case names should be enum values');

$test->report();
