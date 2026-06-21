<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\User;
use App\Notifications\ConfirmationCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ConfirmationTest extends TestCase
{
    use RefreshDatabase;

    private string $xsrfToken = '';

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

    private function spaGet(string $uri): TestResponse
    {
        return $this->get($uri, $this->spaHeaders());
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function spaPost(string $uri, array $data = []): TestResponse
    {
        return $this->post($uri, $data, $this->spaHeaders());
    }

    private function spaDelete(string $uri): TestResponse
    {
        return $this->delete($uri, [], $this->spaHeaders());
    }

    #[Test]
    public function test_methods_for_a_password_user(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret123')]);

        $keys = collect(
            $this->actingAs($user)->spaGet('/api/v1/auth/confirm/methods')->assertOk()->json('methods')
        )->pluck('key')->all();

        $this->assertEqualsCanonicalizing(['password', 'email_code'], $keys);
    }

    #[Test]
    public function test_methods_for_an_oauth_user_are_email_only(): void
    {
        $user = User::factory()->create(['password' => null]);

        $keys = collect(
            $this->actingAs($user)->spaGet('/api/v1/auth/confirm/methods')->assertOk()->json('methods')
        )->pluck('key')->all();

        $this->assertEqualsCanonicalizing(['email_code'], $keys);
    }

    #[Test]
    public function test_confirm_with_correct_password_marks_the_session(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret123')]);

        $this->actingAs($user)
            ->spaPost('/api/v1/auth/confirm', ['method' => 'password', 'value' => 'secret123'])
            ->assertStatus(204)
            ->assertSessionHas('auth.password_confirmed_at');
    }

    #[Test]
    public function test_confirm_with_wrong_password_is_rejected(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret123')]);

        $this->actingAs($user)
            ->spaPost('/api/v1/auth/confirm', ['method' => 'password', 'value' => 'nope'])
            ->assertStatus(422)
            ->assertSessionMissing('auth.password_confirmed_at');
    }

    #[Test]
    public function test_email_code_send_dispatches_the_notification(): void
    {
        Notification::fake();
        $user = User::factory()->create(['password' => null]);

        $this->actingAs($user)
            ->spaPost('/api/v1/auth/confirm/send', ['method' => 'email_code'])
            ->assertStatus(202);

        Notification::assertSentTo($user, ConfirmationCode::class);
    }

    #[Test]
    public function test_email_code_confirm_with_a_valid_code(): void
    {
        $user = User::factory()->create(['password' => null]);
        Cache::put("confirm-code:{$user->id}", Hash::make('123456'), 600);

        $this->actingAs($user)
            ->spaPost('/api/v1/auth/confirm', ['method' => 'email_code', 'value' => '123456'])
            ->assertStatus(204)
            ->assertSessionHas('auth.password_confirmed_at');
    }

    #[Test]
    public function test_restaurant_delete_is_gated_by_confirmation(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret123')]);
        // Creator → RestaurantObserver attaches them as Owner.
        $restaurant = Restaurant::factory()->create(['created_by_user_id' => $user->id]);

        // No recent confirmation → 423.
        $this->actingAs($user)
            ->spaDelete("/api/v1/restaurants/{$restaurant->id}")
            ->assertStatus(423);

        // With a fresh confirmation marker → deletes.
        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => now()->unix()])
            ->spaDelete("/api/v1/restaurants/{$restaurant->id}")
            ->assertStatus(204);

        $this->assertDatabaseMissing('restaurants', ['id' => $restaurant->id]);
    }
}
