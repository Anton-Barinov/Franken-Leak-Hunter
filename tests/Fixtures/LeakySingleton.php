<?php
namespace Tests\Fixtures;

class LeakySingleton
{
    private static ?LeakySingleton $instance = null;
    private array $userSessionData = [];

    private function __construct() {}

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function setUserData(string $key, $value): void
    {
        $this->userSessionData[$key] = $value;
    }

    public function getUserData(string $key)
    {
        return $this->userSessionData[$key] ?? null;
    }
}
