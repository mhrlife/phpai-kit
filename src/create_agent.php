<?php

declare(strict_types=1);

namespace Mhrlife\PhpaiKit;

use Mhrlife\PhpaiKit\Agent\Agent;
use Mhrlife\PhpaiKit\Agent\AgentBuilder;
use OpenAI\Client;

/**
 * Create an agent with tools and optional output model
 *
 * NOTE: Callbacks should be passed to agent->run(), not at creation time.
 * This ensures proper trace hierarchy and parent observation ID management.
 *
 * @param Client $client OpenAI client instance
 * @param array<callable> $tools Array of callable tool functions
 * @param class-string|null $output Optional output class for structured responses
 * @param string $model Model to use (default: gpt-4o)
 * @return Agent
 */
function create_agent(
    Client $client,
    array $tools = [],
    ?string $output = null,
    string $model = 'gpt-4o'
): Agent {
    $builder = new AgentBuilder($client);

    return $builder
        ->withModel($model)
        ->withTools($tools)
        ->withOutput($output)
        ->build();
}
