<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Mhrlife\PhpaiKit\Attributes\Tool;
use function Mhrlife\PhpaiKit\Callbacks\initialize_langfuse;
use function Mhrlife\PhpaiKit\Callbacks\create_langfuse_callback;


// 1. init langfuse once
initialize_langfuse(
    publicKey: getenv("LLM_COURSE_LANGFUSE_PUBLIC_KEY") ?: '',
    secretKey: getenv("LLM_COURSE_LANGFUSE_SECRET_KEY") ?: '',
);


// 2. define the agent
class AverageNumbersParams
{
    /**
     * @var array<float>
     */
    public array $numbers;
}

#[Tool("average_numbers", "Calculate the average of a list of numbers.")]
function averageNumbers(AverageNumbersParams $params): array
{
    $count = count($params->numbers);
    if ($count === 0) {
        return ['average' => 0.0];
    }

    $sum = array_sum($params->numbers);
    $average = $sum / $count;

    return ['average' => $average];
}


$openai = \OpenAI::factory()
    ->withApiKey(getenv('LLM_COURSE_OPENROUTER_API_KEY'))
    ->withBaseUri("https://openrouter.ai/api/v1")
    ->make();


$agent = \Mhrlife\PhpaiKit\create_agent(
    $openai,
    tools: [averageNumbers(...)]
);


// 3. run the agent with langfuse callback
echo "Running agent with Langfuse tracing...\n\n";


echo $agent->run(
    "What is the average of the numbers 10, 20, 30, 40, and 50?",
    callbacks: [create_langfuse_callback()]
) . PHP_EOL;
