<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PushSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_guest_cannot_subscribe(): void
    {
        $this->postJson('/api/v1/push/subscribe', [
            'endpoint' => 'https://push.example.com/abc',
            'key' => 'p256dh-key',
            'token' => 'auth-token',
        ])->assertStatus(401);
    }

    #[Test]
    public function test_subscribe_persists_subscription_for_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/v1/push/subscribe', [
                'endpoint' => 'https://push.example.com/abc',
                'key' => 'p256dh-key',
                'token' => 'auth-token',
                'encoding' => 'aesgcm',
            ])
            ->assertNoContent();

        $this->assertDatabaseHas('push_subscriptions', [
            'subscribable_id' => $user->id,
            'subscribable_type' => $user->getMorphClass(),
            'endpoint' => 'https://push.example.com/abc',
            'public_key' => 'p256dh-key',
            'auth_token' => 'auth-token',
            'content_encoding' => 'aesgcm',
        ]);
    }

    #[Test]
    public function test_subscribe_requires_endpoint_key_and_token(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/v1/push/subscribe', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['endpoint', 'key', 'token']);
    }

    #[Test]
    public function test_unsubscribe_removes_subscription(): void
    {
        $user = User::factory()->create();
        $user->updatePushSubscription('https://push.example.com/abc', 'p256dh-key', 'auth-token');

        $this->actingAs($user)
            ->deleteJson('/api/v1/push/subscribe', ['endpoint' => 'https://push.example.com/abc'])
            ->assertNoContent();

        $this->assertDatabaseMissing('push_subscriptions', [
            'endpoint' => 'https://push.example.com/abc',
        ]);
    }
}
