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
        Schema::create('loldle_players', function (Blueprint $table) {
            $table->id();

            $table->foreignId('league_id')
                ->constrained('leagues')
                ->cascadeOnDelete();

            $table->foreignId('player_id')
                ->constrained('players')
                ->cascadeOnDelete();

            $table->foreignId('team_id')
                ->constrained('teams')
                ->cascadeOnDelete();

            $table->string('lol_role', 16);
            $table->string('season', 32)->nullable();
            $table->boolean('active')->default(true);

            $table->timestamps();

            $table->unique(['league_id', 'player_id']);
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loldle_players');
    }
};
