<?php
require "vendor/autoload.php";

use Mhrlife\PhpaiKit\Attributes\Tool;
use Mhrlife\PhpaiKit\Tools\EmptyParam;
use function Mhrlife\PhpaiKit\Callbacks\create_langfuse_callback;
use function Mhrlife\PhpaiKit\Callbacks\initialize_langfuse;
use function Mhrlife\PhpaiKit\create_agent;


initialize_langfuse(
    publicKey: getenv("LLM_COURSE_LANGFUSE_PUBLIC_KEY") ?: '',
    secretKey: getenv("LLM_COURSE_LANGFUSE_SECRET_KEY") ?: '',
);


$openai = \OpenAI::factory()
    ->withApiKey(getenv('LLM_COURSE_OPENROUTER_API_KEY'))
    ->withBaseUri("https://openrouter.ai/api/v1")
    ->make();


class GenRandomNumberParam
{
    public int $min = 1;
    public int $max = 100;
}

#[Tool(
    name: "gen_random_number",
    description: "Generate a random number between 1 and 100"
)]
function gen_random_number(GenRandomNumberParam $param): array
{
    return ['number' => rand($param->min, $param->max)];
}

$agent = create_agent(
    $openai,
    tools: [gen_random_number(...)],
    model: "gpt-4.1-mini"
);

$response = $agent->run(
    "Generate a random number between 1 and 100. if it was less than 20, then generate a second random number between 101 and 200, otherwise generate a second random number between 201 and 300. return both numbers.",
    callbacks: [create_langfuse_callback()]
);

echo $response;