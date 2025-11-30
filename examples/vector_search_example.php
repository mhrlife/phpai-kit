<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Mhrlife\PhpaiKit\Embedding\OpenAIEmbeddings;
use Mhrlife\PhpaiKit\VectorDB\RedisVectorDB;
use Mhrlife\PhpaiKit\VectorDB\Document;
use Mhrlife\PhpaiKit\VectorDB\DocumentSearch;
use Mhrlife\PhpaiKit\VectorDB\IndexConfig;
use Mhrlife\PhpaiKit\VectorDB\Filter;
use Mhrlife\PhpaiKit\VectorDB\FilterOp;
use Mhrlife\PhpaiKit\VectorDB\FilterableField;
use Mhrlife\PhpaiKit\VectorDB\FilterFieldType;
use Mhrlife\PhpaiKit\VectorDB\NumericRange;
use Predis\Client as PredisClient;


// Example: Vector Search with OpenAI Embeddings and Redis

// 1. Create OpenAI client
$openai = \OpenAI::factory()
    ->withApiKey(getenv("LLM_COURSE_OPENROUTER_API_KEY"))
    ->withBaseUri("https://openrouter.ai/api/v1")
    ->make();


// 2. Create embedding client
$embeddingClient = new OpenAIEmbeddings($openai, 'text-embedding-3-small');

// 3. Create Predis client
$redis = new PredisClient([
    'host' => '127.0.0.1',
    'port' => 6379,
]);

// 4. Create vector database
$vectorDB = new RedisVectorDB('my_index', $embeddingClient, $redis);

// 5. Create index with configuration (including filterable fields)
echo "Creating index...\n";
$vectorDB->createIndex(new IndexConfig(
    dimensions: 1536, // text-embedding-3-small dimension
    distanceMetric: 'COSINE',
    filterableFields: [
        new FilterableField('category', FilterFieldType::Tag),
        new FilterableField('year', FilterFieldType::Numeric),
    ]
));

// 6. Store documents (automatically embeds content)
echo "Storing documents...\n";
$vectorDB->storeDocumentsBatch([
    new Document(
        id: 'Go',
        content: 'Go is a fast compiled programming language',
        meta: ['language' => 'go', 'category' => 'programming', 'year' => 2009]
    ),
    new Document(
        id: 'PHP',
        content: 'PHP is a flexible interpreted programming language',
        meta: ['language' => 'php', 'category' => 'programming', 'year' => 1995]
    ),
    new Document(
        id: 'Python',
        content: 'Python is great for data science and machine learning',
        meta: ['language' => 'python', 'category' => 'data-science', 'year' => 1991]
    ),
    new Document(
        id: 'Redis',
        content: 'Redis is an in-memory database with vector search capabilities',
        meta: ['category' => 'database', 'year' => 2009]
    ),
    new Document(
        id: 'JavaScript',
        content: 'JavaScript is widely used for web development',
        meta: ['category' => 'front-end', 'year' => 1995]
    ),
]);

$queries = [
    'front end programming language',
    'statically typed programming language',
    'fast database',
    'programming language that uses $ (Dollar sign) as variable prefix',
];

/*
    Searching for: 'front end programming language'
    ID: JavaScript | Score: 0.535770833492

    Searching for: 'statically typed programming language'
    ID: Go | Score: 0.555819272995

    Searching for: 'fast database'
    ID: Redis | Score: 0.553134202957

    Searching for: 'programming language that uses $ (Dollar sign) as variable prefix'
    ID: PHP | Score: 0.623752713203
*/

foreach ($queries as $query) {
    // 7. Search documents
    echo "Searching for: '$query'\n";
    $results = $vectorDB->searchDocuments(new DocumentSearch(
        query: $query,
        topK: 1
    ));

    // 8. Display results
    foreach ($results as $result) {
        echo sprintf(
            "ID: %s | Score: %s\nContent: %s\nMetadata: %s\n\n",
            $result->document->id,
            $result->score,
            $result->document->content,
            json_encode($result->document->meta)
        );
    }
}
// Filtered search examples
echo "-----\n";
echo "> Filtered search: programming category only\n";
$results = $vectorDB->searchDocuments(new DocumentSearch(
    query: 'a fast language',
    topK: 3,
    filters: [
        new Filter('category', FilterOp::Eq, 'programming'),
    ]
));
foreach ($results as $result) {
    echo sprintf(
        "ID: %s | Score: %s\nContent: %s\nMetadata: %s\n\n",
        $result->document->id,
        $result->score,
        $result->document->content,
        json_encode($result->document->meta)
    );
}

echo "-----\n";
echo "> Filtered search: database category only\n";
$results = $vectorDB->searchDocuments(new DocumentSearch(
    query: 'a fast language',
    topK: 3,
    filters: [
        new Filter('category', FilterOp::Eq, 'database'),
    ]
));
foreach ($results as $result) {
    echo sprintf(
        "ID: %s | Score: %s\nContent: %s\nMetadata: %s\n\n",
        $result->document->id,
        $result->score,
        $result->document->content,
        json_encode($result->document->meta)
    );
}

echo "-----\n";
echo "> Filtered search: languages created between 2000-2010 (range filter)\n";
$results = $vectorDB->searchDocuments(new DocumentSearch(
    query: 'programming language',
    topK: 3,
    filters: [
        new Filter('year', FilterOp::Range, new NumericRange(2000, 2010)),
    ]
));
foreach ($results as $result) {
    echo sprintf(
        "ID: %s | Score: %s\nContent: %s\nMetadata: %s\n\n",
        $result->document->id,
        $result->score,
        $result->document->content,
        json_encode($result->document->meta)
    );
}

echo "-----\n";
echo "> Filtered search: languages created before 2000 (lte filter)\n";
$results = $vectorDB->searchDocuments(new DocumentSearch(
    query: 'programming language',
    topK: 3,
    filters: [
        new Filter('year', FilterOp::Lte, 2000),
    ]
));
foreach ($results as $result) {
    echo sprintf(
        "ID: %s | Score: %s\nContent: %s\nMetadata: %s\n\n",
        $result->document->id,
        $result->score,
        $result->document->content,
        json_encode($result->document->meta)
    );
}

echo "Done!\n";
