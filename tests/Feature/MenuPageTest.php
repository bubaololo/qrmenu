<?php

namespace Tests\Feature;

use App\Actions\SaveMenuAnalysisAction;
use App\Models\DiningTable;
use App\Models\Restaurant;
use App\Models\Zone;
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
    public function test_table_url_resolves_to_menu_for_valid_pair(): void
    {
        $zone = Zone::factory()->create(['restaurant_id' => $this->restaurant->id]);
        $table = DiningTable::factory()->create(['zone_id' => $zone->id]);

        $this->get("/{$this->restaurant->uniqid}/t/{$table->uniqid}")
            ->assertStatus(200)
            ->assertSee('Black coffee');
    }

    #[Test]
    public function test_table_url_returns_404_when_table_belongs_to_other_restaurant(): void
    {
        $other = Restaurant::factory()->create();
        $zone = Zone::factory()->create(['restaurant_id' => $other->id]);
        $table = DiningTable::factory()->create(['zone_id' => $zone->id]);

        $this->get("/{$this->restaurant->uniqid}/t/{$table->uniqid}")
            ->assertStatus(404);
    }

    #[Test]
    public function test_table_url_returns_404_for_nonexistent_table_uniqid(): void
    {
        $this->get("/{$this->restaurant->uniqid}/t/doesnotexist123")
            ->assertStatus(404);
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
