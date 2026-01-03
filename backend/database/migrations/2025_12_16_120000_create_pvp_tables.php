<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pvp_queue_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('game', 32);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['user_id']);
            $table->index(['game', 'created_at']);
        });

        Schema::create('pvp_matches', function (Blueprint $table): void {
            $table->id();
            $table->string('game', 32);
            $table->string('status', 32)->default('active');
            $table->unsignedTinyInteger('best_of')->default(5);
            $table->unsignedTinyInteger('current_round')->default(1);
            $table->json('rounds');
            $table->json('state')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['game', 'status']);
        });

        Schema::create('pvp_match_players', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('match_id')->constrained('pvp_matches')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('seat');
            $table->unsignedTinyInteger('points')->default(0);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['match_id', 'seat']);
            $table->unique(['match_id', 'user_id']);
            $table->index(['user_id']);
        });

        Schema::create('pvp_match_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('match_id')->constrained('pvp_matches')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 64);
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['match_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pvp_match_events');
        Schema::dropIfExists('pvp_match_players');
        Schema::dropIfExists('pvp_matches');
        Schema::dropIfExists('pvp_queue_entries');
    }
};
