<?php

namespace Tests\Feature;

use App\Enums\RestaurantUserRole;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BroadcastChannelAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The test suite defaults to the `null` broadcaster, which authorizes every
        // channel without running the callbacks. Force the pusher-protocol (reverb)
        // driver with dummy credentials so channel authorization is actually enforced;
        // the auth signature is a local HMAC, so no network call is made.
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => 'test-id',
            'broadcasting.connections.reverb.options.host' => '127.0.0.1',
            'broadcasting.connections.reverb.options.port' => 8080,
            'broadcasting.connections.reverb.options.scheme' => 'http',
            'broadcasting.connections.reverb.options.useTLS' => false,
        ]);

        // Channel callbacks are registered at boot on the default (null) driver.
        // Re-load them now so they attach to the reverb driver selected above.
        require base_path('routes/channels.php');
    }

    private function authorize(string $channel): TestResponse
    {
        return $this->postJson('/api/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => $channel,
        ]);
    }

    #[Test]
    public function unauthenticated_user_cannot_authorize_a_private_channel(): void
    {
        $this->authorize('private-restaurant.1')->assertStatus(401);
    }

    #[Test]
    public function member_can_authorize_their_restaurant_channels(): void
    {
        $restaurant = Restaurant::factory()->create();
        $user = User::factory()->create();
        $restaurant->users()->attach($user, ['role' => RestaurantUserRole::Owner->value]);

        $this->actingAs($user);
        $this->authorize("private-restaurant.{$restaurant->id}")->assertStatus(200);
        $this->authorize("private-restaurant-orders.{$restaurant->id}")->assertStatus(200);
    }

    #[Test]
    public function non_member_cannot_authorize_restaurant_channels(): void
    {
        $restaurant = Restaurant::factory()->create();
        $intruder = User::factory()->create();

        $this->actingAs($intruder);
        $this->authorize("private-restaurant.{$restaurant->id}")->assertStatus(403);
        $this->authorize("private-restaurant-orders.{$restaurant->id}")->assertStatus(403);
    }
}
