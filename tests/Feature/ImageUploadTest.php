<?php

namespace Tests\Feature;

use App\Jobs\ProcessImageJob;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\MenuSection;
use App\Models\Restaurant;
use App\Models\User;
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
    public function test_unauthenticated_upload_returns_401(): void
    {
        $restaurant = Restaurant::factory()->create();

        $this->post("/api/v1/restaurants/{$restaurant->id}/image", [
            'image' => UploadedFile::fake()->create('photo.jpg', 100, 'image/jpeg'),
        ], ['Accept' => 'application/json'])->assertStatus(401);
    }
}
