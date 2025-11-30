<?php

declare(strict_types=1);

namespace Mhrlife\PhpaiKit\VectorDB;

/**
 * Filter operation type
 */
enum FilterOp: string
{
    case Eq = 'eq';             // Equals (text/tag match)
    case In = 'in';             // In list of values (tag match)
    case Range = 'range';       // Numeric range [min, max]
    case Gte = 'gte';           // Greater than or equal
    case Lte = 'lte';           // Less than or equal
    case Contains = 'contains'; // Text contains
}
