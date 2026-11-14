<?php

declare(strict_types=1);

namespace Tests\Feature\Ui;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Tests\TestCase;

class DevUiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        // Changing the environment turns CSRF checks back on; these tests are about the environment guard.
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_gallery_is_available_in_the_local_environment(): void
    {
        $this->app['env'] = 'local';

        $this->get('/dev/ui')
            ->assertOk()
            ->assertSee('Component gallery')
            ->assertSee('HD-2026-00142')
            ->assertSee('Overdue')
            ->assertSee('Ticket history')
            ->assertSee('Enter an email address like name@example.com.');
    }

    public function test_gallery_hides_in_every_other_environment(): void
    {
        foreach (['testing', 'staging', 'production'] as $environment) {
            $this->app['env'] = $environment;

            $this->get('/dev/ui')->assertNotFound();
            $this->post('/dev/ui')->assertNotFound();
        }
    }

    public function test_gallery_can_preview_each_role_menu(): void
    {
        $this->app['env'] = 'local';

        $this->get('/dev/ui?role=user')->assertOk()->assertSee('My tickets')->assertDontSee('Assignment queue');
        $this->get('/dev/ui?role=agent')->assertOk()->assertSee('My queue')->assertDontSee('Assignment queue');
        $this->get('/dev/ui?role=admin')->assertOk()->assertSee('Assignment queue')->assertSee('SLA settings');
    }

    public function test_an_unknown_role_falls_back_to_the_admin_menu(): void
    {
        $this->app['env'] = 'local';

        $this->get('/dev/ui?role=superuser')->assertOk()->assertSee('Assignment queue');
    }

    public function test_sort_links_keep_the_filters_and_reset_the_page(): void
    {
        $this->app['env'] = 'local';

        $response = $this->get('/dev/ui?status=open&page=2');

        $response->assertOk();
        $this->assertStringContainsString('status=open', $response->getContent());
        $this->assertStringContainsString('sort=number', $response->getContent());
        $this->assertStringNotContainsString('sort=number&amp;direction=asc&amp;page=2', $response->getContent());
    }

    public function test_submitting_flashes_a_success_toast(): void
    {
        $this->app['env'] = 'local';

        $this->post('/dev/ui')
            ->assertRedirect('/dev/ui')
            ->assertSessionHas('toast.type', 'success');

        $this->followingRedirects()->post('/dev/ui')->assertSee('data-toast-type="success"', false);
    }
}
