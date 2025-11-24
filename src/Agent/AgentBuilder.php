<?php

declare(strict_types=1);

namespace Mhrlife\PhpaiKit\Agent;

use Mhrlife\PhpaiKit\Tools\ToolRegistry;
use OpenAI\Client;

class AgentBuilder
{
    private ToolRegistry $registry;
    private string $model = 'gpt-4o';
    private ?string $outputClass = null;

    public function __construct(
        private readonly Client $client
    ) {
        $this->registry = new ToolRegistry();
    }

    /**
     * Set the model to use
     *
     * @param string $model
     * @return $this
     */
    public function withModel(string $model): self
    {
        $this->model = $model;
        return $this;
    }

    /**
     * Set the output class for structured responses
     *
     * @param class-string|null $className
     * @return $this
     */
    public function withOutput(?string $className): self
    {
        $this->outputClass = $className;
        return $this;
    }

    /**
     * Add a single tool
     *
     * @param callable $tool
     * @return $this
     */
    public function withTool(callable $tool): self
    {
        $this->registry->register($tool);
        return $this;
    }

    /**
     * Add multiple tools
     *
     * @param array<callable> $tools
     * @return $this
     */
    public function withTools(array $tools): self
    {
        $this->registry->registerMany($tools);
        return $this;
    }

    /**
     * Build the agent
     *
     * @return Agent
     */
    public function build(): Agent
    {
        return new Agent(
            client: $this->client,
            registry: $this->registry,
            model: $this->model,
            outputClass: $this->outputClass
        );
    }
}
