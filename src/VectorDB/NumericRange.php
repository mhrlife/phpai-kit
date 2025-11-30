<?php

declare(strict_types=1);

namespace Mhrlife\PhpaiKit\VectorDB;

/**
 * Represents a numeric range for filtering
 */
class NumericRange
{
    public function __construct(
        public float $min,
        public float $max
    ) {
    }
}
