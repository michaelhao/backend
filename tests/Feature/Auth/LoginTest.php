<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get(route('login'));

        $response->assertStatus(200);
        $response->assertSee('登入');
    }

    public function test_authenticated_user_is_redirected_away_from_login(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('login'));

        $response->assertRedirect('/');
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('login'));
    }

    public function test_user_can_login_with_correct_credentials(): void
    {
        $user = User::factory()->create();

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
        $response->assertSessionHas('auth.permissions');
    }

    public function test_user_cannot_login_with_wrong_password(): void
    {
        $user = User::factory()->create();

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_validation_requires_email_and_password(): void
    {
        $response = $this->post(route('login'), []);

        $response->assertSessionHasErrors(['email', 'password']);
        $this->assertGuest();
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('logout'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_login_is_throttled_after_too_many_attempts(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login'), [
                'email' => $user->email,
                'password' => 'wrong-password',
            ]);
        }

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_throttle_key_is_case_insensitive_on_email(): void
    {
        $user = User::factory()->create(['email' => 'admin@example.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login'), [
                'email' => 'ADMIN@example.com',
                'password' => 'wrong-password',
            ]);
        }

        $response = $this->post(route('login'), [
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_successful_login_clears_throttle_counter(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 4; $i++) {
            $this->post(route('login'), [
                'email' => $user->email,
                'password' => 'wrong-password',
            ]);
        }

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);
        $this->assertAuthenticatedAs($user);

        $this->post(route('logout'));

        // 若計數未清除，這次會是第 6 次嘗試而被擋下
        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
    }
}
