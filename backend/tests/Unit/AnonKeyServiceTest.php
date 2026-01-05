<?php

namespace Tests\Unit;

use App\Services\AnonKeyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class AnonKeyServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_from_request_returns_expected_hmac_hash(): void
    {
        config()->set('app.key', 'base64:TEST_APP_KEY_FOR_HASHING');

        $request = Request::create('/dummy', 'GET', [], [], [], [
            'REMOTE_ADDR' => '203.0.113.42',
        ]);

        $service = $this->app->make(AnonKeyService::class);

        $expected = hash_hmac('sha256', '203.0.113.42', config('app.key'));
        $this->assertSame($expected, $service->fromRequest($request));
    }

    public function test_from_request_is_deterministic_for_same_ip(): void
    {
        config()->set('app.key', 'base64:TEST_APP_KEY_FOR_HASHING');

        $service = $this->app->make(AnonKeyService::class);

        $r1 = Request::create('/a', 'GET', [], [], [], ['REMOTE_ADDR' => '198.51.100.10']);
        $r2 = Request::create('/b', 'POST', [], [], [], ['REMOTE_ADDR' => '198.51.100.10']);

        $this->assertSame($service->fromRequest($r1), $service->fromRequest($r2));
    }

    public function test_from_request_changes_when_ip_changes(): void
    {
        config()->set('app.key', 'base64:TEST_APP_KEY_FOR_HASHING');

        $service = $this->app->make(AnonKeyService::class);

        $r1 = Request::create('/a', 'GET', [], [], [], ['REMOTE_ADDR' => '198.51.100.10']);
        $r2 = Request::create('/b', 'GET', [], [], [], ['REMOTE_ADDR' => '198.51.100.11']);

        $this->assertNotSame($service->fromRequest($r1), $service->fromRequest($r2));
    }
}
