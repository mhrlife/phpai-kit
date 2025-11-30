<?php

declare(strict_types=1);

namespace Mhrlife\PhpaiKit\VectorDB;

/**
 * Type of a filterable field for indexing
 */
enum FilterFieldType: string
{
    case Text = 'text';       // Full-text searchable
    case Tag = 'tag';         // Exact match (like category)
    case Numeric = 'numeric'; // Numeric range queries
}
