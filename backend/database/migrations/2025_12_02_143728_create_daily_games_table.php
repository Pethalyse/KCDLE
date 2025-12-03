<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('daily_games', function (Blueprint $table) {
            $table->id();
            $table->string('game', 10);
            $table->unsignedBigInteger('player_id');
            $table->date('selected_for_date');
            $table->unsignedInteger('solvers_count')->default(0);
            $table->unsignedInteger('total_guesses')->default(0);
            $table->timestamps();

            $table->unique(['game', 'selected_for_date']);
        });

        DB::unprepared("
            CREATE OR REPLACE FUNCTION check_daily_game_player()
            RETURNS trigger AS $$
            BEGIN
                -- KCDLE : joueur doit exister dans kcdle_players
                IF NEW.game = 'kcdle' THEN
                    IF NOT EXISTS (
                        SELECT 1
                        FROM kcdle_players
                        WHERE id = NEW.player_id
                        AND active = true
                    ) THEN
                        RAISE EXCEPTION
                            'Invalid or inactive player_id % for KCDLE', NEW.player_id;
                    END IF;
                END IF;

                -- LFLDLE : joueur doit être dans loldle_players avec league LFL ET actif
                IF NEW.game = 'lfldle' THEN
                    IF NOT EXISTS (
                        SELECT 1
                        FROM loldle_players lp
                        JOIN leagues l ON l.id = lp.league_id
                        WHERE lp.id = NEW.player_id
                        AND l.code = 'LFL'
                        AND lp.active = true
                    ) THEN
                        RAISE EXCEPTION
                            'Invalid or inactive player_id % for LFLDLE', NEW.player_id;
                    END IF;
                END IF;

                -- LECDLE : joueur doit être dans loldle_players avec league LEC ET actif
                IF NEW.game = 'lecdle' THEN
                    IF NOT EXISTS (
                        SELECT 1
                        FROM loldle_players lp
                        JOIN leagues l ON l.id = lp.league_id
                        WHERE lp.id = NEW.player_id
                        AND l.code = 'LEC'
                        AND lp.active = true
                    ) THEN
                        RAISE EXCEPTION
                            'Invalid or inactive player_id % for LECDLE', NEW.player_id;
                    END IF;
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER trg_check_daily_game_player
            BEFORE INSERT OR UPDATE ON daily_games
            FOR EACH ROW EXECUTE FUNCTION check_daily_game_player();
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_games');
        DB::unprepared("
            DROP TRIGGER IF EXISTS trg_check_daily_game_player ON daily_games;
            DROP FUNCTION IF EXISTS check_daily_game_player();
        ");
    }
};
