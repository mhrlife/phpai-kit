<?php

declare(strict_types=1);

namespace Mhrlife\PhpaiKit\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_FUNCTION | Attribute::TARGET_METHOD)]
class Tool
{
    public function __construct(
        public readonly string $name,
        public readonly string $description = '',
        public readonly array $metadata = []
    ) {
    }
}
