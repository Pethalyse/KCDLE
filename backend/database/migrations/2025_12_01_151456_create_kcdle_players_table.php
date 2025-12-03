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
        Schema::create('kcdle_players', function (Blueprint $table) {
            $table->id();

            $table->foreignId('player_id')
                ->constrained('players')
                ->cascadeOnDelete();

            $table->foreignId('game_id')
                ->constrained('games')
                ->cascadeOnDelete();

            $table->foreignId('current_team_id')
                ->nullable()
                ->constrained('teams')
                ->nullOnDelete();

            $table->foreignId('previous_team_before_kc_id')
                ->nullable()
                ->constrained('teams')
                ->nullOnDelete();

            $table->unsignedSmallInteger('first_official_year');
            $table->unsignedSmallInteger('trophies_count')->default(0);

            $table->boolean('active')->default(true);

            $table->timestamps();

            $table->unique(['player_id', 'game_id']);
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kcdle_players');
    }
};
