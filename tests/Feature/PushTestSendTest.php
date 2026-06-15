<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\TestPushNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PushTestSendTest extends TestCase
{
    use RefreshDatabase;

    /**
     * ADMIN_EMAILS is set to admin@example.com in phpunit.xml.
     */
    private function admin(): User
    {
        return User::factory()->create(['email' => 'admin@example.com']);
    }

    #[Test]
    public function test_guest_cannot_send_test_push(): void
    {
        $target = User::factory()->create();

        $this->postJson('/api/v1/push/test', [
            'user_id' => $target->id,
            'body' => 'hello',
        ])->assertStatus(401);
    }

    #[Test]
    public function test_non_admin_cannot_send_test_push(): void
    {
        Notification::fake();
        $target = User::factory()->create();

        $this->actingAs(User::factory()->create())
            ->postJson('/api/v1/push/test', [
                'user_id' => $target->id,
                'body' => 'hello',
            ])
            ->assertStatus(403);

        Notification::assertNothingSent();
    }

    #[Test]
    public function test_admin_sends_test_push_to_target(): void
    {
        Notification::fake();
        $target = User::factory()->create();

        $this->actingAs($this->admin())
            ->postJson('/api/v1/push/test', [
                'user_id' => $target->id,
                'title' => 'Heads up',
                'body' => 'New order at table 4',
            ])
            ->assertOk()
            ->assertJsonPath('subscriptions', 0);

        Notification::assertSentTo(
            $target,
            TestPushNotification::class,
            function (TestPushNotification $notification): bool {
                return $notification->title === 'Heads up'
                    && $notification->body === 'New order at table 4';
            },
        );
    }

    #[Test]
    public function test_send_validates_body_and_user(): void
    {
        $this->actingAs($this->admin())
            ->postJson('/api/v1/push/test', ['user_id' => 999999])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['user_id', 'body']);
    }
}
