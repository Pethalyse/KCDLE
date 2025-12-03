<?php

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
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('display_name');
            $table->string('short_name', 32)->nullable();
            $table->string('country_code', 2)->nullable();
            $table->foreign('country_code')
                ->references('code')
                ->on('countries')
                ->nullOnDelete();


            $table->boolean('is_karmine_corp')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teams');
    }
};
