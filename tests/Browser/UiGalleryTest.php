<?php

declare(strict_types=1);

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\DuskTestCase;

class UiGalleryTest extends DuskTestCase
{
    public function test_copy_button_copies_the_ticket_number_and_says_so(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->visit('/dev/ui')
                ->click('#badges .copy-button')
                ->waitFor('.toast-success')
                ->assertSeeIn('.toast-success', 'Copied HD-2026-00142');
        });
    }

    public function test_toasts_appear_and_errors_stay_until_dismissed(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->visit('/dev/ui')
                ->click('[data-show-toast="error"]')
                ->waitFor('.toast-error')
                ->assertSeeIn('.toast-error', 'That file is larger than 5 MB.')
                ->assertAttribute('.toast-error', 'role', 'alert')
                ->pause(7000)
                ->assertPresent('.toast-error')
                ->click('.toast-error .btn-close')
                ->waitUntilMissing('.toast-error');
        });
    }

    public function test_confirm_modal_takes_its_message_from_the_trigger_and_focuses_cancel(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->visit('/dev/ui')
                ->click('[data-bs-target="#confirm-deactivate"]')
                ->waitFor('#confirm-deactivate.show')
                ->assertSeeIn('#confirm-deactivate [data-confirm-message]', 'Deactivate Wanjiku Kamau?')
                ->pause(400);

            $this->assertTrue($browser->script('return document.activeElement.hasAttribute("data-confirm-cancel");')[0]);

            $browser->keys('#confirm-deactivate', '{escape}')
                ->waitUntilMissing('#confirm-deactivate.show');
        });
    }

    public function test_slash_focuses_search_and_letters_are_ignored_while_typing(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->visit('/dev/ui');
            $url = $browser->driver->getCurrentURL();

            $browser->keys('', '/')
                ->assertFocused('#topbar-search')
                ->keys('#topbar-search', 'n', 'g', 'd')
                ->assertInputValue('#topbar-search', 'ngd');

            $this->assertSame($url, $browser->driver->getCurrentURL());
        });
    }

    public function test_submit_button_locks_with_a_spinner_then_a_toast_confirms(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->visit('/dev/ui');

            // The server holds the request for two seconds. Clicking and reading the button inside one
            // script shows the locked state of the original page, before the browser navigates away.
            $state = $browser->driver->executeAsyncScript(
                <<<'JS'
                const done = arguments[arguments.length - 1];
                const button = document.getElementById('slow-submit');
                button.click();
                setTimeout(() => done({
                    disabled: button.disabled,
                    busy: button.getAttribute('aria-busy'),
                    spinner: button.querySelector('.spinner-border') !== null,
                    text: button.textContent.trim(),
                }), 200);
                JS
            );

            $this->assertTrue($state['disabled']);
            $this->assertSame('true', $state['busy']);
            $this->assertTrue($state['spinner']);
            $this->assertStringContainsString('Creating ticket…', $state['text']);

            $browser->waitFor('.toast-success', 10)
                ->assertSeeIn('.toast-success', 'Ticket HD-2026-00142 created.');
        });
    }

    public function test_character_counter_follows_typing(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->visit('/dev/ui')
                ->assertSeeIn('[data-char-count-for="field-description"]', '0 / 5000')
                ->type('#field-description', 'Hello')
                ->assertSeeIn('[data-char-count-for="field-description"]', '5 / 5000');
        });
    }

    public function test_sorting_a_column_puts_the_sort_in_the_address(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->visit('/dev/ui')
                ->click('#table th a')
                ->assertQueryStringHas('sort', 'number')
                ->assertQueryStringHas('direction', 'asc');
        });
    }

    /**
     * @return array<string, array{string}>
     */
    public static function pages(): array
    {
        return [
            'welcome' => ['/'],
            'gallery as staff' => ['/dev/ui?role=user'],
            'gallery as admin' => ['/dev/ui?role=admin'],
            'error page' => ['/no/such/page'],
        ];
    }

    #[DataProvider('pages')]
    public function test_pages_fit_a_360px_wide_phone_without_sideways_scrolling(string $path): void
    {
        $this->browse(function (Browser $browser) use ($path): void {
            $this->emulatePhone($browser);
            $browser->visit($path);

            $this->assertSame(360, $browser->script('return window.innerWidth;')[0]);
            $this->assertTrue(
                $browser->script('return document.documentElement.scrollWidth <= document.documentElement.clientWidth;')[0],
                "{$path} scrolls sideways on a 360px screen",
            );
        });
    }

    public function test_menu_opens_from_the_topbar_on_a_phone_and_closes_again(): void
    {
        $this->browse(function (Browser $browser): void {
            $this->emulatePhone($browser);
            $browser->visit('/dev/ui?role=user')
                ->assertMissing('#sidebar.show')
                ->click('[aria-label="Open menu"]')
                ->waitFor('#sidebar.show')
                ->assertSeeIn('#sidebar', 'My tickets')
                ->click('#sidebar [aria-label="Close menu"]')
                ->waitUntilMissing('#sidebar.show');
        });
    }
}
