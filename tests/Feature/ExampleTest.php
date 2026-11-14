<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_home_address_leads_a_visitor_to_the_sign_in_page(): void
    {
        $this->withoutVite();

        $this->get('/')->assertRedirect('/dashboard');
        $this->followingRedirects()->get('/')
            ->assertOk()
            ->assertSee('Sign in')
            ->assertSee(config('app.name'));
    }
}
