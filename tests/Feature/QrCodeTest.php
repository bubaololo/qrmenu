<?php

namespace Tests\Feature;

use App\Models\Restaurant;
use App\Models\RestaurantUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class QrCodeTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_qr_endpoint_returns_url(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $restaurant = Restaurant::factory()->create(['created_by_user_id' => $user->id]);

        $response = $this->actingAs($user)
            ->get("/api/v1/restaurants/{$restaurant->id}/qr", ['Accept-Language' => ''])
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['qr_url']]);

        $qrUrl = $response->json('data.qr_url');
        $this->assertNotEmpty($qrUrl);
    }

    #[Test]
    public function test_qr_file_is_created_on_disk(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $restaurant = Restaurant::factory()->create(['created_by_user_id' => $user->id]);

        $this->actingAs($user)
            ->get("/api/v1/restaurants/{$restaurant->id}/qr", ['Accept-Language' => '']);

        Storage::disk('public')->assertExists("qrcodes/{$restaurant->id}.svg");
    }

    #[Test]
    public function test_qr_file_is_reused_on_second_call(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $restaurant = Restaurant::factory()->create(['created_by_user_id' => $user->id]);

        $url1 = $this->actingAs($user)
            ->get("/api/v1/restaurants/{$restaurant->id}/qr", ['Accept-Language' => ''])
            ->json('data.qr_url');

        $url2 = $this->actingAs($user)
            ->get("/api/v1/restaurants/{$restaurant->id}/qr", ['Accept-Language' => ''])
            ->json('data.qr_url');

        $this->assertEquals($url1, $url2);
    }

    #[Test]
    public function test_unauthenticated_returns_401(): void
    {
        $restaurant = Restaurant::factory()->create();

        $this->get("/api/v1/restaurants/{$restaurant->id}/qr", ['Accept' => 'application/json'])
            ->assertStatus(401);
    }

    #[Test]
    public function test_non_member_cannot_get_qr(): void
    {
        Storage::fake('public');

        $owner = User::factory()->create();
        $other = User::factory()->create();
        $restaurant = Restaurant::factory()->create(['created_by_user_id' => $owner->id]);

        $this->actingAs($other)
            ->get("/api/v1/restaurants/{$restaurant->id}/qr", ['Accept-Language' => ''])
            ->assertStatus(403);
    }
}
