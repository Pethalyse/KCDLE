<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('loldle_players', function (Blueprint $table) {
            $table->dropUnique('loldle_players_league_id_player_id_unique');
            $table->unique('player_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loldle_players', function (Blueprint $table) {
            $table->dropUnique('loldle_players_player_id_unique');
            $table->unique(['league_id', 'player_id']);
        });
    }
};
