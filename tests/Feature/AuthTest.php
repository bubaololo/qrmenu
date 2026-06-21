<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    private string $xsrfToken = '';

    /**
     * Simulate the real SPA flow: GET /sanctum/csrf-cookie, then use the
     * XSRF-TOKEN cookie as a header in subsequent requests.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $response = $this->get('/sanctum/csrf-cookie', ['Referer' => 'http://localhost']);

        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === 'XSRF-TOKEN') {
                $this->xsrfToken = urldecode($cookie->getValue());
            }
        }
    }

    /**
     * @param  array<string, mixed>  $headers
     * @return array<string, mixed>
     */
    private function spaHeaders(array $headers = []): array
    {
        return array_merge([
            'Accept' => 'application/json',
            'Referer' => 'http://localhost',
            'X-XSRF-TOKEN' => $this->xsrfToken,
        ], $headers);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function spaPost(string $uri, array $data = []): TestResponse
    {
        return $this->post($uri, $data, $this->spaHeaders());
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function spaPut(string $uri, array $data = []): TestResponse
    {
        return $this->put($uri, $data, $this->spaHeaders());
    }

    private function spaGet(string $uri): TestResponse
    {
        return $this->get($uri, $this->spaHeaders());
    }

    /**
     * Session regeneration (login/logout) issues a new CSRF token.
     * Re-read it from the response cookies so subsequent requests use the fresh one.
     */
    private function refreshXsrfToken(TestResponse $response): void
    {
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === 'XSRF-TOKEN') {
                $this->xsrfToken = urldecode($cookie->getValue());
            }
        }
    }

    #[Test]
    public function test_register_creates_user_and_starts_session(): void
    {
        $response = $this->spaPost('/api/v1/auth/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('user.name', 'Test User')
            ->assertJsonPath('user.email', 'test@example.com');

        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
        $this->assertAuthenticated();
    }

    #[Test]
    public function test_register_validates_unique_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->spaPost('/api/v1/auth/register', [
            'name' => 'Test',
            'email' => 'taken@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    #[Test]
    public function test_register_requires_password_confirmation(): void
    {
        $this->spaPost('/api/v1/auth/register', [
            'name' => 'Test',
            'email' => 'test@example.com',
            'password' => 'password123',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    #[Test]
    public function test_login_with_valid_credentials(): void
    {
        User::factory()->create([
            'email' => 'user@example.com',
            'password' => bcrypt('secret123'),
        ]);

        $this->spaPost('/api/v1/auth/login', [
            'email' => 'user@example.com',
            'password' => 'secret123',
        ])->assertStatus(200)
            ->assertJsonPath('user.email', 'user@example.com');

        $this->assertAuthenticated();
    }

    #[Test]
    public function test_login_with_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'user@example.com',
            'password' => bcrypt('secret123'),
        ]);

        $this->spaPost('/api/v1/auth/login', [
            'email' => 'user@example.com',
            'password' => 'wrong',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    #[Test]
    public function test_logout_returns_204(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->spaPost('/api/v1/auth/logout')
            ->assertStatus(204);
    }

    #[Test]
    public function test_user_returns_authenticated_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->spaGet('/api/v1/auth/user')
            ->assertStatus(200)
            ->assertJsonPath('user.id', $user->id);
    }

    #[Test]
    public function test_user_returns_401_when_unauthenticated(): void
    {
        $this->getJson('/api/v1/auth/user')->assertStatus(401);
    }

    #[Test]
    public function test_change_password(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('old-password'),
        ]);

        $this->actingAs($user)
            ->spaPut('/api/v1/auth/user/password', [
                'current_password' => 'old-password',
                'password' => 'new-password123',
                'password_confirmation' => 'new-password123',
            ])->assertStatus(204);
    }

    #[Test]
    public function test_change_password_rejects_wrong_current(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('old-password'),
        ]);

        $this->actingAs($user)
            ->spaPut('/api/v1/auth/user/password', [
                'current_password' => 'wrong',
                'password' => 'new-password123',
                'password_confirmation' => 'new-password123',
            ])->assertStatus(422)
            ->assertJsonValidationErrors('current_password');
    }

    #[Test]
    public function test_forgot_password_sends_reset_link(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'reset@example.com']);

        $this->spaPost('/api/v1/auth/forgot-password', [
            'email' => 'reset@example.com',
        ])->assertStatus(200);

        Notification::assertSentTo($user, ResetPassword::class);
    }

    #[Test]
    public function test_reset_link_points_at_the_spa(): void
    {
        Notification::fake();
        config(['app.frontend_url' => 'https://admin.example.test']);

        $user = User::factory()->create(['email' => 'reset@example.com']);

        $this->spaPost('/api/v1/auth/forgot-password', [
            'email' => 'reset@example.com',
        ])->assertStatus(200);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            $url = $notification->toMail($user)->actionUrl;

            return str_starts_with($url, 'https://admin.example.test/reset-password?token=')
                && str_contains($url, 'email='.urlencode($user->email));
        });
    }

    #[Test]
    public function test_reset_password_updates_the_password(): void
    {
        $user = User::factory()->create([
            'email' => 'reset@example.com',
            'password' => bcrypt('old-password'),
        ]);

        $token = Password::broker()->createToken($user);

        $this->spaPost('/api/v1/auth/reset-password', [
            'token' => $token,
            'email' => 'reset@example.com',
            'password' => 'brand-new-pass1',
            'password_confirmation' => 'brand-new-pass1',
        ])->assertStatus(204);

        $this->assertTrue(Hash::check('brand-new-pass1', $user->fresh()->password));
    }

    #[Test]
    public function test_reset_password_rejects_invalid_token(): void
    {
        User::factory()->create(['email' => 'reset@example.com']);

        $this->spaPost('/api/v1/auth/reset-password', [
            'token' => 'totally-invalid-token',
            'email' => 'reset@example.com',
            'password' => 'brand-new-pass1',
            'password_confirmation' => 'brand-new-pass1',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }
}
