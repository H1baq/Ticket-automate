<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RoleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        // Stand-in for the sign-in page so these tests only exercise the middleware.
        Route::middleware('web')->get('/login', fn (): string => 'sign in')->name('login');

        Route::middleware(['web', 'auth', 'active', 'role:user'])->get('/_test/staff', fn (): string => 'staff area');
        Route::middleware(['web', 'auth', 'active', 'role:agent'])->get('/_test/agent', fn (): string => 'agent area');
        Route::middleware(['web', 'auth', 'active', 'role:admin'])->get('/_test/admin', fn (): string => 'admin area');
        Route::middleware(['web', 'auth', 'role:agent,admin'])->get('/_test/support', fn (): string => 'support area');
        Route::middleware(['web', 'auth', 'role:superuser'])->get('/_test/typo', fn (): string => 'never');

        Route::getRoutes()->refreshNameLookups();
    }

    /**
     * @return array<string, array{UserRole, string, int}>
     */
    public static function accessMatrix(): array
    {
        $matrix = [];
        $areas = ['user' => 'staff', 'agent' => 'agent', 'admin' => 'admin'];

        foreach (UserRole::cases() as $role) {
            foreach ($areas as $owner => $path) {
                $matrix["{$role->value} on the {$path} area"] = [$role, "/_test/{$path}", $role->value === $owner ? 200 : 403];
            }
        }

        return $matrix;
    }

    #[DataProvider('accessMatrix')]
    public function test_each_role_reaches_only_its_own_area(UserRole $role, string $path, int $expected): void
    {
        $user = User::factory()->create();
        $user->role = $role;
        $user->save();

        $this->actingAs($user)->get($path)->assertStatus($expected);
    }

    public function test_guests_are_sent_to_the_sign_in_page(): void
    {
        $this->get('/_test/admin')->assertRedirect(route('login'));
    }

    public function test_a_route_can_allow_several_roles(): void
    {
        $this->actingAs(User::factory()->agent()->create())->get('/_test/support')->assertOk();
        $this->actingAs(User::factory()->admin()->create())->get('/_test/support')->assertOk();
        $this->actingAs(User::factory()->create())->get('/_test/support')->assertForbidden();
    }

    public function test_an_unknown_role_in_a_route_is_a_developer_error_not_a_silent_deny(): void
    {
        $this->withoutExceptionHandling();
        $this->expectException(LogicException::class);

        $this->actingAs(User::factory()->admin()->create())->get('/_test/typo');
    }

    public function test_a_deactivated_user_is_signed_out_on_the_next_request(): void
    {
        $user = User::factory()->inactive()->create();

        $this->actingAs($user)
            ->get('/_test/staff')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_users_who_are_signed_in_are_kept_off_guest_pages(): void
    {
        Route::middleware(['web', 'guest'])->get('/_test/guest-only', fn (): string => 'guests');

        $this->actingAs(User::factory()->create())->get('/_test/guest-only')->assertRedirect('/dashboard');
    }

    public function test_registration_routes_answer_404_while_the_setting_is_off(): void
    {
        Route::middleware(['web', 'registration'])->get('/_test/register', fn (): string => 'open');

        config(['hdtms.auth.self_registration' => false]);
        $this->get('/_test/register')->assertNotFound();

        config(['hdtms.auth.self_registration' => true]);
        $this->get('/_test/register')->assertOk();
    }
}
