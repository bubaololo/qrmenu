<?php

namespace Tests\Feature;

use App\Enums\MenuAnalysisStatus;
use App\Models\MenuAnalysis;
use App\Models\Restaurant;
use App\Models\User;
use App\Services\AnalysisEventBroker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SseEventsControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Redis::flushdb();
        parent::tearDown();
    }

    #[Test]
    public function unauthenticated_user_cannot_open_event_stream(): void
    {
        $analysis = $this->makeAnalysis();

        $this->getJson("/api/v1/menu-analyses/{$analysis->uuid}/events")
            ->assertStatus(401);
    }

    #[Test]
    public function user_cannot_stream_someone_elses_analysis(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $analysis = $this->makeAnalysis($owner);

        $this->actingAs($intruder)
            ->getJson("/api/v1/menu-analyses/{$analysis->uuid}/events")
            ->assertStatus(403);
    }

    #[Test]
    public function missing_analysis_returns_404(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/menu-analyses/00000000-0000-0000-0000-000000000000/events')
            ->assertStatus(404);
    }

    #[Test]
    public function completed_analysis_streams_buffered_events_and_closes(): void
    {
        $user = User::factory()->create();
        $analysis = $this->makeAnalysis($user, status: MenuAnalysisStatus::Completed);

        $broker = app(AnalysisEventBroker::class);
        $topic = "menu-analysis.{$analysis->uuid}";
        $broker->publish($topic, 'analysis.started', ['chunk_total' => 2]);
        $broker->publish($topic, 'analysis.chunk-complete', ['chunk_index' => 1, 'chunk_total' => 2]);
        $broker->publish($topic, 'analysis.completed', ['menu_id' => 42]);

        // Status is already Completed, so isTerminal() returns true on the first iteration
        // and the stream emits buffered events then closes.
        $response = $this->actingAs($user)
            ->get("/api/v1/menu-analyses/{$analysis->uuid}/events");

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/event-stream; charset=UTF-8');
        $response->assertHeader('X-Accel-Buffering', 'no');

        $body = $response->streamedContent();
        $this->assertStringContainsString('analysis.started', $body);
        $this->assertStringContainsString('analysis.chunk-complete', $body);
        $this->assertStringContainsString('analysis.completed', $body);
        $this->assertStringContainsString('id: 0', $body);
        $this->assertStringContainsString('id: 2', $body);
    }

    private function makeAnalysis(?User $user = null, MenuAnalysisStatus $status = MenuAnalysisStatus::Pending): MenuAnalysis
    {
        $user ??= User::factory()->create();
        $restaurant = Restaurant::factory()->create();

        return MenuAnalysis::create([
            'restaurant_id' => $restaurant->id,
            'user_id' => $user->id,
            'image_count' => 1,
            'image_paths' => [],
            'original_image_paths' => [],
            'image_disk' => 'local',
            'status' => $status,
        ]);
    }
}
