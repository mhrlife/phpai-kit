<?php

declare(strict_types=1);

namespace Mhrlife\PhpaiKit\Tests;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/TestRunner.php';

use Mhrlife\PhpaiKit\Tools\ToolExecutor;
use Mhrlife\PhpaiKit\Tools\ToolRegistry;
use Mhrlife\PhpaiKit\Attributes\Tool;
use Mhrlife\PhpaiKit\Exceptions\ToolException;

echo "Testing ToolExecutor...\n";
$test = new TestRunner();

// Test parameter classes
class CalculatorParams
{
    public int $a;
    public int $b;
    public string $operation = 'add';
}

class StringParams
{
    public string $text;
    public ?int $length = null;
}

// Test tool functions
#[Tool("calculator", "Perform calculations")]
function calculatorTool(CalculatorParams $params): int
{
    return match($params->operation) {
        'add' => $params->a + $params->b,
        'subtract' => $params->a - $params->b,
        'multiply' => $params->a * $params->b,
        default => 0,
    };
}

#[Tool("string_processor", "Process strings")]
function stringProcessorTool(StringParams $params): string
{
    if ($params->length !== null) {
        return substr($params->text, 0, $params->length);
    }
    return strtoupper($params->text);
}

#[Tool("failing_tool", "Tool that throws exception")]
function failingTool(StringParams $params): string
{
    throw new \RuntimeException("Intentional failure");
}

// Setup registry and executor
$registry = new ToolRegistry();
$registry->register(calculatorTool(...));
$registry->register(stringProcessorTool(...));
$registry->register(failingTool(...));

$executor = new ToolExecutor($registry);

// Test basic execution
$result = $executor->execute('calculator', [
    'a' => 5,
    'b' => 3,
    'operation' => 'add',
]);

$test->assertEquals(8, $result, 'Calculator should add 5 + 3 = 8');

// Test with different operation
$result = $executor->execute('calculator', [
    'a' => 10,
    'b' => 4,
    'operation' => 'multiply',
]);

$test->assertEquals(40, $result, 'Calculator should multiply 10 * 4 = 40');

// Test with default parameter value
$result = $executor->execute('calculator', [
    'a' => 15,
    'b' => 5,
]);

$test->assertEquals(20, $result, 'Calculator should use default operation (add) 15 + 5 = 20');

// Test string processor
$result = $executor->execute('string_processor', [
    'text' => 'hello world',
]);

$test->assertEquals('HELLO WORLD', $result, 'String processor should uppercase text');

// Test string processor with length parameter
$result = $executor->execute('string_processor', [
    'text' => 'hello world',
    'length' => 5,
]);

$test->assertEquals('hello', $result, 'String processor should substring to 5 characters');

// Test execution with non-existent tool
$test->assertThrows(
    fn() => $executor->execute('non_existent_tool', []),
    ToolException::class,
    'Should throw ToolException for non-existent tool'
);

// Test execution with tool that throws exception
$test->assertThrows(
    fn() => $executor->execute('failing_tool', ['text' => 'test']),
    ToolException::class,
    'Should wrap tool execution exceptions in ToolException'
);

// Test parameter object creation with missing required field
// This should still work but the tool might fail
$result = $executor->execute('calculator', [
    'a' => 5,
    'b' => 3,
    // operation is optional with default
]);

$test->assertEquals(8, $result, 'Should handle optional parameters correctly');

// Test with extra arguments (should be ignored)
$result = $executor->execute('calculator', [
    'a' => 2,
    'b' => 3,
    'operation' => 'add',
    'extra_field' => 'ignored',
]);

$test->assertEquals(5, $result, 'Should ignore extra arguments not in parameter class');

// Test complex type conversion
class ComplexParams
{
    public string $name;
    public int $age;
    public array $tags;
}

#[Tool("complex_tool", "Tool with complex parameters")]
function complexTool(ComplexParams $params): string
{
    return sprintf(
        "%s is %d years old with %d tags",
        $params->name,
        $params->age,
        count($params->tags)
    );
}

$registry->register(complexTool(...));
$result = $executor->execute('complex_tool', [
    'name' => 'John',
    'age' => 30,
    'tags' => ['developer', 'php', 'ai'],
]);

$test->assertEquals('John is 30 years old with 3 tags', $result, 'Should handle complex parameter types');

$test->report();
