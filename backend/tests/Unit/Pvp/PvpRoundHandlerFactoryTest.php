<?php

namespace Tests\Unit\Pvp;

use App\Models\PvpMatch;
use App\Services\Pvp\Rounds\PvpRoundHandlerFactory;
use App\Services\Pvp\Rounds\PvpRoundHandlerInterface;
use App\Services\Pvp\Rounds\PvpRoundResult;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PvpRoundHandlerFactoryTest extends TestCase
{
    public function test_factory_returns_matching_handler(): void
    {
        $a = new class implements PvpRoundHandlerInterface {
            public function type(): string { return 'a'; }
            public function name(): string { return 'A'; }
            public function initialize(PvpMatch $match): array { return []; }
            public function publicState(PvpMatch $match, int $userId): array { return []; }
            public function handleAction(PvpMatch $match, int $userId, array $action): PvpRoundResult { return PvpRoundResult::ongoing([], []); }
        };

        $b = new class implements PvpRoundHandlerInterface {
            public function type(): string { return 'b'; }
            public function name(): string { return 'B'; }
            public function initialize(PvpMatch $match): array { return []; }
            public function publicState(PvpMatch $match, int $userId): array { return []; }
            public function handleAction(PvpMatch $match, int $userId, array $action): PvpRoundResult { return PvpRoundResult::ongoing([], []); }
        };

        $factory = new PvpRoundHandlerFactory([$a, $b]);
        $res = $factory->forType('b');

        $this->assertSame('b', $res->type());
    }

    public function test_factory_unknown_type_aborts(): void
    {
        $factory = new PvpRoundHandlerFactory([]);

        $this->expectException(HttpException::class);
        $factory->forType('nope');
    }
}
