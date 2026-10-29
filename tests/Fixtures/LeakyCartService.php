<?php
namespace Tests\Fixtures;

class LeakyCartService
{
    /**
     * Intentional static leak: cart items accumulate across requests
     */
    public static array $items = [];

    public function addItem(int $id, string $name, float $price): void
    {
        self::$items[] = [
            'id'    => $id,
            'name'  => $name,
            'price' => $price,
            'ts'    => microtime(true),
        ];
    }

    public function getItems(): array
    {
        return self::$items;
    }
}
