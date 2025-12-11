<?php

use App\Models\DailyGame;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pending_guesses', function (Blueprint $table) {
            $table->id();
            $table->string('anon_key', 128)->index();
            $table->foreignIdFor(DailyGame::class)
                ->constrained()
                ->cascadeOnDelete();
            $table->string('game', 10);
            $table->unsignedBigInteger('player_id');
            $table->unsignedInteger('guess_order');
            $table->boolean('correct')->default(false);
            $table->timestamps();
            $table->unique(['anon_key', 'daily_game_id', 'guess_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_guesses');
    }
};
