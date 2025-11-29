<?php

declare(strict_types=1);

namespace Mhrlife\PhpaiKit\VectorDB;

/**
 * Vector database client interface
 */
interface Client
{
    /**
     * Create vector index with configuration
     *
     * @throws \Exception
     */
    public function createIndex(IndexConfig $config): void;

    /**
     * Store a single document
     *
     * @throws \Exception
     */
    public function storeDocument(Document $doc): void;

    /**
     * Store multiple documents in batch
     *
     * @param array<Document> $docs
     * @throws \Exception
     */
    public function storeDocumentsBatch(array $docs): void;

    /**
     * Update an existing document
     *
     * @throws \Exception
     */
    public function updateDocument(Document $doc): void;

    /**
     * Delete a document by ID
     *
     * @throws \Exception
     */
    public function deleteDocument(string $id): void;

    /**
     * Search for similar documents
     *
     * @return array<DocumentWithScore>
     * @throws \Exception
     */
    public function searchDocuments(DocumentSearch $search): array;
}
