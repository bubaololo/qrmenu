<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Covers the supporting endpoints for the push test harness: the public VAPID
 * key endpoint and the admin-only user list that feeds the recipient select.
 */
class PushHarnessTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['email' => 'admin@example.com']);
    }

    #[Test]
    public function test_vapid_public_key_is_public(): void
    {
        $this->getJson('/api/v1/push/vapid-public-key')
            ->assertOk()
            ->assertJsonPath('public_key', config('webpush.vapid.public_key'));
    }

    #[Test]
    public function test_guest_cannot_list_users(): void
    {
        $this->getJson('/api/v1/users')->assertStatus(401);
    }

    #[Test]
    public function test_non_admin_cannot_list_users(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/api/v1/users')
            ->assertStatus(403);
    }

    #[Test]
    public function test_admin_lists_users_with_id_and_email(): void
    {
        $admin = $this->admin();
        $other = User::factory()->create(['email' => 'someone@example.com']);

        $this->actingAs($admin)
            ->getJson('/api/v1/users')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['id' => $other->id, 'email' => 'someone@example.com'])
            ->assertJsonStructure(['data' => [['id', 'email']]]);
    }
}
