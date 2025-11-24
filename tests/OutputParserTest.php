<?php

declare(strict_types=1);

namespace Mhrlife\PhpaiKit\Tests;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/TestRunner.php';

use Mhrlife\PhpaiKit\Output\OutputParser;
use Mhrlife\PhpaiKit\Exceptions\AgentException;

echo "Testing OutputParser...\n";
$test = new TestRunner();

// Test output classes
class SimpleOutput
{
    public string $message;
    public int $code;
}

class ComplexOutput
{
    public string $name;
    public int $age;
    public array $tags;
    public ?string $email;
}

// Test parsing simple JSON
$json = '{"message": "Success", "code": 200}';
$output = OutputParser::parse($json, SimpleOutput::class);

$test->assertInstanceOf(SimpleOutput::class, $output, 'Should return instance of SimpleOutput');
$test->assertEquals('Success', $output->message, 'Message should match');
$test->assertEquals(200, $output->code, 'Code should match');

// Test parsing complex JSON
$json = '{"name": "John Doe", "age": 30, "tags": ["php", "ai"], "email": "john@example.com"}';
$output = OutputParser::parse($json, ComplexOutput::class);

$test->assertInstanceOf(ComplexOutput::class, $output, 'Should return instance of ComplexOutput');
$test->assertEquals('John Doe', $output->name, 'Name should match');
$test->assertEquals(30, $output->age, 'Age should match');
$test->assertEquals(['php', 'ai'], $output->tags, 'Tags should match');
$test->assertEquals('john@example.com', $output->email, 'Email should match');

// Test parsing JSON with null value
$json = '{"name": "Jane", "age": 25, "tags": [], "email": null}';
$output = OutputParser::parse($json, ComplexOutput::class);

$test->assertEquals('Jane', $output->name, 'Name should match');
$test->assertTrue(!isset($output->email) || $output->email === null, 'Email should be null');

// Test parsing JSON in markdown code block
$markdown = "Here's the result:\n```json\n{\"message\": \"From markdown\", \"code\": 404}\n```\nEnd of response";
$output = OutputParser::parse($markdown, SimpleOutput::class);

$test->assertEquals('From markdown', $output->message, 'Should extract JSON from markdown');
$test->assertEquals(404, $output->code, 'Should parse code from markdown JSON');

// Test parsing JSON embedded in text
$text = "The answer is: {\"message\": \"Embedded\", \"code\": 123} and that's it.";
$output = OutputParser::parse($text, SimpleOutput::class);

$test->assertEquals('Embedded', $output->message, 'Should extract embedded JSON');
$test->assertEquals(123, $output->code, 'Should parse embedded JSON values');

// Test parsing with extra fields (should be ignored)
$json = '{"message": "Test", "code": 100, "extra_field": "ignored", "another": 999}';
$output = OutputParser::parse($json, SimpleOutput::class);

$test->assertEquals('Test', $output->message, 'Should parse message ignoring extra fields');
$test->assertEquals(100, $output->code, 'Should parse code ignoring extra fields');

// Test parsing with missing optional fields
class OutputWithOptional
{
    public string $required;
    public ?int $optional;
}

$json = '{"required": "present"}';
$output = OutputParser::parse($json, OutputWithOptional::class);

$test->assertEquals('present', $output->required, 'Should parse required field');
$test->assertTrue(!isset($output->optional) || $output->optional === null, 'Optional should be null/unset');

// Test with null content
$test->assertThrows(
    fn() => OutputParser::parse(null, SimpleOutput::class),
    AgentException::class,
    'Should throw AgentException for null content'
);

// Test with non-existent class
$test->assertThrows(
    fn() => OutputParser::parse('{"test": "data"}', 'NonExistentClass'),
    AgentException::class,
    'Should throw AgentException for non-existent class'
);

// Test with invalid JSON
$test->assertThrows(
    fn() => OutputParser::parse('not valid json at all', SimpleOutput::class),
    AgentException::class,
    'Should throw AgentException for invalid JSON'
);

// Test with JSON containing nested objects
class NestedOutput
{
    public string $title;
    public array $metadata;
}

$json = '{"title": "Document", "metadata": {"author": "John", "date": "2024-01-01"}}';
$output = OutputParser::parse($json, NestedOutput::class);

$test->assertEquals('Document', $output->title, 'Should parse title');
$test->assertTrue(is_array($output->metadata), 'Metadata should be array');
$test->assertEquals('John', $output->metadata['author'], 'Should preserve nested structure');

// Test multiline JSON
$multilineJson = <<<JSON
{
    "message": "Multiline",
    "code": 555
}
JSON;

$output = OutputParser::parse($multilineJson, SimpleOutput::class);
$test->assertEquals('Multiline', $output->message, 'Should parse multiline JSON');
$test->assertEquals(555, $output->code, 'Should parse multiline JSON code');

$test->report();
