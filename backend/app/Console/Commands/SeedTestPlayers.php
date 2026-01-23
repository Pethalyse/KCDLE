<?php

namespace App\Console\Commands;

use App\Models\Country;
use App\Models\Game;
use App\Models\KcdlePlayer;
use App\Models\League;
use App\Models\LoldlePlayer;
use App\Models\Player;
use App\Models\Role;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Seed deterministic test players across KCDLE, LFLDLE and LECDLE with varied data.
 *
 * Constraints:
 * - players.slug is globally unique.
 * - loldle_players has a unique constraint on player_id (one league per player).
 *
 * This command creates:
 * - KCDLE players using slugs: test{n}
 * - LFLDLE players using slugs: test{n}_lfl
 * - LECDLE players using slugs: test{n}_lec
 *
 * KCDLE variety rules:
 * - game_id varies across existing games
 * - current_team_id varies across existing teams (excluding 'none')
 * - previous_team_before_kc_id varies across existing teams (excluding 'none' and KC)
 * - trophies_count varies
 * - first_official_year is always between 2021 and 2026
 * - all players always have a role_id (no null)
 *
 * LFL/LEC:
 * - lol_role comes from config('pvp.whois.lol_roles')
 * - team_id varies across existing teams (excluding 'none')
 */
class SeedTestPlayers extends Command
{
    /**
     * The console command signature.
     *
     * @var string
     */
    protected $signature = 'kcdle:seed-test-players {count=10 : Number of players to create} {--start=1 : Starting index for test slugs (test{n})}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create deterministic test players (test1, test2, ...) for KCDLE, LFLDLE and LECDLE with varied data.';

    /**
     * Execute the console command.
     *
     * @return int
     * @throws Throwable
     */
    public function handle(): int
    {
        $count = (int) $this->argument('count');
        $start = (int) $this->option('start');

        if ($count <= 0) {
            $this->error('count must be a positive integer.');
            return self::FAILURE;
        }

        if ($start <= 0) {
            $this->error('start must be a positive integer.');
            return self::FAILURE;
        }

        $kcTeamId = (int) Team::query()->where('slug', 'karmine_corp')->value('id');
        if ($kcTeamId <= 0) {
            $this->error('Missing Team with slug karmine_corp. Run database seeders first.');
            return self::FAILURE;
        }

        $lflLeagueId = (int) League::query()->where('code', 'LFL')->value('id');
        if ($lflLeagueId <= 0) {
            $this->error('Missing League with code LFL. Run database seeders first.');
            return self::FAILURE;
        }

        $lecLeagueId = (int) League::query()->where('code', 'LEC')->value('id');
        if ($lecLeagueId <= 0) {
            $this->error('Missing League with code LEC. Run database seeders first.');
            return self::FAILURE;
        }

        $countryCodes = Country::query()
            ->orderBy('code')
            ->pluck('code')
            ->filter(fn ($v) => is_string($v) && $v !== '')
            ->values()
            ->all();

        $roleIds = Role::query()
            ->orderBy('id')
            ->pluck('id')
            ->filter(fn ($v) => $v !== null)
            ->values()
            ->all();

        $gameIds = Game::query()
            ->orderBy('id')
            ->pluck('id')
            ->filter(fn ($v) => $v !== null)
            ->values()
            ->all();

        $teamIds = Team::query()
            ->where('slug', '!=', 'none')
            ->orderBy('id')
            ->pluck('id')
            ->filter(fn ($v) => $v !== null)
            ->values()
            ->all();

        $nonKcTeamIds = array_values(array_filter($teamIds, fn ($id) => (int) $id !== $kcTeamId));

        $lolRoles = (array) config('pvp.whois.lol_roles', []);
        $lolRoles = array_values(array_filter(array_map(static fn ($r) => strtoupper(trim((string) $r)), $lolRoles), static fn ($r) => $r !== ''));

        if (empty($countryCodes)) {
            $this->error('No countries found. Run database seeders first.');
            return self::FAILURE;
        }

        if (empty($roleIds)) {
            $this->error('No roles found. Run database seeders first.');
            return self::FAILURE;
        }

        if (empty($gameIds)) {
            $this->error('No games found. Run database seeders first.');
            return self::FAILURE;
        }

        if (empty($teamIds)) {
            $this->error('No teams found (excluding slug "none"). Run database seeders first.');
            return self::FAILURE;
        }

        if (empty($lolRoles)) {
            $this->error('No LoL roles configured in config/pvp.php (pvp.whois.lol_roles).');
            return self::FAILURE;
        }

        $createdPlayers = 0;
        $linkedKcdle = 0;
        $linkedLfl = 0;
        $linkedLec = 0;

        DB::transaction(function () use (
            $count,
            $start,
            $kcTeamId,
            $lflLeagueId,
            $lecLeagueId,
            $countryCodes,
            $roleIds,
            $gameIds,
            $teamIds,
            $nonKcTeamIds,
            $lolRoles,
            &$createdPlayers,
            &$linkedKcdle,
            &$linkedLfl,
            &$linkedLec
        ): void {
            for ($i = 0; $i < $count; $i++) {
                $n = $start + $i;

                $baseSlug = "test{$n}";
                $lflSlug = "test{$n}_lfl";
                $lecSlug = "test{$n}_lec";

                $baseCountryCode = (string) $this->pickFromList($countryCodes, $n);
                $baseRoleId = (int) $this->pickFromList($roleIds, $n);
                $baseBirthdate = $this->computeBirthdateForIndex($n);

                $basePlayer = Player::query()->firstOrCreate(
                    ['slug' => $baseSlug],
                    [
                        'display_name' => "Test {$n}",
                        'country_code' => $baseCountryCode,
                        'birthdate' => $baseBirthdate,
                        'role_id' => $baseRoleId,
                    ]
                );

                if ($basePlayer->wasRecentlyCreated) {
                    $createdPlayers++;
                } else {
                    $basePlayer->fill([
                        'display_name' => "Test {$n}",
                        'country_code' => $baseCountryCode,
                        'birthdate' => $baseBirthdate,
                        'role_id' => $baseRoleId,
                    ])->save();
                }

                $lflCountryCode = (string) $this->pickFromList($countryCodes, $n + 7);
                $lflRoleId = (int) $this->pickFromList($roleIds, $n + 7);
                $lflBirthdate = $this->computeBirthdateForIndex($n + 7);

                $lflPlayer = Player::query()->firstOrCreate(
                    ['slug' => $lflSlug],
                    [
                        'display_name' => "Test {$n} LFL",
                        'country_code' => $lflCountryCode,
                        'birthdate' => $lflBirthdate,
                        'role_id' => $lflRoleId,
                    ]
                );

                if ($lflPlayer->wasRecentlyCreated) {
                    $createdPlayers++;
                } else {
                    $lflPlayer->fill([
                        'display_name' => "Test {$n} LFL",
                        'country_code' => $lflCountryCode,
                        'birthdate' => $lflBirthdate,
                        'role_id' => $lflRoleId,
                    ])->save();
                }

                $lecCountryCode = (string) $this->pickFromList($countryCodes, $n + 13);
                $lecRoleId = (int) $this->pickFromList($roleIds, $n + 13);
                $lecBirthdate = $this->computeBirthdateForIndex($n + 13);

                $lecPlayer = Player::query()->firstOrCreate(
                    ['slug' => $lecSlug],
                    [
                        'display_name' => "Test {$n} LEC",
                        'country_code' => $lecCountryCode,
                        'birthdate' => $lecBirthdate,
                        'role_id' => $lecRoleId,
                    ]
                );

                if ($lecPlayer->wasRecentlyCreated) {
                    $createdPlayers++;
                } else {
                    $lecPlayer->fill([
                        'display_name' => "Test {$n} LEC",
                        'country_code' => $lecCountryCode,
                        'birthdate' => $lecBirthdate,
                        'role_id' => $lecRoleId,
                    ])->save();
                }

                $kcdleGameId = (int) $this->pickFromList($gameIds, $n);
                $kcdleCurrentTeamId = (int) $this->pickFromList($teamIds, $n + 3);

                $firstMatchAtKcYear = 2021 + (($n - 1) % 6);

                $trophiesCount = $this->computeTrophiesForIndex($n, $kcdleGameId, $kcdleCurrentTeamId);

                $previousTeamId = null;
                if (!empty($nonKcTeamIds)) {
                    $previousTeamId = (int) $this->pickFromList($nonKcTeamIds, $n + 11);
                }

                $kcdle = KcdlePlayer::query()->updateOrCreate(
                    [
                        'player_id' => (int) $basePlayer->getAttribute('id'),
                        'game_id' => $kcdleGameId,
                    ],
                    [
                        'current_team_id' => $kcdleCurrentTeamId,
                        'previous_team_before_kc_id' => $previousTeamId,
                        'first_official_year' => $firstMatchAtKcYear,
                        'trophies_count' => $trophiesCount,
                        'active' => true,
                    ]
                );

                if ($kcdle->wasRecentlyCreated) {
                    $linkedKcdle++;
                }

                $lflTeamId = (int) $this->pickFromList($teamIds, $n + 19);
                $lflLolRole = (string) $this->pickFromList($lolRoles, $n + 19);
                $lflSeason = $this->computeSeasonForIndex($n, 'LFL');

                $lfl = LoldlePlayer::query()->updateOrCreate(
                    ['player_id' => (int) $lflPlayer->getAttribute('id')],
                    [
                        'league_id' => $lflLeagueId,
                        'team_id' => $lflTeamId,
                        'lol_role' => $lflLolRole,
                        'season' => $lflSeason,
                        'active' => true,
                    ]
                );

                if ($lfl->wasRecentlyCreated) {
                    $linkedLfl++;
                }

                $lecTeamId = (int) $this->pickFromList($teamIds, $n + 29);
                $lecLolRole = (string) $this->pickFromList($lolRoles, $n + 29);
                $lecSeason = $this->computeSeasonForIndex($n, 'LEC');

                $lec = LoldlePlayer::query()->updateOrCreate(
                    ['player_id' => (int) $lecPlayer->getAttribute('id')],
                    [
                        'league_id' => $lecLeagueId,
                        'team_id' => $lecTeamId,
                        'lol_role' => $lecLolRole,
                        'season' => $lecSeason,
                        'active' => true,
                    ]
                );

                if ($lec->wasRecentlyCreated) {
                    $linkedLec++;
                }
            }
        });

        $this->info("Players created: {$createdPlayers}");
        $this->info("KCDLE links created: {$linkedKcdle}");
        $this->info("LFLDLE links created: {$linkedLfl}");
        $this->info("LECDLE links created: {$linkedLec}");
        $this->info("Done (range: test{$start}..test" . ($start + $count - 1) . ")");

        return self::SUCCESS;
    }

    /**
     * Pick a deterministic value from a list based on an integer index.
     *
     * @template T
     * @param array<int, T> $list
     * @param int $index
     * @return T|null
     */
    private function pickFromList(array $list, int $index): mixed
    {
        if (empty($list)) {
            return null;
        }

        $pos = ($index - 1) % count($list);
        if ($pos < 0) {
            $pos = 0;
        }

        return $list[$pos] ?? null;
    }

    /**
     * Compute a birthdate (YYYY-MM-DD) with varied ages.
     *
     * @param int $n
     * @return string
     */
    private function computeBirthdateForIndex(int $n): string
    {
        $ages = [17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 28, 30, 32, 35];
        $age = (int) $this->pickFromList($ages, $n);

        $year = (int) Carbon::now()->subYears($age)->year;
        $month = (($n - 1) % 12) + 1;
        $day = (($n - 1) % 28) + 1;

        return Carbon::create($year, $month, $day)->toDateString();
    }

    /**
     * Compute trophies_count with deterministic variety influenced by game/team ids.
     *
     * @param int $n
     * @param int $gameId
     * @param int $teamId
     * @return int
     */
    private function computeTrophiesForIndex(int $n, int $gameId, int $teamId): int
    {
        $seed = ($n * 7) + ($gameId * 3) + ($teamId * 5);

        $base = ($seed % 19);
        $bonus = 0;

        if ($n % 11 === 0) {
            $bonus = 18;
        } elseif ($n % 7 === 0) {
            $bonus = 9;
        } elseif ($n % 5 === 0) {
            $bonus = 5;
        }

        $value = $base + $bonus;

        return (int) max(0, min(60, $value));
    }

    /**
     * Compute a deterministic season label for Loldle.
     *
     * @param int $n
     * @param string $leagueCode
     * @return string|null
     */
    private function computeSeasonForIndex(int $n, string $leagueCode): ?string
    {
        if ($n % 8 === 0) {
            return null;
        }

        $years = [2022, 2023, 2024, 2025, 2026];
        $splits = ['winter', 'spring', 'summer'];

        $year = (int) $this->pickFromList($years, $n);
        $split = (string) $this->pickFromList($splits, $n);

        return strtolower($leagueCode) . '_' . $year . '_' . $split;
    }
}
