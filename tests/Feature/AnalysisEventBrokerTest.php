<?php

namespace Tests\Feature;

use App\Services\AnalysisEventBroker;
use Illuminate\Support\Facades\Redis;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AnalysisEventBrokerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Redis::del('events:test-topic');
    }

    protected function tearDown(): void
    {
        Redis::del('events:test-topic');
        parent::tearDown();
    }

    #[Test]
    public function it_publishes_events_in_chronological_order(): void
    {
        $broker = new AnalysisEventBroker;

        $broker->publish('test-topic', 'started', ['n' => 1]);
        $broker->publish('test-topic', 'chunk-complete', ['n' => 2]);
        $broker->publish('test-topic', 'completed', ['n' => 3]);

        $events = $broker->readSince('test-topic', 0);
        $this->assertCount(3, $events);

        $decoded = array_map(fn ($e) => json_decode($e, true), $events);
        $this->assertSame('started', $decoded[0]['event']);
        $this->assertSame('chunk-complete', $decoded[1]['event']);
        $this->assertSame('completed', $decoded[2]['event']);
        $this->assertSame(['n' => 1], $decoded[0]['data']);
    }

    #[Test]
    public function read_since_returns_only_events_at_or_after_index(): void
    {
        $broker = new AnalysisEventBroker;
        $broker->publish('test-topic', 'a', []);
        $broker->publish('test-topic', 'b', []);
        $broker->publish('test-topic', 'c', []);

        $tail = $broker->readSince('test-topic', 1);
        $this->assertCount(2, $tail);
        $this->assertSame('b', json_decode($tail[0], true)['event']);
        $this->assertSame('c', json_decode($tail[1], true)['event']);
    }

    #[Test]
    public function total_events_reports_list_length(): void
    {
        $broker = new AnalysisEventBroker;
        $this->assertSame(0, $broker->totalEvents('test-topic'));

        $broker->publish('test-topic', 'a', []);
        $broker->publish('test-topic', 'b', []);

        $this->assertSame(2, $broker->totalEvents('test-topic'));
    }
}
