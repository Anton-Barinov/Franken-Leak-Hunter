<?php
namespace Tests\Fixtures;

class CleanService
{
    private static array $cache = [];

    public function fetch(string $key): ?string
    {
        return self::$cache[$key] ?? null;
    }

    public function set(string $key, string $value): void
    {
        self::$cache[$key] = $value;
    }

    /**
     * Proper reset method to prevent worker state pollution
     */
    public function reset(): void
    {
        self::$cache = [];
    }
}
