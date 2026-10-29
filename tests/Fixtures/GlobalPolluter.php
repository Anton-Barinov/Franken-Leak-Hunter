<?php
namespace Tests\Fixtures;

class GlobalPolluter
{
    public function setContext(string $userId): void
    {
        // Intentional global pollution
        $GLOBALS['current_user_id'] = $userId;
    }
}
