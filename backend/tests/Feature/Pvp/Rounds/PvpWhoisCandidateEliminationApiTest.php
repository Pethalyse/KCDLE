<?php

namespace Feature\Pvp\Rounds;

use App\Models\Game;
use App\Models\PvpMatch;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\Support\PvpTestHelper;
use Tests\TestCase;

/**
 * Validates server-side candidate elimination for the WHOIS PvP round.
 *
 * The WHOIS "ask" action must filter candidate_ids so only the candidates that share the same evaluated answer
 * as the secret player remain, for every key and every operator supported by the game configuration.
 */
class PvpWhoisCandidateEliminationApiTest extends TestCase
{
    use RefreshDatabase;
    use PvpTestHelper;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        Config::set('pvp.disable_shuffle', true);
        Carbon::setTestNow(Carbon::parse('2026-01-25 12:00:00'));
    }

    /**
     * @return void
     */
    public function test_ask_eliminates_candidates_for_country_code_when_answer_is_false(): void
    {
        $s = $this->seedScenarioKcdle();
        $ids = $s['ids'];

        $match = $this->createInitializedWhoisMatch((int) $ids['w2']);

        $this->chooseTurn((int) $match->id, (int) $match->state['chooser_user_id'], (int) $match->state['chooser_user_id']);
        $this->ask((int) $match->id, (int) $match->state['chooser_user_id'], [
            'key' => 'country_code',
            'op' => 'eq',
            'value' => 'FR',
        ]);

        $candidateIds = $this->fetchCandidateIds((int) $match->id, (int) $match->state['chooser_user_id']);
        $this->assertSameIds([$ids['w2'], $ids['w3']], $candidateIds);
    }

    /**
     * @return void
     */
    public function test_ask_eliminates_candidates_for_country_code_when_answer_is_true(): void
    {
        $s = $this->seedScenarioKcdle();
        $ids = $s['ids'];

        $match = $this->createInitializedWhoisMatch((int) $ids['w2']);

        $this->chooseTurn((int) $match->id, (int) $match->state['chooser_user_id'], (int) $match->state['chooser_user_id']);
        $this->ask((int) $match->id, (int) $match->state['chooser_user_id'], [
            'key' => 'country_code',
            'op' => 'eq',
            'value' => 'US',
        ]);

        $candidateIds = $this->fetchCandidateIds((int) $match->id, (int) $match->state['chooser_user_id']);
        $this->assertSameIds([$ids['w2']], $candidateIds);
    }

    /**
     * @return void
     */
    public function test_ask_eliminates_candidates_for_current_team_id(): void
    {
        $s = $this->seedScenarioKcdle();
        $ids = $s['ids'];
        $teams = $s['teams'];

        $match = $this->createInitializedWhoisMatch((int) $ids['w1']);

        $this->chooseTurn((int) $match->id, (int) $match->state['chooser_user_id'], (int) $match->state['chooser_user_id']);
        $this->ask((int) $match->id, (int) $match->state['chooser_user_id'], [
            'key' => 'current_team_id',
            'op' => 'eq',
            'value' => $teams['aegis'],
        ]);

        $candidateIds = $this->fetchCandidateIds((int) $match->id, (int) $match->state['chooser_user_id']);
        $this->assertSameIds([$ids['w1'], $ids['w3']], $candidateIds);
    }

    /**
     * @return void
     */
    public function test_ask_eliminates_candidates_for_previous_team_id_when_answer_is_false(): void
    {
        $s = $this->seedScenarioKcdle();
        $ids = $s['ids'];
        $teams = $s['teams'];

        $match = $this->createInitializedWhoisMatch((int) $ids['w4']);

        $this->chooseTurn((int) $match->id, (int) $match->state['chooser_user_id'], (int) $match->state['chooser_user_id']);
        $this->ask((int) $match->id, (int) $match->state['chooser_user_id'], [
            'key' => 'previous_team_id',
            'op' => 'eq',
            'value' => $teams['g2'],
        ]);

        $candidateIds = $this->fetchCandidateIds((int) $match->id, (int) $match->state['chooser_user_id']);
        $this->assertSameIds([$ids['w3'], $ids['w4']], $candidateIds);
    }

    /**
     * @return void
     */
    public function test_ask_eliminates_candidates_for_trophies_count_gt(): void
    {
        $s = $this->seedScenarioKcdle();
        $ids = $s['ids'];

        $match = $this->createInitializedWhoisMatch((int) $ids['w1']);

        $this->chooseTurn((int) $match->id, (int) $match->state['chooser_user_id'], (int) $match->state['chooser_user_id']);
        $this->ask((int) $match->id, (int) $match->state['chooser_user_id'], [
            'key' => 'trophies_count',
            'op' => 'gt',
            'value' => 4,
        ]);

        $candidateIds = $this->fetchCandidateIds((int) $match->id, (int) $match->state['chooser_user_id']);
        $this->assertSameIds([$ids['w1'], $ids['w4']], $candidateIds);
    }

    /**
     * @return void
     */
    public function test_ask_eliminates_candidates_for_trophies_count_lt(): void
    {
        $s = $this->seedScenarioKcdle();
        $ids = $s['ids'];

        $match = $this->createInitializedWhoisMatch((int) $ids['w2']);

        $this->chooseTurn((int) $match->id, (int) $match->state['chooser_user_id'], (int) $match->state['chooser_user_id']);
        $this->ask((int) $match->id, (int) $match->state['chooser_user_id'], [
            'key' => 'trophies_count',
            'op' => 'lt',
            'value' => 2,
        ]);

        $candidateIds = $this->fetchCandidateIds((int) $match->id, (int) $match->state['chooser_user_id']);
        $this->assertSameIds([$ids['w2']], $candidateIds);
    }

    /**
     * @return void
     */
    public function test_ask_eliminates_candidates_for_first_official_year_lt(): void
    {
        $s = $this->seedScenarioKcdle();
        $ids = $s['ids'];

        $match = $this->createInitializedWhoisMatch((int) $ids['w1']);

        $this->chooseTurn((int) $match->id, (int) $match->state['chooser_user_id'], (int) $match->state['chooser_user_id']);
        $this->ask((int) $match->id, (int) $match->state['chooser_user_id'], [
            'key' => 'first_official_year',
            'op' => 'lt',
            'value' => 2023,
        ]);

        $candidateIds = $this->fetchCandidateIds((int) $match->id, (int) $match->state['chooser_user_id']);
        $this->assertSameIds([$ids['w1'], $ids['w3']], $candidateIds);
    }

    /**
     * @return void
     */
    public function test_ask_eliminates_candidates_for_first_official_year_eq(): void
    {
        $s = $this->seedScenarioKcdle();
        $ids = $s['ids'];

        $match = $this->createInitializedWhoisMatch((int) $ids['w4']);

        $this->chooseTurn((int) $match->id, (int) $match->state['chooser_user_id'], (int) $match->state['chooser_user_id']);
        $this->ask((int) $match->id, (int) $match->state['chooser_user_id'], [
            'key' => 'first_official_year',
            'op' => 'eq',
            'value' => 2023,
        ]);

        $candidateIds = $this->fetchCandidateIds((int) $match->id, (int) $match->state['chooser_user_id']);
        $this->assertSameIds([$ids['w4']], $candidateIds);
    }

    /**
     * @return void
     */
    public function test_ask_eliminates_candidates_for_age_gt(): void
    {
        $s = $this->seedScenarioKcdle();
        $ids = $s['ids'];

        $match = $this->createInitializedWhoisMatch((int) $ids['w1']);

        $this->chooseTurn((int) $match->id, (int) $match->state['chooser_user_id'], (int) $match->state['chooser_user_id']);
        $this->ask((int) $match->id, (int) $match->state['chooser_user_id'], [
            'key' => 'age',
            'op' => 'gt',
            'value' => 18,
        ]);

        $candidateIds = $this->fetchCandidateIds((int) $match->id, (int) $match->state['chooser_user_id']);
        $this->assertSameIds([$ids['w1'], $ids['w4']], $candidateIds);
    }

    /**
     * @return void
     */
    public function test_ask_eliminates_candidates_for_age_eq(): void
    {
        $s = $this->seedScenarioKcdle();
        $ids = $s['ids'];

        $match = $this->createInitializedWhoisMatch((int) $ids['w2']);

        $this->chooseTurn((int) $match->id, (int) $match->state['chooser_user_id'], (int) $match->state['chooser_user_id']);
        $this->ask((int) $match->id, (int) $match->state['chooser_user_id'], [
            'key' => 'age',
            'op' => 'eq',
            'value' => 16,
        ]);

        $candidateIds = $this->fetchCandidateIds((int) $match->id, (int) $match->state['chooser_user_id']);
        $this->assertSameIds([$ids['w2'], $ids['w3']], $candidateIds);
    }

    /**
     * @return void
     */
    public function test_ask_eliminates_candidates_for_role_id(): void
    {
        $s = $this->seedScenarioKcdle();
        $ids = $s['ids'];
        $roles = $s['roles'];

        $match = $this->createInitializedWhoisMatch((int) $ids['w3']);

        $this->chooseTurn((int) $match->id, (int) $match->state['chooser_user_id'], (int) $match->state['chooser_user_id']);
        $this->ask((int) $match->id, (int) $match->state['chooser_user_id'], [
            'key' => 'role_id',
            'op' => 'eq',
            'value' => $roles['sup'],
        ]);

        $candidateIds = $this->fetchCandidateIds((int) $match->id, (int) $match->state['chooser_user_id']);
        $this->assertSameIds([$ids['w3'], $ids['w4']], $candidateIds);
    }

    /**
     * @return void
     */
    public function test_ask_eliminates_candidates_for_game_id(): void
    {
        $s = $this->seedScenarioKcdle();
        $ids = $s['ids'];
        $games = $s['games'];

        $match = $this->createInitializedWhoisMatch((int) $ids['w1']);

        $this->chooseTurn((int) $match->id, (int) $match->state['chooser_user_id'], (int) $match->state['chooser_user_id']);
        $this->ask((int) $match->id, (int) $match->state['chooser_user_id'], [
            'key' => 'game_id',
            'op' => 'eq',
            'value' => $games['a'],
        ]);

        $candidateIds = $this->fetchCandidateIds((int) $match->id, (int) $match->state['chooser_user_id']);
        $this->assertSameIds([$ids['w1'], $ids['w2']], $candidateIds);
    }

    /**
     * @return void
     */
    public function test_wrong_guess_removes_candidate_and_adds_to_banned_ids(): void
    {
        $s = $this->seedScenarioKcdle();
        $ids = $s['ids'];

        $match = $this->createInitializedWhoisMatch((int) $ids['w1']);
        $chooser = (int) $match->state['chooser_user_id'];

        $this->chooseTurn((int) $match->id, $chooser, $chooser);

        $this->actingAs(User::findOrFail($chooser), 'sanctum')
            ->postJson("/api/pvp/matches/{$match->id}/round/action", [
                'action' => [
                    'type' => 'guess',
                    'player_id' => $ids['w2'],
                ],
            ])
            ->assertOk();

        $payload = $this->actingAs(User::findOrFail($chooser), 'sanctum')
            ->getJson("/api/pvp/matches/{$match->id}/round")
            ->assertOk()
            ->json();

        $candidateIds = (array) ($payload['round']['candidate_ids'] ?? []);
        $bannedIds = (array) ($payload['round']['banned_ids'] ?? []);

        $this->assertNotContains($ids['w2'], array_map('intval', $candidateIds));
        $this->assertContains($ids['w2'], array_map('intval', $bannedIds));
    }

    /**
     * @return array{ids:array{w1:int,w2:int,w3:int,w4:int},teams:array{aegis:int,g2:int,kc:int},roles:array{top:int,sup:int},games:array{a:int,b:int}}
     */
    private function seedScenarioKcdle(): array
    {
        $this->pvpSeedCountry('FR', 'France');
        $this->pvpSeedCountry('US', 'United States');
        $this->pvpSeedCountry('DE', 'Germany');

        $aegis = $this->pvpSeedTeam('aegis', 'AEGIS', 'AEG');
        $g2 = $this->pvpSeedTeam('g2', 'G2 Esports', 'G2');
        $kc = $this->pvpSeedTeam('kc', 'Karmine Corp', 'KC');

        $roleTop = Role::query()->create(['code' => 'TOP', 'label' => 'Toplaner']);
        $roleSup = Role::query()->create(['code' => 'SUP', 'label' => 'Support']);

        $gameA = Game::query()->create(['code' => 'A', 'name' => 'Game A']);
        $gameB = Game::query()->create(['code' => 'B', 'name' => 'Game B']);

        $p1 = $this->pvpCreatePlayer('p1', 'Player One', 'FR', '2000-01-01', (int) $roleTop->id);
        $p2 = $this->pvpCreatePlayer('p2', 'Player Two', 'US', '2010-01-01', (int) $roleTop->id);
        $p3 = $this->pvpCreatePlayer('p3', 'Player Three', 'DE', '2010-01-01', (int) $roleSup->id);
        $p4 = $this->pvpCreatePlayer('p4', 'Player Four', 'FR', '2004-01-01', (int) $roleSup->id);

        $w1 = $this->pvpCreateKcdleWrapper($p1, $gameA, $aegis, $g2, 2022, 5);
        $w2 = $this->pvpCreateKcdleWrapper($p2, $gameA, $kc, $g2, 2024, 1);
        $w3 = $this->pvpCreateKcdleWrapper($p3, $gameB, $aegis, $kc, 2021, 3);
        $w4 = $this->pvpCreateKcdleWrapper($p4, $gameB, $kc, $kc, 2023, 7);

        return [
            'ids' => [
                'w1' => (int) $w1->id,
                'w2' => (int) $w2->id,
                'w3' => (int) $w3->id,
                'w4' => (int) $w4->id,
            ],
            'teams' => [
                'aegis' => (int) $aegis->id,
                'g2' => (int) $g2->id,
                'kc' => (int) $kc->id,
            ],
            'roles' => [
                'top' => (int) $roleTop->id,
                'sup' => (int) $roleSup->id,
            ],
            'games' => [
                'a' => (int) $gameA->id,
                'b' => (int) $gameB->id,
            ],
        ];
    }

    /**
     * Creates a match through the PvP HTTP API flow and forces the secret player to a deterministic wrapper id.
     *
     * @param int $secretWrapperId
     *
     * @return PvpMatch
     */
    private function createInitializedWhoisMatch(int $secretWrapperId): PvpMatch
    {
        $u1 = User::factory()->create();
        $u2 = User::factory()->create();

        [$match] = $this->pvpCreateMatch('kcdle', 'whois', 1, $u1, $u2);

        $match->state = array_replace_recursive((array) $match->state, [
            'chooser_user_id' => (int) $u1->id,
        ]);
        $match->save();

        $this->actingAs($u1, 'sanctum')
            ->getJson("/api/pvp/matches/{$match->id}/round")
            ->assertOk();

        $match = PvpMatch::findOrFail($match->id);

        $candidateIds = (array) data_get($match->state ?? [], 'round_data.whois.candidate_ids', []);
        $this->assertNotEmpty($candidateIds);
        $this->assertContains($secretWrapperId, array_map('intval', $candidateIds));

        $state = (array) ($match->state ?? []);
        data_set($state, 'round_data.whois.secret_player_id', $secretWrapperId);
        data_set($state, 'round_data.whois.candidate_ids', array_values(array_unique(array_map('intval', $candidateIds))));
        $match->state = $state;
        $match->save();

        return PvpMatch::findOrFail($match->id);
    }

    /**
     * @param int $matchId
     * @param int $actingUserId
     * @param int $firstUserId
     *
     * @return void
     */
    private function chooseTurn(int $matchId, int $actingUserId, int $firstUserId): void
    {
        $u = User::findOrFail($actingUserId);

        $this->actingAs($u, 'sanctum')
            ->postJson("/api/pvp/matches/{$matchId}/round/action", [
                'action' => [
                    'type' => 'choose_turn',
                    'first_player_user_id' => $firstUserId,
                ],
            ])
            ->assertOk();
    }

    /**
     * @param int $matchId
     * @param int $actingUserId
     * @param array{key:string,op:string,value:mixed} $question
     *
     * @return void
     */
    private function ask(int $matchId, int $actingUserId, array $question): void
    {
        $u = User::findOrFail($actingUserId);

        $this->actingAs($u, 'sanctum')
            ->postJson("/api/pvp/matches/{$matchId}/round/action", [
                'action' => [
                    'type' => 'ask',
                    'question' => $question,
                ],
            ])
            ->assertOk();
    }

    /**
     * @param int $matchId
     * @param int $userId
     *
     * @return int[]
     */
    private function fetchCandidateIds(int $matchId, int $userId): array
    {
        $u = User::findOrFail($userId);

        $payload = $this->actingAs($u, 'sanctum')
            ->getJson("/api/pvp/matches/{$matchId}/round")
            ->assertOk()
            ->json();

        $candidateIds = (array) ($payload['round']['candidate_ids'] ?? []);

        return array_values(array_unique(array_map('intval', $candidateIds)));
    }

    /**
     * @param int[] $expected
     * @param int[] $actual
     *
     * @return void
     */
    private function assertSameIds(array $expected, array $actual): void
    {
        $expected = array_values(array_unique(array_map('intval', $expected)));
        $actual = array_values(array_unique(array_map('intval', $actual)));
        sort($expected);
        sort($actual);
        $this->assertSame($expected, $actual);
    }
}
