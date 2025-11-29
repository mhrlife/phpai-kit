<?php

declare(strict_types=1);

namespace Mhrlife\PhpaiKit\Embedding;

use OpenAI\Client as OpenAIClient;

/**
 * OpenAI embeddings implementation
 */
class OpenAIEmbeddings implements Client
{
    private OpenAIClient $client;
    private string $model;

    /**
     * @param OpenAIClient $client OpenAI API client
     * @param string $model Embedding model name (default: text-embedding-3-small)
     */
    public function __construct(OpenAIClient $client, string $model = 'text-embedding-3-small')
    {
        $this->client = $client;
        $this->model = $model;
    }

    /**
     * @inheritDoc
     */
    public function embedTexts(array $texts): array
    {
        // Handle empty input
        if (empty($texts)) {
            return [];
        }

        // Call OpenAI embeddings API
        $response = $this->client->embeddings()->create([
            'model' => $this->model,
            'input' => $texts,
        ]);

        // Extract embeddings from response
        $embeddings = [];
        foreach ($response->embeddings as $embedding) {
            $embeddings[] = $embedding->embedding;
        }

        return $embeddings;
    }
}
