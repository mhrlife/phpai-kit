<?php

declare(strict_types=1);

namespace Mhrlife\PhpaiKit\VectorDB;

/**
 * Search parameters for vector similarity search
 */
class DocumentSearch
{
    /**
     * @param string $query Search query text
     * @param int $topK Number of results to return
     */
    public function __construct(
        public string $query,
        public int $topK
    ) {
    }
}
