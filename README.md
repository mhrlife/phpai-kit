# PHPAi-Kit

A powerful PHP library for OpenAI communication with advanced tool function support, automatic JSON Schema generation, and structured output parsing.

## Features

- **Tool Functions with Attributes**: Decorate functions with `#[Tool]` to make them AI-callable
- **Automatic JSON Schema**: Automatically converts PHP classes to OpenAI-compatible JSON Schema
- **Structured Output**: Type-safe output models using OpenAI's `response_format` with JSON Schema
- **Agent Execution Loop**: Handles tool calls automatically until task completion
- **PHPDoc Support**: Enhanced type inference from PHPDoc annotations (`@var array<string>`, etc.)
- **Type Safety**: Full PHP 8.1+ type support with PHPStan level 5
- **Works with OpenRouter**: Compatible with OpenAI API and OpenRouter

## Requirements

- PHP 8.1 or higher
- OpenAI API key

## Installation

```bash
composer require mhrlife/phpai-kit
```

## Quick Start

### 1. Define Your Models

```php
use Mhrlife\PhpaiKit\Attributes\Tool;

// Input parameter class
class WeatherParams {
    public string $location;
    public ?string $unit = 'celsius';
}

// Output model class
class WeatherReport {
    public string $location;
    public float $temperature;
    public string $description;
}
```

### 2. Create Tool Functions

```php
#[Tool("get_weather", "Get current weather for a location")]
function getWeather(WeatherParams $params): array {
    // Your implementation
    return [
        'location' => $params->location,
        'temperature' => 22.5,
        'description' => 'Sunny',
    ];
}
```

### 3. Create and Run Agent

```php
use OpenAI;

$openai = OpenAI::factory()
    ->withApiKey($apiKey)
    ->make();

$agent = \Mhrlife\PhpaiKit\create_agent(
    $openai,
    tools: [getWeather(...)],
    output: WeatherReport::class
);

$report = $agent->run("What's the weather in Paris?");

echo "Temperature: {$report->temperature}°C\n";
```

## Core Concepts

### Tool Functions

Tool functions are regular PHP functions decorated with the `#[Tool]` attribute:

```php
#[Tool("function_name", "Description for AI")]
function myTool(InputClass $params): mixed {
    // Function must accept exactly one parameter (a class)
    // Return value can be any serializable type
}
```

**Requirements:**
- Must have exactly one parameter
- Parameter must be a class (not scalar type)
- Must have `#[Tool]` attribute with name and description

### Automatic Schema Generation

The library automatically converts your PHP classes to JSON Schema:

```php
class SearchParams {
    public string $query;           // Required string
    public ?int $limit = 10;        // Optional int with default
    /** @var array<string> */
    public array $filters = [];     // Array type from PHPDoc
}
```

**Supported Types:**
- Scalar types: `string`, `int`, `float`, `bool`
- Arrays with PHPDoc: `array<Type>`, `Type[]`
- Nested objects: Any class type
- Enums: String and integer-backed enums
- Union types: `string|int`
- Nullable types: `?string`, `string|null`

**Enum Support:**

The library automatically extracts enum values for OpenAI schema constraints:

```php
enum Status: string {
    case PENDING = 'pending';
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
}

class TaskParams {
    public string $title;
    public Status $status;          // Enum values are extracted as constraints
}
```

Generates schema with enum constraint:
```json
{
    "type": "object",
    "properties": {
        "status": {
            "type": "string",
            "enum": ["pending", "active", "inactive"]
        }
    }
}
```

### Structured Output

Define output models to get type-safe responses:

```php
class AnalysisResult {
    public string $summary;
    public int $score;
    public array $tags;
}

$agent = create_agent(
    $openai,
    tools: $tools,
    output: AnalysisResult::class
);

$result = $agent->run("Analyze this text...");
// $result is instance of AnalysisResult
```

## Advanced Usage

### Using AgentBuilder

For more control, use the fluent builder API:

```php
use Mhrlife\PhpaiKit\Agent\AgentBuilder;

$agent = (new AgentBuilder($openai))
    ->withModel('gpt-4o-mini')
    ->withTools([tool1(...), tool2(...)])
    ->withOutput(OutputClass::class)
    ->build();
```

### Observability with Callbacks

Add callbacks for tracing, logging, and monitoring. **Important**: Callbacks must be passed to `agent->run()`, not at agent creation time.

```php
use function Mhrlife\PhpaiKit\Callbacks\create_langfuse_callback;

// Create agent WITHOUT callbacks
$agent = create_agent(
    $openai,
    tools: [getWeather(...)]
);

// Pass callbacks at runtime for proper trace hierarchy
$result = $agent->run(
    "What's the weather in Paris?",
    callbacks: [create_langfuse_callback()]
);
```

**Why pass callbacks to run()?**
This ensures all LLM calls and tool executions are properly nested within a single trace. Each `agent->run()` creates one trace with nested spans for generations and tool calls.

**Built-in Callbacks:**

**Langfuse Tracing** - Complete observability with OpenTelemetry:
```php
use function Mhrlife\PhpaiKit\Callbacks\{
    initialize_langfuse,
    create_langfuse_callback
};

// Optional: Initialize once with credentials
initialize_langfuse(
    publicKey: 'pk-...',
    secretKey: 'sk-...'
);

// Or use environment variables:
// LANGFUSE_PUBLIC_KEY, LANGFUSE_SECRET_KEY

// Pass callback to each run
$agent->run($message, callbacks: [create_langfuse_callback()]);
```

**Custom Callbacks:**
Implement the `AgentCallback` interface:
```php
use Mhrlife\PhpaiKit\Callbacks\AgentCallback;

class MyCallback implements AgentCallback {
    public function onRunStart(array $context): void {
        // Called when agent starts
        // $context: ['model' => string, 'input' => mixed, 'has_output_class' => bool]
    }

    public function onRunEnd(array $context): void {
        // Called when agent completes
        // $context: ['output' => mixed, 'total_iterations' => int]
    }

    public function onGenerationStart(array $context): void {
        // Called before each LLM call
        // $context: ['iteration' => int, 'messages' => array, 'model' => string]
    }

    public function onGenerationEnd(array $context): void {
        // Called after each LLM call
        // $context: ['finish_reason' => string, 'content' => string, 'tool_calls' => array, 'usage' => object]
    }

    public function onToolCallStart(array $context): void {
        // Called before tool execution
        // $context: ['tool_name' => string, 'arguments' => array, 'tool_call_id' => string]
    }

    public function onToolCallEnd(array $context): void {
        // Called after tool execution
        // $context: ['tool_name' => string, 'arguments' => array, 'result' => mixed, 'tool_call_id' => string]
    }

    public function onError(array $context): void {
        // Called on errors
        // $context: ['error' => string, 'exception' => Throwable]
    }
}

// Use your custom callback
$agent->run($message, callbacks: [new MyCallback()]);
```

### Multiple Tools

Register multiple tools to give your agent more capabilities:

```php
#[Tool("search_web", "Search the web")]
function searchWeb(SearchParams $params): array { /* ... */ }

#[Tool("calculate", "Perform calculations")]
function calculate(CalcParams $params): array { /* ... */ }

$agent = create_agent(
    $openai,
    tools: [searchWeb(...), calculate(...)],
    output: ResultClass::class
);
```

### Complex Types with PHPDoc

Use PHPDoc for advanced type definitions:

```php
class AdvancedParams {
    /**
     * List of user IDs to process
     * @var array<int>
     */
    public array $userIds;

    /**
     * Optional configuration map
     * @var array<string, string>|null
     */
    public ?array $config = null;
}
```

## Error Handling

The library throws specific exceptions for different error types:

```php
use Mhrlife\PhpaiKit\Exceptions\{AgentException, ToolException, SchemaException};

try {
    $result = $agent->run("Your prompt");
} catch (ToolException $e) {
    // Tool execution failed
} catch (SchemaException $e) {
    // Schema generation failed
} catch (AgentException $e) {
    // Agent runtime error
}
```

## Vector Database with Filtering

Store and search documents using vector embeddings with Redis:

```php
use Mhrlife\PhpaiKit\VectorDB\{
    RedisVectorDB, Document, DocumentSearch, IndexConfig,
    Filter, FilterOp, FilterableField, FilterFieldType, NumericRange
};

// Create vector DB with filterable fields
$vectorDB = new RedisVectorDB('my_index', $embeddingClient, $redis);
$vectorDB->createIndex(new IndexConfig(
    dimensions: 1536,
    distanceMetric: 'COSINE',
    filterableFields: [
        new FilterableField('category', FilterFieldType::Tag),
        new FilterableField('year', FilterFieldType::Numeric),
    ]
));

// Store documents with metadata
$vectorDB->storeDocumentsBatch([
    new Document('go', 'Go is a fast compiled language', ['category' => 'backend', 'year' => 2009]),
    new Document('php', 'PHP is a flexible language', ['category' => 'backend', 'year' => 1995]),
]);

// Search with filters
$results = $vectorDB->searchDocuments(new DocumentSearch(
    query: 'fast programming language',
    topK: 3,
    filters: [
        new Filter('category', FilterOp::Eq, 'backend'),
    ]
));

// Range filter example
$results = $vectorDB->searchDocuments(new DocumentSearch(
    query: 'programming language',
    topK: 3,
    filters: [
        new Filter('year', FilterOp::Range, new NumericRange(2000, 2010)),
    ]
));
```

**Filter Operators:**
- `FilterOp::Eq` - Exact tag match
- `FilterOp::In` - Match any in list (array value)
- `FilterOp::Contains` - Text contains
- `FilterOp::Range` - Numeric range (use `NumericRange`)
- `FilterOp::Gte` - Greater than or equal
- `FilterOp::Lte` - Less than or equal

**Field Types:**
- `FilterFieldType::Tag` - Exact match (categories, tags)
- `FilterFieldType::Text` - Full-text search
- `FilterFieldType::Numeric` - Numeric range queries

## Examples

See the `examples/` directory for complete working examples:

- `examples/test_without_api.php` - Test all features without API calls (recommended first)
- `examples/weather_example.php` - Full weather tool example with OpenAI API
- `examples/langfuse_tracing_example.php` - Complete example with Langfuse tracing
- `examples/vector_search_example.php` - Vector search with filtering

Run the non-API example to see all features in action:
```bash
php examples/test_without_api.php
```

For Langfuse tracing:
```bash
export LANGFUSE_PUBLIC_KEY="pk-..."
export LANGFUSE_SECRET_KEY="sk-..."
php examples/langfuse_tracing_example.php
```

## Architecture

The library is organized into clear components:

- **Attributes**: `#[Tool]` decorator for marking functions
- **Schema**: JSON Schema generation from PHP classes
- **Tools**: Tool registration and execution
- **Agent**: Main agent with execution loop
- **Output**: Structured output parsing

## Development

### Run Tests

```bash
composer test
```

The test suite includes comprehensive unit tests for:
- **TypeMapper**: PHP to JSON Schema type conversion
- **SchemaGenerator**: Automatic schema generation from classes
- **ToolRegistry**: Tool registration and management
- **ToolDefinition**: OpenAI format conversion
- **ToolExecutor**: Tool execution and parameter handling
- **OutputParser**: Structured output parsing

All 117 tests pass successfully.

### Run Static Analysis

```bash
composer lint
```

### Format Code

```bash
composer format
```

### Check Formatting

```bash
composer format:check
```

## How It Works

1. **Registration**: Tools are registered with their metadata extracted via reflection
2. **Schema Generation**: Parameter classes are converted to JSON Schema automatically
3. **Execution Loop**:
   - Agent sends request with tool definitions
   - OpenAI responds with tool calls
   - Library executes tools and sends results back
   - Loop continues until completion
4. **Output Parsing**: Final response is parsed into typed output model

## License

MIT

## Author

Mohammad Hoseinirad

## Contributing

Contributions are welcome! Please ensure code passes PHPStan level 5 and follows PSR-12 formatting.
