<?php
namespace Tests\Fixtures;

class ResourceLeaker
{
    private array $leakedHandles = [];

    /**
     * Intentionally retains an open stream handle across requests
     */
    public function openUnclosedStream(): void
    {
        $this->leakedHandles[] = fopen('php://temp', 'r+');
    }
}
