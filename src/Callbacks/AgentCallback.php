<?php

declare(strict_types=1);

namespace Mhrlife\PhpaiKit\Callbacks;

interface AgentCallback
{
    /**
     * Called when agent run starts
     *
     * @param array<string, mixed> $context
     */
    public function onRunStart(array $context): void;

    /**
     * Called when agent run ends
     *
     * @param array<string, mixed> $context
     */
    public function onRunEnd(array $context): void;

    /**
     * Called when LLM generation starts
     *
     * @param array<string, mixed> $context
     */
    public function onGenerationStart(array $context): void;

    /**
     * Called when LLM generation ends
     *
     * @param array<string, mixed> $context
     */
    public function onGenerationEnd(array $context): void;

    /**
     * Called when tool call starts
     *
     * @param array<string, mixed> $context
     */
    public function onToolCallStart(array $context): void;

    /**
     * Called when tool call ends
     *
     * @param array<string, mixed> $context
     */
    public function onToolCallEnd(array $context): void;

    /**
     * Called when an error occurs
     *
     * @param array<string, mixed> $context
     */
    public function onError(array $context): void;
}
