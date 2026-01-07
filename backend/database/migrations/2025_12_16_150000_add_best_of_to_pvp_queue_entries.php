<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pvp_queue_entries', function (Blueprint $table): void {
            $table->unsignedTinyInteger('best_of')->default(5)->after('game');
            $table->index(['game', 'best_of', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('pvp_queue_entries', function (Blueprint $table): void {
            $table->dropIndex(['game', 'best_of', 'created_at']);
            $table->dropColumn('best_of');
        });
    }
};
