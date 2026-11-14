<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;

class ConfigurationTest extends TestCase
{
    public function test_application_uses_the_nairobi_timezone(): void
    {
        $this->assertSame('Africa/Nairobi', config('app.timezone'));
        $this->assertSame('Africa/Nairobi', now()->timezoneName);
    }

    public function test_sessions_expire_after_thirty_minutes(): void
    {
        $this->assertSame(30, config('session.lifetime'));
    }
}
