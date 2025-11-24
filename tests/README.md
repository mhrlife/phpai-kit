# Test Suite

Comprehensive unit tests for the PHPAi-Kit library covering all core non-API functionality.

## Running Tests

```bash
# Run all tests
composer test

# Or run directly
php tests/run_all_tests.php

# Run individual test files
php tests/TypeMapperTest.php
php tests/SchemaGeneratorTest.php
```

## Test Coverage

### TypeMapperTest.php (17 tests)
Tests for PHP to JSON Schema type conversion:
- Basic type mapping (string, int, float, bool, array)
- Nullable types
- Union types
- PHPDoc annotation parsing
- Array type notation (`array<string>`, `int[]`)
- Complex type expressions

### SchemaGeneratorTest.php (29 tests)
Tests for automatic JSON Schema generation:
- Simple class schema generation
- Property type detection
- Required vs optional fields
- Default value handling
- PHPDoc description extraction
- Nested object schemas
- Array type handling from PHPDoc
- Error handling for invalid classes

### ToolRegistryTest.php (18 tests)
Tests for tool registration and management:
- Registering single tools
- Registering multiple tools
- Tool attribute extraction
- Parameter schema generation
- OpenAI format conversion
- Class method registration
- Error handling (missing attributes, wrong parameters)
- Tool retrieval

### ToolDefinitionTest.php (17 tests)
Tests for OpenAI tool format:
- Basic tool properties
- OpenAI format structure
- Complex nested schemas
- Array and object handling
- Minimal tool definitions

### ToolExecutorTest.php (10 tests)
Tests for tool execution:
- Basic tool execution
- Parameter object creation
- Default parameter values
- Multiple operations
- Complex parameter types
- Error handling for failed tools
- Non-existent tool handling
- Extra parameter handling

### OutputParserTest.php (26 tests)
Tests for structured output parsing:
- Simple JSON parsing
- Complex object parsing
- Nullable field handling
- JSON in markdown extraction
- Embedded JSON extraction
- Optional field handling
- Nested object parsing
- Multiline JSON
- Error handling (null content, invalid JSON, non-existent classes)

## Test Statistics

- **Total Tests**: 117
- **Total Passed**: 117
- **Total Failed**: 0
- **Success Rate**: 100%

## Test Framework

The test suite uses a lightweight custom test framework (`TestRunner.php`) that provides:
- Assertion methods (`assert`, `assertEquals`, `assertTrue`, `assertFalse`, etc.)
- Exception testing (`assertThrows`)
- Detailed failure reporting
- Simple test output format

## Adding New Tests

To add a new test file:

1. Create a new file in `tests/` directory following the naming pattern `*Test.php`
2. Use the `TestRunner` class for assertions
3. Add the file to `run_all_tests.php` in the `$testFiles` array
4. Run `composer test` to verify

Example:
```php
<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/TestRunner.php';

use Mhrlife\PhpaiKit\Tests\TestRunner;

echo "Testing MyFeature...\n";
$test = new TestRunner();

$test->assertEquals(2, 1 + 1, 'Math should work');
$test->assertTrue(true, 'True should be true');

$test->report();
```

## Continuous Integration

Tests should be run:
- Before committing changes
- As part of CI/CD pipeline
- After any dependency updates
- Before releases

Run tests with:
```bash
composer test && composer lint
```
