<?php

declare(strict_types=1);

namespace Tests\Feature\Ui;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config(['app.debug' => false]);
    }

    /**
     * @return array<string, array{int, string}>
     */
    public static function errors(): array
    {
        return [
            'forbidden' => [403, "You don't have access to this page"],
            'not found' => [404, "We can't find that page"],
            'page expired' => [419, 'This page has expired'],
            'too many requests' => [429, 'Too many attempts'],
            'server error' => [500, 'Something went wrong on our side'],
        ];
    }

    #[DataProvider('errors')]
    public function test_each_error_page_is_friendly_and_offers_a_way_out(int $status, string $heading): void
    {
        Route::get('/_error/'.$status, function () use ($status): never {
            if ($status === 500) {
                throw new RuntimeException('Secret internal detail');
            }

            abort($status);
        });

        $response = $this->get('/_error/'.$status);

        $response->assertStatus($status)
            ->assertSee(htmlspecialchars($heading, ENT_QUOTES), false)
            ->assertSee('Error '.$status)
            ->assertSee('Go to the home page')
            ->assertDontSee('Secret internal detail')
            ->assertDontSee('Whoops');
    }

    public function test_an_unknown_address_shows_the_404_page(): void
    {
        $this->get('/no/such/page')->assertNotFound()->assertSee('Error 404');
    }

    public function test_error_pages_do_not_read_the_session(): void
    {
        Route::get('/_error/session', fn () => abort(500));

        $this->withSession(['toast' => ['type' => 'success', 'message' => 'Should not appear here']])
            ->get('/_error/session')
            ->assertStatus(500)
            ->assertDontSee('Should not appear here');
    }
}
