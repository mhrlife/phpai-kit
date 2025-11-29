<?php

declare(strict_types=1);

namespace Mhrlife\PhpaiKit\VectorDB;

/**
 * Configuration for vector index
 */
class IndexConfig
{
    /**
     * @param int $dimensions Vector dimensions (e.g., 1536 for text-embedding-3-small)
     * @param string $distanceMetric Distance metric: "COSINE", "L2", or "IP"
     */
    public function __construct(
        public int $dimensions,
        public string $distanceMetric = 'COSINE'
    ) {
    }
}
