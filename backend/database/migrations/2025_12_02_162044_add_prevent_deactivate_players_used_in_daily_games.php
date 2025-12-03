<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared("
            -- Empêcher de désactiver un kcdle_player utilisé dans daily_games (game = 'kcdle')
            CREATE OR REPLACE FUNCTION prevent_deactivate_kcdle_player()
            RETURNS trigger AS $$
            BEGIN
                -- On ne check que si on passe de true à false
                IF OLD.active = true AND NEW.active = false THEN
                    IF EXISTS (
                        SELECT 1
                        FROM daily_games
                        WHERE game = 'kcdle'
                          AND player_id = OLD.id
                          AND selected_for_date >= CURRENT_DATE
                    ) THEN
                        RAISE EXCEPTION
                            'Cannot deactivate KCDLE player %: still used in daily games for today or future.',
                            OLD.id;
                    END IF;
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER trg_prevent_deactivate_kcdle_player
            BEFORE UPDATE ON kcdle_players
            FOR EACH ROW
            EXECUTE FUNCTION prevent_deactivate_kcdle_player();
        ");

        DB::unprepared("
            -- Empêcher de désactiver un loldle_player utilisé dans daily_games (LFLDLE / LECDLE)
            CREATE OR REPLACE FUNCTION prevent_deactivate_loldle_player()
            RETURNS trigger AS $$
            BEGIN
                IF OLD.active = true AND NEW.active = false THEN
                    IF EXISTS (
                        SELECT 1
                        FROM daily_games
                        WHERE game IN ('lfldle', 'lecdle')
                          AND player_id = OLD.id
                          AND selected_for_date >= CURRENT_DATE
                    ) THEN
                        RAISE EXCEPTION
                            'Cannot deactivate LOLDLE player %: still used in daily games for today or future.',
                            OLD.id;
                    END IF;
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER trg_prevent_deactivate_loldle_player
            BEFORE UPDATE ON loldle_players
            FOR EACH ROW
            EXECUTE FUNCTION prevent_deactivate_loldle_player();
        ");
    }

    public function down(): void
    {
        DB::unprepared("
            DROP TRIGGER IF EXISTS trg_prevent_deactivate_kcdle_player ON kcdle_players;
            DROP FUNCTION IF EXISTS prevent_deactivate_kcdle_player();
        ");

        DB::unprepared("
            DROP TRIGGER IF EXISTS trg_prevent_deactivate_loldle_player ON loldle_players;
            DROP FUNCTION IF EXISTS prevent_deactivate_loldle_player();
        ");
    }
};
