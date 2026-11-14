<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * TC15: staff users and agents are refused on every administration page.
 */
class UserAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function adminRequests(): array
    {
        return [
            'list users' => ['get', '/admin/users'],
            'new user form' => ['get', '/admin/users/create'],
            'create a user' => ['post', '/admin/users'],
            'edit form' => ['get', '/admin/users/{target}/edit'],
            'save an edit' => ['put', '/admin/users/{target}'],
            'deactivate' => ['patch', '/admin/users/{target}/deactivate'],
            'reactivate' => ['patch', '/admin/users/{target}/reactivate'],
        ];
    }

    #[DataProvider('adminRequests')]
    public function test_tc15_a_staff_user_gets_403_on_every_admin_page(string $method, string $path): void
    {
        $target = User::factory()->create();

        $this->actingAs(User::factory()->create())
            ->call($method, str_replace('{target}', (string) $target->id, $path), ['name' => 'X', 'email' => 'x@example.test', 'role' => 'admin'])
            ->assertForbidden();
    }

    #[DataProvider('adminRequests')]
    public function test_an_agent_gets_403_on_every_admin_page(string $method, string $path): void
    {
        $target = User::factory()->create();

        $this->actingAs(User::factory()->agent()->create())
            ->call($method, str_replace('{target}', (string) $target->id, $path), ['name' => 'X', 'email' => 'x@example.test', 'role' => 'admin'])
            ->assertForbidden();
    }

    #[DataProvider('adminRequests')]
    public function test_a_visitor_who_is_not_signed_in_is_sent_to_sign_in(string $method, string $path): void
    {
        $target = User::factory()->create();

        $this->call($method, str_replace('{target}', (string) $target->id, $path))->assertRedirect('/login');
    }

    public function test_a_refused_request_changes_nothing(): void
    {
        $target = User::factory()->create();
        $staff = User::factory()->create();

        $this->actingAs($staff)->put("/admin/users/{$staff->id}", ['name' => 'Boss', 'role' => 'admin'])->assertForbidden();
        $this->actingAs($staff)->patch("/admin/users/{$target->id}/deactivate")->assertForbidden();
        $this->actingAs($staff)->post('/admin/users', ['name' => 'Mole', 'email' => 'mole@example.test', 'role' => 'admin'])->assertForbidden();

        $this->assertSame(UserRole::User, $staff->fresh()->role);
        $this->assertTrue($target->fresh()->is_active);
        $this->assertDatabaseMissing('users', ['email' => 'mole@example.test']);
    }

    public function test_staff_never_see_the_administration_menu(): void
    {
        $this->actingAs(User::factory()->create())->get('/dashboard')->assertOk()->assertDontSee('Users')->assertDontSee('/admin/users', false);
    }

    public function test_administrators_see_the_users_menu_item(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get('/dashboard')->assertOk()->assertSee('/admin/users', false);
    }

    public function test_a_deactivated_administrator_is_refused_and_signed_out(): void
    {
        $this->actingAs(User::factory()->admin()->inactive()->create())
            ->get('/admin/users')
            ->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_every_admin_route_is_guarded_by_sign_in_an_active_account_and_the_admin_role(): void
    {
        $adminRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn (\Illuminate\Routing\Route $route): bool => str_starts_with((string) $route->getName(), 'admin.'));

        $this->assertGreaterThanOrEqual(7, $adminRoutes->count());

        foreach ($adminRoutes as $route) {
            $middleware = $route->gatherMiddleware();

            foreach (['auth', 'active', 'role:admin'] as $required) {
                $this->assertContains($required, $middleware, "Route {$route->getName()} is missing the {$required} middleware.");
            }
        }
    }

    public function test_there_is_no_way_to_delete_a_user(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();

        $this->actingAs($admin)->delete("/admin/users/{$target->id}")->assertStatus(405);

        $this->assertModelExists($target);
    }

    public function test_an_unknown_user_is_a_404_for_an_administrator(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get('/admin/users/999999/edit')->assertNotFound();
    }
}
