<?php

declare(strict_types=1);

namespace Mhrlife\PhpaiKit\VectorDB;

/**
 * Defines a metadata field that can be filtered
 */
class FilterableField
{
    /**
     * @param string $name Field name in metadata
     * @param FilterFieldType $type Field type for indexing
     */
    public function __construct(
        public string $name,
        public FilterFieldType $type
    ) {
    }
}
