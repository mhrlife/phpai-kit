<?php

declare(strict_types=1);

namespace Mhrlife\PhpaiKit\Tests;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/TestRunner.php';

use Mhrlife\PhpaiKit\Tools\ToolDefinition;

echo "Testing ToolDefinition...\n";
$test = new TestRunner();

// Create a test callable
$testCallable = function($input) {
    return "Result: $input";
};

// Create a ToolDefinition
$definition = new ToolDefinition(
    name: 'test_tool',
    description: 'A test tool for validation',
    parameters: [
        'type' => 'object',
        'properties' => [
            'input' => ['type' => 'string'],
            'count' => ['type' => 'integer'],
        ],
        'required' => ['input'],
    ],
    callable: $testCallable
);

// Test basic properties
$test->assertEquals('test_tool', $definition->name, 'Name should match constructor value');
$test->assertEquals('A test tool for validation', $definition->description, 'Description should match');
$test->assertTrue(is_callable($definition->callable), 'Callable should be callable');

// Test parameters
$test->assertEquals('object', $definition->parameters['type'], 'Parameters type should be object');
$test->assertTrue(isset($definition->parameters['properties']), 'Parameters should have properties');
$test->assertEquals(2, count($definition->parameters['properties']), 'Should have 2 properties');

// Test OpenAI format conversion
$openaiFormat = $definition->toOpenAIFormat();

$test->assertEquals('function', $openaiFormat['type'], 'OpenAI format should have type "function"');
$test->assertTrue(isset($openaiFormat['function']), 'OpenAI format should have function key');

$function = $openaiFormat['function'];
$test->assertEquals('test_tool', $function['name'], 'Function name should match');
$test->assertEquals('A test tool for validation', $function['description'], 'Function description should match');
$test->assertTrue(isset($function['parameters']), 'Function should have parameters');
$test->assertEquals($definition->parameters, $function['parameters'], 'Parameters should match');

// Test with different parameter schema
$complexDefinition = new ToolDefinition(
    name: 'complex_tool',
    description: 'Complex tool with nested schema',
    parameters: [
        'type' => 'object',
        'properties' => [
            'user' => [
                'type' => 'object',
                'properties' => [
                    'name' => ['type' => 'string'],
                    'age' => ['type' => 'integer'],
                ],
            ],
            'tags' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
            ],
        ],
        'required' => ['user'],
    ],
    callable: $testCallable
);

$openaiFormat = $complexDefinition->toOpenAIFormat();
$test->assertTrue(isset($openaiFormat['function']['parameters']['properties']['user']), 'Should handle nested objects');
$test->assertTrue(isset($openaiFormat['function']['parameters']['properties']['tags']), 'Should handle arrays');
$test->assertEquals('object', $openaiFormat['function']['parameters']['properties']['user']['type'], 'Nested object should be type object');

// Test with minimal parameters
$minimalDefinition = new ToolDefinition(
    name: 'minimal',
    description: '',
    parameters: ['type' => 'object', 'properties' => []],
    callable: fn() => null
);

$openaiFormat = $minimalDefinition->toOpenAIFormat();
$test->assertEquals('minimal', $openaiFormat['function']['name'], 'Minimal definition should work');
$test->assertEquals('', $openaiFormat['function']['description'], 'Empty description should be allowed');

$test->report();
