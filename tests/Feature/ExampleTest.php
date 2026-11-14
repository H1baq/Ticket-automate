<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_home_page_shows_the_application_name(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee(config('app.name'));
    }
}
