<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_via_api(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Fouad Test',
            'email' => 'fouad@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'device_name' => 'ios-app',
        ]);

        $response
            ->assertCreated()
            ->assertJsonStructure([
                'message',
                'token_type',
                'token',
                'user' => ['id', 'name', 'email'],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'fouad@example.com',
        ]);
    }

    public function test_user_can_login_via_api(): void
    {
        $user = User::factory()->create([
            'email' => 'fouad@example.com',
            'password' => 'Password123!',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'Password123!',
            'device_name' => 'android-app',
        ]);

        $response
            ->assertOk()
            ->assertJsonStructure([
                'message',
                'token_type',
                'token',
                'user' => ['id', 'name', 'email'],
            ]);
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'fouad@example.com',
            'password' => 'Password123!',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'fouad@example.com',
            'password' => 'WrongPassword123!',
            'device_name' => 'android-app',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_user_can_request_a_password_reset_link(): void
    {
        Notification::fake();
        config()->set('services.mobile_app.password_reset_url', 'moneytracker://reset-password');

        $user = User::factory()->create([
            'email' => 'fouad@example.com',
        ]);

        $response = $this->postJson('/api/forgot-password', [
            'email' => $user->email,
        ]);

        $response
            ->assertOk()
            ->assertJson([
                'message' => 'If an account with that email exists, we have emailed a password reset link.',
            ]);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification, array $channels) use ($user): bool {
            $mailMessage = $notification->toMail($user);

            $this->assertSame(['mail'], $channels);
            $this->assertStringStartsWith('moneytracker://reset-password?', $mailMessage->actionUrl);
            $this->assertStringContainsString('email='.rawurlencode($user->email), $mailMessage->actionUrl);
            $this->assertStringContainsString('token=', $mailMessage->actionUrl);

            return true;
        });

        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => $user->email,
        ]);
    }

    public function test_forgot_password_returns_generic_success_for_unknown_email(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/forgot-password', [
            'email' => 'missing@example.com',
        ]);

        $response
            ->assertOk()
            ->assertJson([
                'message' => 'If an account with that email exists, we have emailed a password reset link.',
            ]);

        Notification::assertNothingSent();
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => 'missing@example.com',
        ]);
    }

    public function test_user_can_reset_password_via_api(): void
    {
        $user = User::factory()->create([
            'email' => 'fouad@example.com',
            'password' => 'OldPassword123!',
        ]);

        $token = Password::broker()->createToken($user);

        $response = $this->postJson('/api/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response
            ->assertOk()
            ->assertJson([
                'message' => 'Your password has been reset.',
            ]);

        $user->refresh();

        $this->assertTrue(Hash::check('NewPassword123!', $user->password));
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => $user->email,
        ]);
    }

    public function test_reset_password_fails_with_invalid_token(): void
    {
        $user = User::factory()->create([
            'email' => 'fouad@example.com',
            'password' => 'OldPassword123!',
        ]);

        $response = $this->postJson('/api/reset-password', [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $user->refresh();

        $this->assertTrue(Hash::check('OldPassword123!', $user->password));
    }

    public function test_reset_password_page_can_be_opened_from_email_link(): void
    {
        $response = $this->get('/reset-password?token=sample-token&email=fouad@example.com');

        $response
            ->assertOk()
            ->assertSee('Reset password')
            ->assertSee('fouad@example.com')
            ->assertSee('sample-token', false);
    }

    public function test_user_can_reset_password_from_the_website_flow(): void
    {
        $user = User::factory()->create([
            'email' => 'fouad@example.com',
            'password' => 'OldPassword123!',
        ]);

        $token = Password::broker()->createToken($user);

        $response = $this->post('/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response
            ->assertRedirect('/reset-password?email='.rawurlencode($user->email).'&token='.$token)
            ->assertSessionHas('status', 'Your password has been reset.');

        $user->refresh();

        $this->assertTrue(Hash::check('NewPassword123!', $user->password));
    }
}
