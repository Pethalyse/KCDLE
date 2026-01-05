<?php

use App\Models\DailyGame;
use App\Models\User;
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
        Schema::create('user_game_results', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(User::class)
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignIdFor(DailyGame::class)
                ->constrained()
                ->cascadeOnDelete();
            $table->string('game', 16);
            $table->unsignedSmallInteger('guesses_count')->default(0);
            $table->timestamp('won_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'daily_game_id']);
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_game_results');
    }
};
