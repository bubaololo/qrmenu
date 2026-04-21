<?php

namespace Tests\Feature;

use App\Jobs\ProcessImageJob;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\MenuSection;
use App\Models\Restaurant;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ImageUploadTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_restaurant_image_upload_dispatches_job(): void
    {
        Queue::fake();
        Storage::fake('public');
        Storage::fake('local');

        $user = User::factory()->create();
        $restaurant = Restaurant::factory()->create(['created_by_user_id' => $user->id]);

        $this->actingAs($user)
            ->post("/api/v1/restaurants/{$restaurant->id}/image", [
                'image' => UploadedFile::fake()->create('photo.jpg', 100, 'image/jpeg'),
            ], ['Accept-Language' => ''])
            ->assertStatus(202)
            ->assertJsonStructure(['data' => ['image_url', 'thumb_url']]);

        Queue::assertPushed(ProcessImageJob::class, fn ($job) => $job->modelClass === Restaurant::class
            && $job->modelId === $restaurant->id);
    }

    #[Test]
    public function test_menu_item_image_upload_dispatches_job(): void
    {
        Queue::fake();
        Storage::fake('public');
        Storage::fake('local');

        $user = User::factory()->create();
        $restaurant = Restaurant::factory()->create(['created_by_user_id' => $user->id]);
        $menu = Menu::factory()->for($restaurant)->create();
        $section = MenuSection::factory()->for($menu)->create();
        $item = MenuItem::factory()->for($section, 'section')->create();

        $this->actingAs($user)
            ->post("/api/v1/menu-items/{$item->id}/image", [
                'image' => UploadedFile::fake()->create('photo.jpg', 100, 'image/jpeg'),
            ], ['Accept-Language' => ''])
            ->assertStatus(202)
            ->assertJsonStructure(['data' => ['image_url', 'thumb_url']]);

        Queue::assertPushed(ProcessImageJob::class, fn ($job) => $job->modelClass === MenuItem::class
            && $job->modelId === $item->id);
    }

    #[Test]
    public function test_restaurant_image_delete_removes_files_and_clears_field(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $restaurant = Restaurant::factory()->create([
            'created_by_user_id' => $user->id,
            'image' => 'restaurants/test.webp',
        ]);

        Storage::disk('public')->put('restaurants/test.webp', 'fake');
        Storage::disk('public')->put('restaurants/test_thumb.webp', 'fake');

        $this->actingAs($user)
            ->delete("/api/v1/restaurants/{$restaurant->id}/image", [], ['Accept-Language' => ''])
            ->assertStatus(204);

        $this->assertNull($restaurant->fresh()->image);
        Storage::disk('public')->assertMissing('restaurants/test.webp');
        Storage::disk('public')->assertMissing('restaurants/test_thumb.webp');
    }

    #[Test]
    public function test_restaurant_logo_upload_dispatches_job(): void
    {
        Queue::fake();
        Storage::fake('public');
        Storage::fake('local');

        $user = User::factory()->create();
        $restaurant = Restaurant::factory()->create(['created_by_user_id' => $user->id]);

        $this->actingAs($user)
            ->post("/api/v1/restaurants/{$restaurant->id}/logo", [
                'image' => UploadedFile::fake()->create('logo.png', 50, 'image/png'),
            ], ['Accept-Language' => ''])
            ->assertStatus(202)
            ->assertJsonStructure(['data' => ['image_url', 'thumb_url']]);

        Queue::assertPushed(ProcessImageJob::class, fn ($job) => $job->modelClass === Restaurant::class
            && $job->modelId === $restaurant->id
            && $job->fieldName === 'logo');
    }

    #[Test]
    public function test_restaurant_logo_delete_removes_files_and_clears_field(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $restaurant = Restaurant::factory()->create([
            'created_by_user_id' => $user->id,
            'logo' => 'logos/test.webp',
        ]);

        Storage::disk('public')->put('logos/test.webp', 'fake');
        Storage::disk('public')->put('logos/test_thumb.webp', 'fake');

        $this->actingAs($user)
            ->delete("/api/v1/restaurants/{$restaurant->id}/logo", [], ['Accept-Language' => ''])
            ->assertStatus(204);

        $this->assertNull($restaurant->fresh()->logo);
        Storage::disk('public')->assertMissing('logos/test.webp');
        Storage::disk('public')->assertMissing('logos/test_thumb.webp');
    }

    #[Test]
    public function test_zone_image_upload_dispatches_job(): void
    {
        Queue::fake();
        Storage::fake('public');
        Storage::fake('local');

        $user = User::factory()->create();
        $restaurant = Restaurant::factory()->create(['created_by_user_id' => $user->id]);
        $zone = Zone::factory()->create(['restaurant_id' => $restaurant->id]);

        $this->actingAs($user)
            ->post("/api/v1/zones/{$zone->id}/image", [
                'image' => UploadedFile::fake()->create('zone.jpg', 100, 'image/jpeg'),
            ], ['Accept-Language' => ''])
            ->assertStatus(202)
            ->assertJsonStructure(['data' => ['image_url', 'thumb_url']]);

        Queue::assertPushed(ProcessImageJob::class, fn ($job) => $job->modelClass === Zone::class
            && $job->modelId === $zone->id);
    }

    #[Test]
    public function test_zone_image_delete_removes_files_and_clears_field(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $restaurant = Restaurant::factory()->create(['created_by_user_id' => $user->id]);
        $zone = Zone::factory()->create(['restaurant_id' => $restaurant->id, 'image' => 'zones/test.webp']);

        Storage::disk('public')->put('zones/test.webp', 'fake');
        Storage::disk('public')->put('zones/test_thumb.webp', 'fake');

        $this->actingAs($user)
            ->delete("/api/v1/zones/{$zone->id}/image", [], ['Accept-Language' => ''])
            ->assertStatus(204);

        $this->assertNull($zone->fresh()->image);
        Storage::disk('public')->assertMissing('zones/test.webp');
        Storage::disk('public')->assertMissing('zones/test_thumb.webp');
    }

    #[Test]
    public function test_unauthenticated_upload_returns_401(): void
    {
        $restaurant = Restaurant::factory()->create();

        $this->post("/api/v1/restaurants/{$restaurant->id}/image", [
            'image' => UploadedFile::fake()->create('photo.jpg', 100, 'image/jpeg'),
        ], ['Accept' => 'application/json'])->assertStatus(401);
    }
}
