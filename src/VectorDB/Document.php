<?php

declare(strict_types=1);

namespace Mhrlife\PhpaiKit\VectorDB;

/**
 * Represents a document with content and metadata
 */
class Document
{
    /**
     * @param string $id Unique document identifier
     * @param string $content Document text content
     * @param array<string, mixed> $meta Custom metadata
     */
    public function __construct(
        public string $id,
        public string $content,
        public array $meta = []
    ) {
    }
}
