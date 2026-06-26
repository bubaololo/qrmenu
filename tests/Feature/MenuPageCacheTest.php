<?php

namespace Tests\Feature;

use App\Actions\ForgetMenuPageCache;
use App\Actions\SaveMenuAnalysisAction;
use App\Jobs\TranslateMenuJob;
use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Support\MenuJson;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Silber\PageCache\Cache;
use Tests\TestCase;

class MenuPageCacheTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    private string $cacheDir;

    protected function setUp(): void
    {
        parent::setUp();

        // Isolate the page-cache files in a throwaway dir (never the real public/).
        $this->cacheDir = storage_path('framework/testing/page-cache-'.uniqid());
        app(Cache::class)->setCachePath($this->cacheDir);

        $this->restaurant = Restaurant::factory()->create(['primary_language' => 'en']);

        $raw = file_get_contents(base_path('tests/llm_responce.json'));
        $menuData = MenuJson::decodeMenuFromLlmText($raw);
        (new SaveMenuAnalysisAction)->handle($menuData, $this->restaurant->id, 1);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->cacheDir);

        parent::tearDown();
    }

    private function writeCachedPage(string $relativePath): string
    {
        $path = $this->cacheDir.'/'.$relativePath;
        File::ensureDirectoryExists(dirname($path));
        File::put($path, '<cached/>');

        return $path;
    }

    #[Test]
    public function test_bogus_locale_301_redirects_to_primary(): void
    {
        $this->get("/{$this->restaurant->id}/zz")
            ->assertStatus(301)
            ->assertRedirectToRoute('menu.public', ['identifier' => $this->restaurant->id, 'lang' => 'en']);
    }

    #[Test]
    public function test_locale_over_tariff_limit_301_redirects_to_primary(): void
    {
        $this->restaurant->update(['max_languages' => 0]);

        Bus::fake(); // guard: a redirect must happen BEFORE any translation dispatch

        $this->get("/{$this->restaurant->id}/fr")
            ->assertStatus(301)
            ->assertRedirectToRoute('menu.public', ['identifier' => $this->restaurant->id, 'lang' => 'en']);

        Bus::assertNothingDispatched();
    }

    #[Test]
    public function test_allowed_cold_locale_renders_and_dispatches_translation(): void
    {
        Bus::fake();

        $this->get("/{$this->restaurant->id}/de")
            ->assertStatus(200)
            ->assertSee('Black coffee'); // source fallback while translating

        Bus::assertDispatchedSync(TranslateMenuJob::class);
    }

    #[Test]
    public function test_primary_locale_renders_without_redirect(): void
    {
        $this->get("/{$this->restaurant->id}/en")
            ->assertStatus(200)
            ->assertSee('Black coffee');
    }

    #[Test]
    public function test_forget_for_restaurant_clears_both_id_and_uniqid_roots(): void
    {
        config(['pagecache.enabled' => true]);

        $id = $this->restaurant->id;
        $uniqid = $this->restaurant->uniqid;

        $base = $this->writeCachedPage("{$id}.html");
        $idLang = $this->writeCachedPage("{$id}/de.html");
        $uniqidTable = $this->writeCachedPage("{$uniqid}/t/abcd1234/de.html");

        app(ForgetMenuPageCache::class)->forRestaurant($this->restaurant->fresh());

        $this->assertFileDoesNotExist($base);
        $this->assertFileDoesNotExist($idLang);
        $this->assertFileDoesNotExist($uniqidTable);
    }

    #[Test]
    public function test_item_update_invalidates_cached_pages(): void
    {
        config(['pagecache.enabled' => true]);

        $cached = $this->writeCachedPage("{$this->restaurant->id}/en.html");

        // Bump updated_at to a distinct value so the update is guaranteed dirty
        // (a same-second touch() would be a no-op and fire no `updated` event).
        $item = MenuItem::query()->firstOrFail();
        $item->forceFill(['updated_at' => now()->addMinute()])->save();

        $this->assertFileDoesNotExist($cached);
    }

    #[Test]
    public function test_translation_save_invalidates_cached_pages(): void
    {
        config(['pagecache.enabled' => true]);

        $cached = $this->writeCachedPage("{$this->restaurant->id}/de.html");

        MenuItem::query()->firstOrFail()->setTranslation('name', 'de', 'Schwarzer Kaffee', isInitial: false);

        $this->assertFileDoesNotExist($cached);
    }

    #[Test]
    public function test_cache_writes_gzip_sibling_and_forget_removes_it(): void
    {
        $cache = app(Cache::class);

        $cache->cache(
            Request::create('/55/en', 'GET'),
            new Response('<html>menu</html>', 200, ['Content-Type' => 'text/html']),
        );

        $html = $this->cacheDir.'/55/en.html';
        $gz = $html.'.gz';
        $this->assertFileExists($html);
        $this->assertFileExists($gz);
        $this->assertSame('<html>menu</html>', gzdecode(file_get_contents($gz)));

        $cache->forget('55/en');
        $this->assertFileDoesNotExist($html);
        $this->assertFileDoesNotExist($gz);
    }

    #[Test]
    public function test_invalidation_is_a_noop_when_disabled(): void
    {
        // pagecache.enabled defaults to false in the test env.
        $cached = $this->writeCachedPage("{$this->restaurant->id}/en.html");

        MenuItem::query()->firstOrFail()->touch();

        $this->assertFileExists($cached);
    }

    #[Test]
    public function test_without_invalidation_suppresses_then_resumes(): void
    {
        config(['pagecache.enabled' => true]);

        $cached = $this->writeCachedPage("{$this->restaurant->id}/en.html");
        $item = MenuItem::query()->firstOrFail();

        ForgetMenuPageCache::withoutInvalidation(
            fn () => $item->forceFill(['updated_at' => now()->addMinute()])->save()
        );
        $this->assertFileExists($cached); // suppressed during the bulk scope

        app(ForgetMenuPageCache::class)->forRestaurant($this->restaurant->fresh());
        $this->assertFileDoesNotExist($cached); // invalidation resumes after the scope
    }
}
