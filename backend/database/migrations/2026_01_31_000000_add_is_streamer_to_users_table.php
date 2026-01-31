<?php

/**
 * Add the is_streamer flag to the users table.
 *
 * Streamer users are highlighted in the frontend (e.g., purple nickname + Twitch icon).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_streamer')->default(false)->after('is_admin')->index();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['is_streamer']);
            $table->dropColumn('is_streamer');
        });
    }
};
