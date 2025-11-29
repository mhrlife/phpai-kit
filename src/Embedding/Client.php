<?php

declare(strict_types=1);

namespace Mhrlife\PhpaiKit\Embedding;

/**
 * Embedding client interface for converting text to vectors
 */
interface Client
{
    /**
     * Convert texts to embeddings (vectors)
     *
     * @param array<string> $texts List of texts to embed
     * @return array<array<float>> 2D array of embeddings (vectors)
     * @throws \Exception
     */
    public function embedTexts(array $texts): array;
}
