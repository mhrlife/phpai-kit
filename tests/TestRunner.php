<?php

declare(strict_types=1);

namespace Mhrlife\PhpaiKit\Tests;

class TestRunner
{
    private int $passed = 0;
    private int $failed = 0;
    private array $failures = [];

    public function assert(bool $condition, string $message): void
    {
        if ($condition) {
            $this->passed++;
            echo ".";
        } else {
            $this->failed++;
            $this->failures[] = $message;
            echo "F";
        }
    }

    public function assertEquals(mixed $expected, mixed $actual, string $message): void
    {
        $this->assert($expected === $actual, "$message\nExpected: " . json_encode($expected) . "\nActual: " . json_encode($actual));
    }

    public function assertArrayEquals(array $expected, array $actual, string $message): void
    {
        $this->assert(
            json_encode($expected) === json_encode($actual),
            "$message\nExpected: " . json_encode($expected, JSON_PRETTY_PRINT) . "\nActual: " . json_encode($actual, JSON_PRETTY_PRINT)
        );
    }

    public function assertTrue(bool $condition, string $message): void
    {
        $this->assert($condition === true, $message);
    }

    public function assertFalse(bool $condition, string $message): void
    {
        $this->assert($condition === false, $message);
    }

    public function assertInstanceOf(string $class, mixed $object, string $message): void
    {
        $this->assert($object instanceof $class, $message);
    }

    public function assertThrows(callable $callback, string $exceptionClass, string $message): void
    {
        try {
            $callback();
            $this->assert(false, "$message - Expected exception {$exceptionClass} but none was thrown");
        } catch (\Throwable $e) {
            $this->assert($e instanceof $exceptionClass, "$message - Expected {$exceptionClass} but got " . get_class($e));
        }
    }

    public function report(): void
    {
        echo "\n\n";
        echo "======================================\n";
        echo "Test Results\n";
        echo "======================================\n";
        echo "Passed: {$this->passed}\n";
        echo "Failed: {$this->failed}\n";
        echo "Total:  " . ($this->passed + $this->failed) . "\n";

        if (!empty($this->failures)) {
            echo "\n";
            echo "Failures:\n";
            echo "======================================\n";
            foreach ($this->failures as $i => $failure) {
                echo ($i + 1) . ". $failure\n\n";
            }
        }

        echo "======================================\n";

        if ($this->failed > 0) {
            exit(1);
        }
    }
}
