<?php

namespace Tests\Unit\Pvp;

use App\Services\Pvp\Rounds\HintValueService;
use App\Services\Pvp\Rounds\WhoisQuestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Mockery;
use stdClass;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class WhoisQuestionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_validate_rejects_invalid_key(): void
    {
        Config::set('pvp.whois.keys.kcdle', ['age']);
        Config::set('pvp.whois.meta.age', ['type' => 'number', 'ops' => ['eq', 'lt', 'gt'], 'cast' => 'int']);

        $svc = app(WhoisQuestionService::class);

        $this->expectException(HttpException::class);
        $svc->validate(['key' => 'nope', 'op' => 'eq', 'value' => 1], 'kcdle');
    }

    public function test_validate_rejects_invalid_operator(): void
    {
        Config::set('pvp.whois.keys.kcdle', ['age']);
        Config::set('pvp.whois.meta.age', ['type' => 'number', 'ops' => ['eq', 'lt', 'gt'], 'cast' => 'int']);

        $svc = app(WhoisQuestionService::class);

        $this->expectException(HttpException::class);
        $svc->validate(['key' => 'age', 'op' => 'nope', 'value' => 1], 'kcdle');
    }

    public function test_validate_casts_value_and_accepts(): void
    {
        Config::set('pvp.whois.keys.kcdle', ['age']);
        Config::set('pvp.whois.meta.age', ['type' => 'number', 'ops' => ['eq', 'lt', 'gt'], 'cast' => 'int']);

        $svc = app(WhoisQuestionService::class);

        $q = $svc->validate(['key' => 'age', 'op' => 'gt', 'value' => '18'], 'kcdle');

        $this->assertSame('age', $q['key']);
        $this->assertSame('gt', $q['op']);
        $this->assertSame(18, $q['value']);
    }

    public function test_evaluate_numeric_gt(): void
    {
        Config::set('pvp.whois.meta.age', ['cast' => 'int']);

        $hints = Mockery::mock(HintValueService::class);
        $hints->shouldReceive('readHintValue')->once()->andReturn(25);

        $svc = app(WhoisQuestionService::class);

        $ok = $svc->evaluate(new stdClass(), 'age', 'gt', 18);
        $this->assertTrue($ok);
    }

    public function test_evaluate_eq_strict(): void
    {
        Config::set('pvp.whois.meta.country_code', ['cast' => 'upper']);

        $hints = Mockery::mock(HintValueService::class);
        $hints->shouldReceive('readHintValue')->once()->andReturn('fr');

        $svc = app(WhoisQuestionService::class);

        $ok = $svc->evaluate(new stdClass(), 'country_code', 'eq', 'FR');
        $this->assertTrue($ok);
    }
}
