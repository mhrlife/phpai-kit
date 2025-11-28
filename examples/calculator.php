<?php

require "vendor/autoload.php";


use Mhrlife\PhpaiKit\Attributes\Tool;
use function Mhrlife\PhpaiKit\Callbacks\initialize_langfuse;
use function Mhrlife\PhpaiKit\Callbacks\create_langfuse_callback;


// 1. init langfuse once
initialize_langfuse(
    publicKey: getenv("LLM_COURSE_LANGFUSE_PUBLIC_KEY") ?: '',
    secretKey: getenv("LLM_COURSE_LANGFUSE_SECRET_KEY") ?: '',
);

enum Operator: string
{
    case ADD = 'add';
    case SUBTRACT = 'subtract';
    case MULTIPLY = 'multiply';
    case DIVIDE = 'divide';
}

class Calculator
{
    public Operator $operator;
    /**
     * @var array<float>
     */
    public array $numbers;
}

#[Tool("calculator", "Calculator")]
function calculator(Calculator $params): array
{
    return match ($params->operator) {
        Operator::ADD => [array_sum($params->numbers)],
        Operator::SUBTRACT => [array_reduce($params->numbers, fn($carry, $item) => $carry - $item)],
        Operator::MULTIPLY => [array_reduce($params->numbers, fn($carry, $item) => $carry * $item, 1)],
        Operator::DIVIDE => [array_reduce($params->numbers, fn($carry, $item) => $carry / $item)],
    };
}


$openai = \OpenAI::factory()
    ->withApiKey(getenv('LLM_COURSE_OPENROUTER_API_KEY'))
    ->withBaseUri("https://openrouter.ai/api/v1")
    ->make();


$agent = \Mhrlife\PhpaiKit\create_agent(
    $openai,
    tools: [calculator(...)]
);


// 3. run the agent with langfuse callback
echo "Running agent with Langfuse tracing...\n\n";


echo $agent->run(
        "What is the average of the numbers 10, 20, 30, 40, and 50?",
        callbacks: [create_langfuse_callback()]
    ) . PHP_EOL;
