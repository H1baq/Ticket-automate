<?php

declare(strict_types=1);

namespace Tests\Browser;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class ExampleTest extends DuskTestCase
{
    public function test_home_page_loads_in_a_real_browser(): void
    {
        $this->browse(function (Browser $browser): void {
            $browser->visit('/')
                ->assertTitle(config('app.name'))
                ->assertSee('Help desk ticketing and management.');
        });
    }
}
