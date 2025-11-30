<?php

declare(strict_types=1);

namespace Mhrlife\PhpaiKit\VectorDB;

/**
 * Represents a search filter condition
 */
class Filter
{
    /**
     * @param string $field Metadata field name to filter on
     * @param FilterOp $operator Filter operator
     * @param mixed $value Value to compare against
     */
    public function __construct(
        public string $field,
        public FilterOp $operator,
        public mixed $value
    ) {
    }
}
