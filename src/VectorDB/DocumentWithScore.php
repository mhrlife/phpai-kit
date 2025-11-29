<?php

declare(strict_types=1);

namespace Mhrlife\PhpaiKit\VectorDB;

/**
 * Document with similarity score from search results
 */
class DocumentWithScore
{
    /**
     * @param Document $document The document
     * @param string $score Similarity score (lower = more similar for most metrics)
     */
    public function __construct(
        public Document $document,
        public string $score
    ) {
    }
}
