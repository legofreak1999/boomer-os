<?php

namespace Tests\Unit\Monitors;

use App\Actions\Monitors\EvaluateMonitor;
use App\Models\Monitor;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\Response;
use PHPUnit\Framework\TestCase;

class EvaluateMonitorTest extends TestCase
{
    private function response(string $body, int $status = 200): Response
    {
        return new Response(new Psr7Response($status, [], $body));
    }

    private function monitor(string $type, array $config): Monitor
    {
        $monitor = new Monitor;
        $monitor->check_type = $type;
        $monitor->check_config = $config;

        return $monitor;
    }

    public function test_text_contains_matches_case_insensitive_by_default(): void
    {
        $evaluate = new EvaluateMonitor;
        $monitor = $this->monitor(Monitor::CHECK_TEXT_CONTAINS, ['needle' => 'Out of stock']);

        $this->assertTrue($evaluate($monitor, $this->response('<p>out of STOCK today</p>')));
        $this->assertFalse($evaluate($monitor, $this->response('<p>in stock</p>')));
    }

    public function test_text_contains_case_sensitive(): void
    {
        $evaluate = new EvaluateMonitor;
        $monitor = $this->monitor(Monitor::CHECK_TEXT_CONTAINS, ['needle' => 'Out of stock', 'case_sensitive' => true]);

        $this->assertFalse($evaluate($monitor, $this->response('<p>out of stock</p>')));
        $this->assertTrue($evaluate($monitor, $this->response('<p>Out of stock</p>')));
    }

    public function test_css_selector_presence(): void
    {
        $evaluate = new EvaluateMonitor;
        $monitor = $this->monitor(Monitor::CHECK_CSS_SELECTOR, ['selector' => '.sold-out']);

        $this->assertTrue($evaluate($monitor, $this->response('<div class="sold-out">Nope</div>')));
        $this->assertFalse($evaluate($monitor, $this->response('<div class="in-stock">Yes</div>')));
    }

    public function test_css_selector_with_expected_text(): void
    {
        $evaluate = new EvaluateMonitor;
        $monitor = $this->monitor(Monitor::CHECK_CSS_SELECTOR, [
            'selector' => '.status',
            'expected_text' => 'Sold out',
        ]);

        $this->assertTrue($evaluate($monitor, $this->response('<div class="status">Sold out</div>')));
        $this->assertFalse($evaluate($monitor, $this->response('<div class="status">In stock</div>')));
    }

    public function test_regex_match(): void
    {
        $evaluate = new EvaluateMonitor;
        $monitor = $this->monitor(Monitor::CHECK_REGEX, ['pattern' => '/out\s+of\s+stock/i']);

        $this->assertTrue($evaluate($monitor, $this->response('This is Out   Of Stock')));
        $this->assertFalse($evaluate($monitor, $this->response('Available now')));
    }

    public function test_regex_invalid_throws(): void
    {
        $evaluate = new EvaluateMonitor;
        $monitor = $this->monitor(Monitor::CHECK_REGEX, ['pattern' => '/(unterminated']);

        $this->expectException(\InvalidArgumentException::class);
        $evaluate($monitor, $this->response('anything'));
    }

    public function test_http_status_match(): void
    {
        $evaluate = new EvaluateMonitor;
        $monitor = $this->monitor(Monitor::CHECK_HTTP_STATUS, ['expected_status' => 200]);

        $this->assertTrue($evaluate($monitor, $this->response('', 200)));
        $this->assertFalse($evaluate($monitor, $this->response('', 404)));
    }
}
