<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * TC01: registration.
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        config(['hdtms.auth.self_registration' => true, 'hdtms.auth.allowed_email_domains' => []]);
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function validInput(array $overrides = []): array
    {
        return [
            'name' => 'Wanjiku Kamau',
            'email' => 'wanjiku.kamau@example.test',
            'department' => 'Finance',
            'password' => 'a-good-password',
            'password_confirmation' => 'a-good-password',
            ...$overrides,
        ];
    }

    public function test_the_registration_page_is_available_when_the_setting_is_on(): void
    {
        $this->get('/register')->assertOk()->assertSee('Create an account')->assertSee('name="password_confirmation"', false);
        $this->get('/login')->assertSee('Create an account');
    }

    public function test_registration_is_closed_by_default_and_leaves_no_trace(): void
    {
        config(['hdtms.auth.self_registration' => false]);

        $this->get('/register')->assertNotFound();
        $this->post('/register', $this->validInput())->assertNotFound();
        $this->get('/login')->assertDontSee('Create an account');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_tc01_a_visitor_registers_and_is_signed_in_as_staff(): void
    {
        $this->post('/register', $this->validInput())->assertRedirect('/dashboard');

        $user = User::query()->where('email', 'wanjiku.kamau@example.test')->sole();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('Wanjiku Kamau', $user->name);
        $this->assertSame('Finance', $user->department);
        $this->assertSame(UserRole::User, $user->role);
        $this->assertTrue($user->is_active);
        $this->assertTrue(Hash::check('a-good-password', $user->password));
        $this->assertNotSame('a-good-password', $user->password);
        $this->get('/dashboard')->assertOk()->assertSee('Your account is ready.');
    }

    public function test_the_department_is_optional(): void
    {
        $this->post('/register', $this->validInput(['department' => '']))->assertRedirect('/dashboard');

        $this->assertNull(User::query()->sole()->department);
    }

    public function test_the_email_is_stored_in_lower_case(): void
    {
        $this->post('/register', $this->validInput(['email' => '  Wanjiku.Kamau@Example.TEST ']))->assertRedirect('/dashboard');

        $this->assertSame('wanjiku.kamau@example.test', User::query()->sole()->email);
    }

    public function test_nobody_can_register_themselves_as_an_administrator(): void
    {
        $this->post('/register', $this->validInput(['role' => 'admin', 'is_active' => '0', 'failed_logins' => '9', 'locked_until' => '2099-01-01']))
            ->assertRedirect('/dashboard');

        $user = User::query()->sole();
        $this->assertSame(UserRole::User, $user->role);
        $this->assertTrue($user->is_active);
        $this->assertSame(0, $user->failed_logins);
        $this->assertNull($user->locked_until);
    }

    /**
     * @return array<string, array{array<string, string>, string}>
     */
    public static function invalidInput(): array
    {
        return [
            'no name' => [['name' => ''], 'name'],
            'name too long' => [['name' => str_repeat('a', 256)], 'name'],
            'no email' => [['email' => ''], 'email'],
            'not an email' => [['email' => 'wanjiku'], 'email'],
            'department too long' => [['department' => str_repeat('a', 101)], 'department'],
            'password too short' => [['password' => 'short12', 'password_confirmation' => 'short12'], 'password'],
            'password not confirmed' => [['password_confirmation' => 'something-else'], 'password'],
            'no password' => [['password' => '', 'password_confirmation' => ''], 'password'],
            'password longer than bcrypt reads' => [['password' => str_repeat('a', 73), 'password_confirmation' => str_repeat('a', 73)], 'password'],
        ];
    }

    /**
     * @param  array<string, string>  $overrides
     */
    #[DataProvider('invalidInput')]
    public function test_invalid_input_is_refused_and_creates_nothing(array $overrides, string $field): void
    {
        $this->from('/register')->post('/register', $this->validInput($overrides))
            ->assertRedirect('/register')
            ->assertSessionHasErrors($field);

        $this->assertDatabaseCount('users', 0);
        $this->assertGuest();
    }

    public function test_a_password_of_exactly_eight_characters_is_accepted(): void
    {
        $this->post('/register', $this->validInput(['password' => 'abcdefgh', 'password_confirmation' => 'abcdefgh']))->assertRedirect('/dashboard');
    }

    public function test_an_email_that_already_has_an_account_is_refused_whatever_its_case(): void
    {
        User::factory()->create(['email' => 'wanjiku.kamau@example.test']);

        $this->from('/register')->post('/register', $this->validInput(['email' => 'WANJIKU.KAMAU@example.test']))
            ->assertSessionHasErrors(['email' => 'An account with this email address already exists.']);

        $this->assertDatabaseCount('users', 1);
    }

    public function test_only_the_allowed_email_domains_may_register(): void
    {
        config(['hdtms.auth.allowed_email_domains' => ['example.ac.ke', 'staff.example.ac.ke']]);

        foreach (['someone@gmail.com', 'someone@notexample.ac.ke', 'someone@mail.example.ac.ke', 'someone@example.ac.ke.evil.test'] as $email) {
            $this->from('/register')->post('/register', $this->validInput(['email' => $email]))->assertSessionHasErrors('email');
        }
        $this->assertDatabaseCount('users', 0);

        $this->post('/register', $this->validInput(['email' => 'Someone@EXAMPLE.ac.ke']))->assertRedirect('/dashboard');
        $this->assertAuthenticated();
    }

    public function test_the_page_names_the_allowed_domains(): void
    {
        config(['hdtms.auth.allowed_email_domains' => ['example.ac.ke']]);

        $this->get('/register')->assertSee('ending in @example.ac.ke');
    }

    public function test_the_domain_message_tells_the_person_what_to_do(): void
    {
        config(['hdtms.auth.allowed_email_domains' => ['example.ac.ke']]);

        $this->from('/register')->post('/register', $this->validInput(['email' => 'someone@gmail.com']));

        $this->get('/register')->assertSee('Use your work email address. It must end with @example.ac.ke.');
    }

    public function test_the_form_keeps_what_was_typed_except_the_passwords(): void
    {
        $this->from('/register')->post('/register', $this->validInput(['email' => 'nonsense', 'password' => 'secret-pass-1', 'password_confirmation' => 'secret-pass-2']));

        $this->get('/register')
            ->assertSee('value="Wanjiku Kamau"', false)
            ->assertSee('value="Finance"', false)
            ->assertDontSee('secret-pass-1', false)
            ->assertDontSee('secret-pass-2', false);
    }

    public function test_registration_is_rate_limited(): void
    {
        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $this->post('/register', $this->validInput(['email' => 'nonsense']))->assertStatus(302);
        }

        $this->post('/register', $this->validInput())->assertStatus(429);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_signed_in_users_cannot_register_again(): void
    {
        $this->actingAs(User::factory()->create())->get('/register')->assertRedirect('/dashboard');
    }
}
