<?php

namespace Tests\Feature;

use App\Actions\SaveMenuAnalysisAction;
use App\Models\Restaurant;
use App\Support\MenuJson;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MenuPageTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::factory()->create();

        $raw = file_get_contents(base_path('tests/llm_responce.json'));
        $menuData = MenuJson::decodeMenuFromLlmText($raw);
        $menu = (new SaveMenuAnalysisAction)->handle($menuData, $this->restaurant->id, 1);
        $menu->update(['is_active' => true]);
    }

    #[Test]
    public function test_returns_200_by_numeric_id(): void
    {
        $this->get("/{$this->restaurant->id}")
            ->assertStatus(200)
            ->assertSee('Black coffee');
    }

    #[Test]
    public function test_returns_200_by_uniqid(): void
    {
        $this->get("/{$this->restaurant->uniqid}")
            ->assertStatus(200)
            ->assertSee('Black coffee');
    }

    #[Test]
    public function test_returns_404_for_nonexistent_id(): void
    {
        $this->get('/99999')->assertStatus(404);
    }

    #[Test]
    public function test_returns_404_for_nonexistent_uniqid(): void
    {
        $this->get('/nonexst')->assertStatus(404);
    }

    #[Test]
    public function test_shows_menu_with_explicit_lang(): void
    {
        $this->get("/{$this->restaurant->id}/vi")->assertStatus(200);
    }

    #[Test]
    public function test_handles_restaurant_with_no_active_menu(): void
    {
        $empty = Restaurant::factory()->create();

        $this->get("/{$empty->id}")->assertStatus(200);
    }
}
