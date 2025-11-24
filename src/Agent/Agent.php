<?php

declare(strict_types=1);

namespace Mhrlife\PhpaiKit\Agent;

use Mhrlife\PhpaiKit\Callbacks\AgentCallback;
use Mhrlife\PhpaiKit\Exceptions\AgentException;
use Mhrlife\PhpaiKit\Output\OutputParser;
use Mhrlife\PhpaiKit\Schema\SchemaGenerator;
use Mhrlife\PhpaiKit\Tools\ToolExecutor;
use Mhrlife\PhpaiKit\Tools\ToolRegistry;
use OpenAI\Client;

class Agent
{
    private ToolExecutor $executor;
    /**
     * @var array<array<string, mixed>>
     */
    private array $messages = [];

    /**
     * @param Client $client
     * @param ToolRegistry $registry
     * @param string $model
     * @param class-string|null $outputClass
     */
    public function __construct(
        private readonly Client       $client,
        private readonly ToolRegistry $registry,
        private readonly string       $model = 'gpt-4o',
        private readonly ?string      $outputClass = null
    )
    {
        $this->executor = new ToolExecutor($registry);
    }

    /**
     * Run the agent with a message
     *
     * @param string|array<array<string, mixed>> $messages
     * @param array<AgentCallback> $callbacks
     * @return mixed
     * @throws AgentException
     */
    public function run(string|array $messages, array $callbacks = []): mixed
    {
        // Trigger onRunStart callback
        $this->triggerCallback($callbacks, 'onRunStart', [
            'model' => $this->model,
            'input' => $messages,
            'has_output_class' => $this->outputClass !== null,
        ]);

        try {
            // Initialize messages
            if (is_string($messages)) {
                $this->messages = [
                    ['role' => 'user', 'content' => $messages],
                ];
            } else {
                $this->messages = $messages;
            }

            // Add system message for structured output if needed
            if ($this->outputClass !== null) {
                $schema = SchemaGenerator::generate($this->outputClass);
                $schemaJson = json_encode($schema, JSON_PRETTY_PRINT);

                $systemMessage = [
                    'role' => 'system',
                    'content' => "After you complete all tool calls and have the final answer, you MUST respond with a valid JSON object matching this exact schema:\n\n{$schemaJson}\n\nDo not include any other text, only the JSON object."
                ];

                array_unshift($this->messages, $systemMessage);
            }

            $maxIterations = 20;
            $iteration = 0;

            while ($iteration < $maxIterations) {
                $iteration++;

                // Build request parameters
                $params = [
                    'model' => $this->model,
                    'messages' => $this->messages,
                ];

                // Add tools if available
                $tools = $this->registry->toOpenAIFormat();
                if (!empty($tools)) {
                    $params['tools'] = $tools;
                }

                // Add structured output if output class is specified
                if ($this->outputClass !== null) {
                    $schema = SchemaGenerator::generate($this->outputClass);
                    // Add additionalProperties: false for strict mode compatibility
                    $schema['additionalProperties'] = false;

                    $params['response_format'] = [
                        'type' => 'json_schema',
                        'json_schema' => [
                            'name' => 'output',
                            'schema' => $schema,
                            'strict' => false,
                        ],
                    ];
                }

                // Trigger onGenerationStart callback
                $this->triggerCallback($callbacks, 'onGenerationStart', [
                    'iteration' => $iteration,
                    'messages' => $this->messages,
                    'model' => $this->model,
                ]);

                // Make API call
                $response = $this->client->chat()
                    ->create($params);

                $choice = $response->choices[0] ?? null;
                if ($choice === null) {
                    throw new AgentException("No response from OpenAI");
                }

                $finishReason = $choice->finishReason;
                $message = $choice->message;

                // Trigger onGenerationEnd callback
                $this->triggerCallback($callbacks, 'onGenerationEnd', [
                    'iteration' => $iteration,
                    'finish_reason' => $finishReason,
                    'content' => $message->content,
                    'tool_calls' => $message->toolCalls,
                    'usage' => $response->usage ?? null,
                ]);

                // Add assistant message to history
                $this->messages[] = [
                    'role' => 'assistant',
                    'content' => $message->content,
                    'tool_calls' => $message->toolCalls,
                ];

                // Check if we're done
                if ($finishReason === 'stop') {
                    $output = $this->parseOutput($message->content);

                    // Trigger onRunEnd callback
                    $this->triggerCallback($callbacks, 'onRunEnd', [
                        'output' => $output,
                        'total_iterations' => $iteration,
                    ]);

                    return $output;
                }

                // Handle tool calls
                if ($finishReason === 'tool_calls' && !empty($message->toolCalls)) {
                    foreach ($message->toolCalls as $toolCall) {
                        $toolName = $toolCall->function->name;
                        $arguments = json_decode($toolCall->function->arguments, true);

                        if ($arguments === null) {
                            throw new AgentException("Invalid tool call arguments JSON");
                        }

                        // Trigger onToolCallStart callback
                        $this->triggerCallback($callbacks, 'onToolCallStart', [
                            'tool_name' => $toolName,
                            'arguments' => $arguments,
                            'tool_call_id' => $toolCall->id,
                        ]);

                        // Execute tool
                        $result = $this->executor->execute($toolName, $arguments);

                        // Trigger onToolCallEnd callback
                        $this->triggerCallback($callbacks, 'onToolCallEnd', [
                            'tool_name' => $toolName,
                            'arguments' => $arguments,
                            'result' => $result,
                            'tool_call_id' => $toolCall->id,
                        ]);

                        // Add tool result to messages
                        $this->messages[] = [
                            'role' => 'tool',
                            'tool_call_id' => $toolCall->id,
                            'content' => json_encode($result),
                        ];
                    }

                    // Continue loop to send tool results back
                    continue;
                }

                // Other finish reasons (length, content_filter, etc.)
                throw new AgentException("Unexpected finish reason: {$finishReason}");
            }

            throw new AgentException("Max iterations ({$maxIterations}) reached");
        } catch (\Throwable $e) {
            // Trigger onError callback
            $this->triggerCallback($callbacks, 'onError', [
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);

            throw $e;
        }
    }

    /**
     * Parse final output
     *
     * @param string|null $content
     * @return mixed
     */
    private function parseOutput(?string $content): mixed
    {
        if ($this->outputClass === null) {
            return $content;
        }

        return OutputParser::parse($content, $this->outputClass);
    }

    /**
     * Trigger callbacks
     *
     * @param array<AgentCallback> $callbacks
     * @param string $method
     * @param array<string, mixed> $context
     */
    private function triggerCallback(array $callbacks, string $method, array $context): void
    {
        foreach ($callbacks as $callback) {
            try {
                $callback->$method($context);
            } catch (\Throwable $e) {
                // Silently catch callback errors to prevent disrupting agent execution
                error_log("Callback error in {$method}: " . $e->getMessage());
            }
        }
    }

    /**
     * Get message history
     *
     * @return array<array<string, mixed>>
     */
    public function getMessages(): array
    {
        return $this->messages;
    }
}
