<?php

declare(strict_types=1);

namespace Mhrlife\PhpaiKit\VectorDB;

use Mhrlife\PhpaiKit\Embedding\Client as EmbeddingClient;
use Predis\Client as PredisClient;
use Predis\Command\Argument\Search\CreateArguments;
use Predis\Command\Argument\Search\SearchArguments;
use Predis\Command\Argument\Search\SchemaFields\TextField;
use Predis\Command\Argument\Search\SchemaFields\TagField;
use Predis\Command\Argument\Search\SchemaFields\VectorField;

/**
 * Redis-based vector database implementation using Predis and RediSearch
 */
class RedisVectorDB implements Client
{
    private string $index;
    private EmbeddingClient $embedClient;
    private PredisClient $client;
    private ?IndexConfig $indexConfig = null;

    /**
     * @param string $index Index name (namespace for Redis keys)
     * @param EmbeddingClient $embeddingClient Embedding service client
     * @param PredisClient $redisClient Predis connection
     */
    public function __construct(string $index, EmbeddingClient $embeddingClient, PredisClient $redisClient)
    {
        $this->index = $index;
        $this->embedClient = $embeddingClient;
        $this->client = $redisClient;
    }

    /**
     * @inheritDoc
     */
    public function createIndex(IndexConfig $config): void
    {
        // Validate distance metric
        $validMetrics = ['COSINE', 'L2', 'IP'];
        if (!in_array($config->distanceMetric, $validMetrics)) {
            throw new \InvalidArgumentException(
                "Invalid distance metric: {$config->distanceMetric}. Must be one of: " . implode(', ', $validMetrics)
            );
        }

        // Store config for later validation
        $this->indexConfig = $config;

        // Drop existing index if it exists
        try {
            $this->client->ftdropindex($this->index);
        } catch (\Exception $e) {
            // Index doesn't exist, continue
        }

        // Build schema using Predis classes
        $schema = [
            new TagField('id'),
            new TextField('content'),
            new TagField('metadata'),
            new VectorField(
                'embedding',
                'HNSW',
                [
                    'TYPE', 'FLOAT32',
                    'DIM', $config->dimensions,
                    'DISTANCE_METRIC', $config->distanceMetric
                ]
            )
        ];

        // Create index with HASH storage
        try {
            $this->client->ftcreate(
                $this->index,
                $schema,
                (new CreateArguments())
                    ->on('HASH')
                    ->prefix(["{$this->index}:"])
            );
        } catch (\Exception $e) {
            throw new \Exception("Failed to create index: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function storeDocument(Document $doc): void
    {
        $this->storeDocumentsBatch([$doc]);
    }

    /**
     * @inheritDoc
     */
    public function storeDocumentsBatch(array $docs): void
    {
        // Validate index exists
        if ($this->indexConfig === null) {
            throw new \RuntimeException('Index must be created before storing documents');
        }

        if (empty($docs)) {
            return;
        }

        // Extract all content for batch embedding
        $contents = array_map(fn(Document $doc) => $doc->content, $docs);

        // Get embeddings for all documents
        $embeddings = $this->embedClient->embedTexts($contents);

        // Validate dimensions
        foreach ($embeddings as $embedding) {
            if (count($embedding) !== $this->indexConfig->dimensions) {
                throw new \RuntimeException(
                    sprintf(
                        'Embedding dimension mismatch: expected %d, got %d',
                        $this->indexConfig->dimensions,
                        count($embedding)
                    )
                );
            }
        }

        // Use pipeline for batch operations
        $pipe = $this->client->pipeline();

        foreach ($docs as $i => $doc) {
            $key = "{$this->index}:{$doc->id}";
            $embedding = $embeddings[$i];

            // Convert to float32 and encode as binary
            $binaryEmbedding = $this->encodeVector($embedding);

            // Store document with embedding
            $pipe->hmset($key, [
                'id' => $doc->id,
                'content' => $doc->content,
                'metadata' => json_encode($doc->meta),
                'embedding' => $binaryEmbedding,
            ]);
        }

        $pipe->execute();
    }

    /**
     * @inheritDoc
     */
    public function updateDocument(Document $doc): void
    {
        // Simply overwrite the existing document
        $this->storeDocument($doc);
    }

    /**
     * @inheritDoc
     */
    public function deleteDocument(string $id): void
    {
        $key = "{$this->index}:{$id}";
        $result = $this->client->del([$key]);

        if ($result === 0) {
            throw new \RuntimeException("Document not found: {$id}");
        }
    }

    /**
     * @inheritDoc
     */
    public function searchDocuments(DocumentSearch $search): array
    {
        // Validate index exists
        if ($this->indexConfig === null) {
            throw new \RuntimeException('Index must be created before searching documents');
        }

        // Validate input
        if ($search->topK <= 0) {
            throw new \InvalidArgumentException('TopK must be greater than 0');
        }

        if (empty($search->query)) {
            throw new \InvalidArgumentException('Query cannot be empty');
        }

        // Embed the query
        $embeddings = $this->embedClient->embedTexts([$search->query]);
        $queryVector = $embeddings[0];

        // Validate dimensions
        if (count($queryVector) !== $this->indexConfig->dimensions) {
            throw new \RuntimeException(
                sprintf(
                    'Query embedding dimension mismatch: expected %d, got %d',
                    $this->indexConfig->dimensions,
                    count($queryVector)
                )
            );
        }

        // Encode query vector as binary
        $binaryQuery = $this->encodeVector($queryVector);

        // Perform KNN search using Predis
        try {
            $result = $this->client->ftsearch(
                $this->index,
                "*=>[KNN {$search->topK} @embedding \$vec AS score]",
                (new SearchArguments())
                    ->addReturn(4, 'id', 'content', 'metadata', 'score')
                    ->dialect('2')
                    ->params(['vec', $binaryQuery])
                    ->sortBy('score')
            );
        } catch (\Exception $e) {
            throw new \Exception("Search failed: {$e->getMessage()}", 0, $e);
        }

        // Parse results
        return $this->parseSearchResults($result);
    }

    /**
     * Encode float array as binary (FLOAT32)
     *
     * @param array<float> $vector
     */
    private function encodeVector(array $vector): string
    {
        // Pack as machine-dependent float (g* format)
        return pack('g*', ...$vector);
    }

    /**
     * Parse Predis FT.SEARCH results
     *
     * @param array<mixed> $result Raw Predis response
     * @return array<DocumentWithScore>
     */
    private function parseSearchResults(array $result): array
    {
        $documents = [];

        // Result format: [count, key1, [field1, value1, field2, value2, ...], key2, [...], ...]
        $count = array_shift($result);

        for ($i = 0; $i < $count; $i++) {
            $key = array_shift($result);
            $fields = array_shift($result);

            // Convert fields array to associative array
            $data = [];
            for ($j = 0; $j < count($fields); $j += 2) {
                $data[$fields[$j]] = $fields[$j + 1];
            }

            // Parse metadata
            $meta = [];
            if (isset($data['metadata'])) {
                $meta = json_decode($data['metadata'], true) ?? [];
            }

            // Create document
            $doc = new Document(
                id: $data['id'] ?? '',
                content: $data['content'] ?? '',
                meta: $meta
            );

            $documents[] = new DocumentWithScore(
                document: $doc,
                score: $data['score'] ?? '0'
            );
        }

        return $documents;
    }
}
