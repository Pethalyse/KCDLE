<?php

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
        Schema::create('friend_groups', function (Blueprint $table) {
            $table->id();

            $table->foreignIdFor(User::class, 'owner_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('name');
            $table->string('slug')->unique();
            $table->string('join_code', 32)->unique();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('friend_groups');
    }
};
