<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pvp_lobbies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('host_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('guest_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('game', 32);
            $table->unsignedTinyInteger('best_of')->default(5);
            $table->string('status', 32)->default('open');
            $table->string('code', 16)->unique();
            $table->foreignId('match_id')->nullable()->constrained('pvp_matches')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['game', 'status']);
            $table->index(['host_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pvp_lobbies');
    }
};
