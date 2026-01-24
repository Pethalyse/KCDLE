<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds profile customization columns:
     * - avatar_path: relative path on the public disk (storage/app/public), nullable.
     * - avatar_frame_color: frame color used by the frontend (hex string), defaults to blue.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar_path')->nullable()->after('remember_token');
            $table->string('avatar_frame_color', 32)->default('#3B82F6')->after('avatar_path');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['avatar_path', 'avatar_frame_color']);
        });
    }
};
