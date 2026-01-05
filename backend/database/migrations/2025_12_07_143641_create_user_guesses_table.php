<?php

use App\Models\UserGameResult;
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
        Schema::create('user_guesses', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(UserGameResult::class)
                ->constrained()
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('guess_order');
            $table->unsignedBigInteger('player_id');
            $table->timestamps();
            $table->index(['user_game_result_id', 'guess_order']);
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_guesses');
    }
};
