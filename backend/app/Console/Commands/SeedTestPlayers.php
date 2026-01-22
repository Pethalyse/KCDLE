<?php

namespace App\Console\Commands;

use App\Models\Game;
use App\Models\KcdlePlayer;
use App\Models\League;
use App\Models\LoldlePlayer;
use App\Models\Player;
use App\Models\Team;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Seed deterministic test players across KCDLE, LFLDLE and LECDLE.
 *
 * Notes about constraints:
 * - players.slug is globally unique.
 * - loldle_players has a unique constraint on player_id (one league per player).
 *
 * Therefore this command creates:
 * - KCDLE players using slugs: test{n}
 * - LFLDLE players using slugs: test{n}_lfl
 * - LECDLE players using slugs: test{n}_lec
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
    protected $description = 'Create deterministic test players (test1, test2, ...) for KCDLE, LFLDLE and LECDLE.';

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

        $lolGameId = (int) Game::query()->where('code', 'LOL')->value('id');
        if ($lolGameId <= 0) {
            $this->error('Missing Game with code LOL. Run database seeders first.');
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

        $createdPlayers = 0;

        $linkedKcdle = 0;
        $linkedLfl = 0;
        $linkedLec = 0;

        DB::transaction(function () use ($count, $start, $lolGameId, $kcTeamId, $lflLeagueId, $lecLeagueId, &$createdPlayers, &$linkedKcdle, &$linkedLfl, &$linkedLec): void {
            for ($i = 0; $i < $count; $i++) {
                $n = $start + $i;

                $baseSlug = "test{$n}";
                $lflSlug = "test{$n}_lfl";
                $lecSlug = "test{$n}_lec";

                $basePlayer = Player::query()->firstOrCreate(
                    ['slug' => $baseSlug],
                    [
                        'display_name' => $baseSlug,
                        'country_code' => null,
                        'birthdate' => null,
                        'role_id' => null,
                    ]
                );

                if ($basePlayer->wasRecentlyCreated) {
                    $createdPlayers++;
                }

                $lflPlayer = Player::query()->firstOrCreate(
                    ['slug' => $lflSlug],
                    [
                        'display_name' => $lflSlug,
                        'country_code' => null,
                        'birthdate' => null,
                        'role_id' => null,
                    ]
                );

                if ($lflPlayer->wasRecentlyCreated) {
                    $createdPlayers++;
                }

                $lecPlayer = Player::query()->firstOrCreate(
                    ['slug' => $lecSlug],
                    [
                        'display_name' => $lecSlug,
                        'country_code' => null,
                        'birthdate' => null,
                        'role_id' => null,
                    ]
                );

                if ($lecPlayer->wasRecentlyCreated) {
                    $createdPlayers++;
                }

                $kcdle = KcdlePlayer::query()->updateOrCreate(
                    ['player_id' => (int) $basePlayer->getAttribute('id'), 'game_id' => $lolGameId],
                    [
                        'current_team_id' => $kcTeamId,
                        'previous_team_before_kc_id' => null,
                        'first_official_year' => 2000 + $n,
                        'trophies_count' => $n,
                        'active' => true,
                    ]
                );

                if ($kcdle->wasRecentlyCreated) {
                    $linkedKcdle++;
                }

                $lfl = LoldlePlayer::query()->updateOrCreate(
                    ['player_id' => (int) $lflPlayer->getAttribute('id')],
                    [
                        'league_id' => $lflLeagueId,
                        'team_id' => $kcTeamId,
                        'lol_role' => 'MID',
                        'season' => 'test',
                        'active' => true,
                    ]
                );

                if ($lfl->wasRecentlyCreated) {
                    $linkedLfl++;
                }

                $lec = LoldlePlayer::query()->updateOrCreate(
                    ['player_id' => (int) $lecPlayer->getAttribute('id')],
                    [
                        'league_id' => $lecLeagueId,
                        'team_id' => $kcTeamId,
                        'lol_role' => 'MID',
                        'season' => 'test',
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
}
