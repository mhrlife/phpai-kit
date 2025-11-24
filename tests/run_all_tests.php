#!/usr/bin/env php
<?php

declare(strict_types=1);

echo "========================================\n";
echo "Running All Tests\n";
echo "========================================\n\n";

$testFiles = [
    'TypeMapperTest.php',
    'SchemaGeneratorTest.php',
    'ToolDefinitionTest.php',
    'ToolRegistryTest.php',
    'ToolExecutorTest.php',
    'OutputParserTest.php',
];

$allPassed = true;

foreach ($testFiles as $testFile) {
    $path = __DIR__ . '/' . $testFile;
    if (file_exists($path)) {
        echo "\n";
        ob_start();
        $exitCode = 0;
        passthru("php $path", $exitCode);
        $output = ob_get_clean();
        echo $output;

        if ($exitCode !== 0) {
            $allPassed = false;
        }
    } else {
        echo "SKIP: $testFile (not found)\n";
    }
}

echo "\n";
echo "========================================\n";
if ($allPassed) {
    echo "ALL TESTS PASSED!\n";
    echo "========================================\n";
    exit(0);
} else {
    echo "SOME TESTS FAILED!\n";
    echo "========================================\n";
    exit(1);
}
