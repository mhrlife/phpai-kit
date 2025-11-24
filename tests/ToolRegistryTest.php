<?php

declare(strict_types=1);

namespace Mhrlife\PhpaiKit\Tests;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/TestRunner.php';

use Mhrlife\PhpaiKit\Tools\ToolRegistry;
use Mhrlife\PhpaiKit\Attributes\Tool;
use Mhrlife\PhpaiKit\Exceptions\ToolException;

echo "Testing ToolRegistry...\n";
$test = new TestRunner();

// Test classes and functions
class TestParams
{
    public string $input;
    public ?int $count = 1;
}

#[Tool("test_function", "A test function")]
function testToolFunction(TestParams $params): string
{
    return "Result: {$params->input}";
}

// Test registering a tool
$registry = new ToolRegistry();
$registry->register(testToolFunction(...));

$tools = $registry->all();
$test->assertEquals(1, count($tools), 'Registry should have 1 tool');
$test->assertTrue(isset($tools['test_function']), 'Tool should be registered with correct name');

// Test getting a tool
$tool = $registry->get('test_function');
$test->assertEquals('test_function', $tool->name, 'Tool name should match');
$test->assertEquals('A test function', $tool->description, 'Tool description should match');

// Test tool parameters schema
$test->assertEquals('object', $tool->parameters['type'], 'Tool parameters should be object');
$test->assertTrue(isset($tool->parameters['properties']['input']), 'Parameters should include input');
$test->assertEquals('string', $tool->parameters['properties']['input']['type'], 'Input should be string');

// Test OpenAI format conversion
$openaiTools = $registry->toOpenAIFormat();
$test->assertEquals(1, count($openaiTools), 'Should have 1 tool in OpenAI format');
$test->assertEquals('function', $openaiTools[0]['type'], 'Type should be "function"');
$test->assertTrue(isset($openaiTools[0]['function']), 'Should have function property');
$test->assertEquals('test_function', $openaiTools[0]['function']['name'], 'Function name should match');

// Test registering multiple tools
class AnotherParams
{
    public int $value;
}

#[Tool("another_function", "Another test function")]
function anotherToolFunction(AnotherParams $params): int
{
    return $params->value * 2;
}

$registry->registerMany([testToolFunction(...), anotherToolFunction(...)]);
$tools = $registry->all();
$test->assertEquals(2, count($tools), 'Registry should have 2 tools after registerMany');

// Test function without Tool attribute
function noAttributeFunction(TestParams $params): string
{
    return "test";
}

$test->assertThrows(
    fn() => (new ToolRegistry())->register(noAttributeFunction(...)),
    ToolException::class,
    'Should throw ToolException for function without Tool attribute'
);

// Test function with wrong number of parameters
#[Tool("bad_params", "Bad function")]
function badParamsFunction(string $a, string $b): string
{
    return "test";
}

$test->assertThrows(
    fn() => (new ToolRegistry())->register(badParamsFunction(...)),
    ToolException::class,
    'Should throw ToolException for function with multiple parameters'
);

// Test function with scalar parameter
#[Tool("scalar_param", "Scalar parameter function")]
function scalarParamFunction(string $input): string
{
    return $input;
}

$test->assertThrows(
    fn() => (new ToolRegistry())->register(scalarParamFunction(...)),
    ToolException::class,
    'Should throw ToolException for function with scalar parameter'
);

// Test getting non-existent tool
$test->assertThrows(
    fn() => $registry->get('non_existent'),
    ToolException::class,
    'Should throw ToolException for non-existent tool'
);

// Test with class method
class ToolClass
{
    #[Tool("class_method", "Class method tool")]
    public function methodTool(TestParams $params): string
    {
        return "Method: {$params->input}";
    }
}

$instance = new ToolClass();
$methodRegistry = new ToolRegistry();
$methodRegistry->register([$instance, 'methodTool']);

$tool = $methodRegistry->get('class_method');
$test->assertEquals('class_method', $tool->name, 'Class method should be registered with correct name');
$test->assertEquals('Class method tool', $tool->description, 'Class method description should match');

$test->report();
