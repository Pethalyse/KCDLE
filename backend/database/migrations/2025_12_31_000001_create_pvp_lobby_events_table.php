<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pvp_lobby_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lobby_id')->constrained('pvp_lobbies')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 64);
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['lobby_id', 'id']);
            $table->index(['lobby_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pvp_lobby_events');
    }
};
