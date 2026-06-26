<?php

namespace Tests\Feature;

use App\Actions\SaveMenuAnalysisAction;
use App\Models\DiningTable;
use App\Models\MenuItem;
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
        (new SaveMenuAnalysisAction)->handle($menuData, $this->restaurant->id, 1);
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
    public function test_does_not_trigger_translation_when_opening_menu_in_its_source_language(): void
    {
        // Opening a menu in its own original language must not retrigger the
        // translator (otherwise SSE 'translation.completed' replays loop the page
        // on every reload).
        $this->restaurant->update(['primary_language' => 'en']);
        $this->restaurant->menu->update(['source_locale' => 'en']);

        $response = $this->get("/{$this->restaurant->id}/en")->assertStatus(200);

        // The banner only renders when translationPending=true; its sentinel id
        // is unambiguous. Absence means we correctly skipped the translator.
        $response->assertDontSee('id="translation-banner"', false);
    }

    #[Test]
    public function test_picker_marks_already_translated_locale_as_available_now(): void
    {
        // A locale with real (non-initial) translations must show under "Available
        // now" even when the page is being viewed in a *different* language.
        $this->restaurant->update(['primary_language' => 'en']);
        $this->restaurant->menu->update(['source_locale' => 'en']);

        MenuItem::query()->firstOrFail()
            ->setTranslation('name', 'fr', 'Café noir', isInitial: false);

        $response = $this->get("/{$this->restaurant->id}/en")->assertStatus(200);

        // The fr option sits in the ready group (data-section precedes nothing but
        // the class on the same anchor; data-code precedes data-section in markup).
        $this->assertMatchesRegularExpression(
            '/data-code="fr"[^>]*data-section="ready"/s',
            $response->getContent(),
        );
    }

    #[Test]
    public function test_handles_restaurant_with_no_active_menu(): void
    {
        $empty = Restaurant::factory()->create();

        $this->get("/{$empty->id}")->assertStatus(200);
    }

    #[Test]
    public function test_hero_shows_currency(): void
    {
        $this->restaurant->update(['currency' => 'VND']);

        $this->get("/{$this->restaurant->id}/en")
            ->assertStatus(200)
            ->assertSee('Currency')
            ->assertSee('VND');
    }

    #[Test]
    public function test_hero_shows_24_7_when_flagged(): void
    {
        $this->restaurant->update([
            'opening_hours' => ['is_24_7' => true],
        ]);

        $this->get("/{$this->restaurant->id}/en")
            ->assertStatus(200)
            ->assertSee('24 hours')
            ->assertSee('Open now');
    }

    #[Test]
    public function test_hero_shows_today_period(): void
    {
        $todayCode = strtolower(now()->format('D'));

        $this->restaurant->update([
            'opening_hours' => [
                'periods' => [
                    ['days' => [$todayCode], 'open' => '08:00', 'close' => '23:30'],
                ],
            ],
        ]);

        $this->get("/{$this->restaurant->id}/en")
            ->assertStatus(200)
            ->assertSee('08:00–23:30');
    }

    #[Test]
    public function test_hero_shows_closed_today_when_no_period_matches(): void
    {
        $otherDay = strtolower(now()->addDay()->format('D'));

        $this->restaurant->update([
            'opening_hours' => [
                'periods' => [
                    ['days' => [$otherDay], 'open' => '08:00', 'close' => '23:30'],
                ],
            ],
        ]);

        $this->get("/{$this->restaurant->id}/en")
            ->assertStatus(200)
            ->assertSee('Closed today')
            ->assertSee('Closed');
    }

    #[Test]
    public function test_info_panel_shows_todays_hours_only(): void
    {
        $this->restaurant->update([
            'opening_hours' => [
                'periods' => [
                    ['days' => ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'], 'open' => '10:00', 'close' => '22:00'],
                ],
            ],
        ]);

        $response = $this->get("/{$this->restaurant->id}/en")
            ->assertStatus(200)
            ->assertSee('id="info-toggle"', false)
            ->assertSee('id="info-panel"', false)
            ->assertSee('10:00–22:00');

        // Today only — no full weekly breakdown.
        $this->assertStringNotContainsString('info-schedule-day', $response->getContent());
        // Today's window appears exactly once, not seven times.
        $this->assertSame(1, substr_count($response->getContent(), '10:00–22:00'));
    }

    #[Test]
    public function test_info_panel_shows_phone_with_tel_link_and_copy_button(): void
    {
        $this->restaurant->update(['phone' => '+84 263 3816 868']);

        $this->get("/{$this->restaurant->id}/en")
            ->assertStatus(200)
            // tel: target keeps a leading + but strips spaces/punctuation.
            ->assertSee('href="tel:+842633816868"', false)
            ->assertSee('class="info-copy"', false)
            ->assertSee('data-copy="+84 263 3816 868"', false);
    }

    #[Test]
    public function test_info_panel_shows_address_with_maps_link_and_copy_button(): void
    {
        $this->restaurant->update([
            'city' => null,
            'address' => '12 Test Street',
            'google_maps_url' => 'https://maps.google.com/?q=test',
        ]);

        $this->get("/{$this->restaurant->id}/en")
            ->assertStatus(200)
            ->assertSee('href="https://maps.google.com/?q=test"', false)
            ->assertSee('class="info-text info-link"', false)
            ->assertSee('data-copy="12 Test Street"', false);
    }

    #[Test]
    public function test_info_panel_absent_when_no_contact_info(): void
    {
        $bare = Restaurant::factory()->create(['city' => null]);

        $this->get("/{$bare->id}")
            ->assertStatus(200)
            ->assertDontSee('id="info-panel"', false)
            ->assertDontSee('id="info-toggle"', false);
    }

    #[Test]
    public function test_hero_banner_renders_when_restaurant_has_image(): void
    {
        $this->restaurant->update(['image' => 'restaurants/test-banner.webp']);

        $this->get("/{$this->restaurant->id}/en")
            ->assertStatus(200)
            ->assertSee('class="hero-banner"', false)
            ->assertSee('restaurants/test-banner.webp');
    }

    #[Test]
    public function test_hero_logo_renders_when_restaurant_has_logo(): void
    {
        $this->restaurant->update(['logo' => 'logos/test-logo.webp']);

        $this->get("/{$this->restaurant->id}/en")
            ->assertStatus(200)
            ->assertSee('class="hero-logo', false)
            ->assertSee('logos/test-logo_thumb.webp');
    }

    #[Test]
    public function test_item_card_exposes_full_image_url_for_the_sheet(): void
    {
        $item = MenuItem::query()->firstOrFail();
        $item->update(['image' => 'menu-items/full-test.webp']);

        $this->get("/{$this->restaurant->id}/en")
            ->assertStatus(200)
            ->assertSee('data-full=', false)
            ->assertSee('menu-items/full-test.webp');
    }

    #[Test]
    public function test_starred_item_renders_recommended_marker(): void
    {
        $item = MenuItem::query()->firstOrFail();
        $item->update(['starred' => true]);

        $this->get("/{$this->restaurant->id}/en")
            ->assertStatus(200)
            ->assertSee('class="menu-card-star"', false)
            ->assertSee('data-starred="1"', false)
            ->assertSee('Recommended');
    }

    #[Test]
    public function test_unstarred_menu_has_no_recommended_marker(): void
    {
        MenuItem::query()->update(['starred' => false]);

        $this->get("/{$this->restaurant->id}/en")
            ->assertStatus(200)
            ->assertDontSee('class="menu-card-star"', false)
            ->assertDontSee('data-starred', false);
    }

    #[Test]
    public function test_hero_markup_absent_without_branding_images(): void
    {
        $this->get("/{$this->restaurant->id}/en")
            ->assertStatus(200)
            ->assertDontSee('class="hero-banner"', false)
            ->assertDontSee('class="hero-logo', false);
    }
}
