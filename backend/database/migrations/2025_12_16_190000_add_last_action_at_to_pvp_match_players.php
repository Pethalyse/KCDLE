<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pvp_match_players', function (Blueprint $table): void {
            $table->timestamp('last_action_at')->nullable()->after('last_seen_at');
            $table->index(['match_id', 'last_action_at']);
        });
    }

    public function down(): void
    {
        Schema::table('pvp_match_players', function (Blueprint $table): void {
            $table->dropIndex(['match_id', 'last_action_at']);
            $table->dropColumn('last_action_at');
        });
    }
};
