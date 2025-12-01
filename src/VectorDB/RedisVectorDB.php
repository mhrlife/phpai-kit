<?php

declare(strict_types=1);

namespace Mhrlife\PhpaiKit\VectorDB;

use Mhrlife\PhpaiKit\Embedding\Client as EmbeddingClient;
use Predis\Client as PredisClient;
use Predis\Command\Argument\Search\CreateArguments;
use Predis\Command\Argument\Search\SchemaFields\TagField;
use Predis\Command\Argument\Search\SchemaFields\TextField;
use Predis\Command\Argument\Search\SchemaFields\VectorField;
use Predis\Command\Argument\Search\SearchArguments;

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
                    'DISTANCE_METRIC', $config->distanceMetric,
                ]
            ),
        ];

        // Add filterable fields to schema
        foreach ($config->filterableFields as $field) {
            $fieldName = 'meta_' . $field->name;
            $schema[] = match ($field->type) {
                FilterFieldType::Text => new TextField($fieldName),
                FilterFieldType::Tag => new TagField($fieldName),
                FilterFieldType::Numeric => new \Predis\Command\Argument\Search\SchemaFields\NumericField($fieldName),
            };
        }

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
        $contents = array_map(fn (Document $doc) => $doc->content, $docs);

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

            // Prepare document data
            $docData = [
                'id' => $doc->id,
                'content' => $doc->content,
                'metadata' => json_encode($doc->meta),
                'embedding' => $binaryEmbedding,
            ];

            // Add filterable metadata fields with meta_ prefix
            foreach ($this->indexConfig->filterableFields as $field) {
                if (isset($doc->meta[$field->name])) {
                    $docData['meta_' . $field->name] = $doc->meta[$field->name];
                }
            }

            // Store document with embedding
            $pipe->hmset($key, $docData);
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

        // Build filter prefix
        $filterPrefix = '*';
        if (!empty($search->filters)) {
            $filterPrefix = $this->buildFilterQuery($search->filters);
        }

        // Perform KNN search using Predis
        try {
            $result = $this->client->ftsearch(
                $this->index,
                "{$filterPrefix}=>[KNN {$search->topK} @embedding \$vec AS score]",
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

    /**
     * Build Redis Search filter query from filters
     *
     * @param array<Filter> $filters
     */
    private function buildFilterQuery(array $filters): string
    {
        if (empty($filters)) {
            return '*';
        }

        $parts = [];
        foreach ($filters as $filter) {
            $fieldName = 'meta_' . $filter->field;
            $part = '';

            switch ($filter->operator) {
                case FilterOp::Eq:
                    // Tag exact match: @field:{value}
                    $part = sprintf('@%s:{%s}', $fieldName, $this->escapeTagValue($filter->value));
                    break;

                case FilterOp::In:
                    // Tag in list: @field:{val1|val2|val3}
                    if (is_array($filter->value)) {
                        $escaped = array_map(fn ($v) => $this->escapeTagValue($v), $filter->value);
                        $part = sprintf('@%s:{%s}', $fieldName, implode('|', $escaped));
                    }
                    break;

                case FilterOp::Contains:
                    // Text contains: @field:value
                    $part = sprintf('@%s:%s', $fieldName, $filter->value);
                    break;

                case FilterOp::Range:
                    // Numeric range: @field:[min max]
                    if ($filter->value instanceof NumericRange) {
                        $part = sprintf('@%s:[%s %s]', $fieldName, $filter->value->min, $filter->value->max);
                    }
                    break;

                case FilterOp::Gte:
                    // Numeric >=: @field:[value +inf]
                    $part = sprintf('@%s:[%s +inf]', $fieldName, $filter->value);
                    break;

                case FilterOp::Lte:
                    // Numeric <=: @field:[-inf value]
                    $part = sprintf('@%s:[-inf %s]', $fieldName, $filter->value);
                    break;
            }

            if ($part !== '') {
                $parts[] = $part;
            }
        }

        if (empty($parts)) {
            return '*';
        }

        // Combine with AND (space separated in Redis Search)
        return '(' . implode(' ', $parts) . ')';
    }

    /**
     * Escape special characters in tag values for Redis Search
     */
    private function escapeTagValue(mixed $value): string
    {
        $s = (string) $value;
        // Escape special characters in Redis Search tag syntax
        $replacements = [
            ',' => '\\,',
            '.' => '\\.',
            '<' => '\\<',
            '>' => '\\>',
            '{' => '\\{',
            '}' => '\\}',
            '[' => '\\[',
            ']' => '\\]',
            '"' => '\\"',
            "'" => "\\'",
            ':' => '\\:',
            ';' => '\\;',
            '!' => '\\!',
            '@' => '\\@',
            '#' => '\\#',
            '$' => '\\$',
            '%' => '\\%',
            '^' => '\\^',
            '&' => '\\&',
            '*' => '\\*',
            '(' => '\\(',
            ')' => '\\)',
            '-' => '\\-',
            '+' => '\\+',
            '=' => '\\=',
            '~' => '\\~',
            ' ' => '\\ ',
        ];
        return strtr($s, $replacements);
    }
}
