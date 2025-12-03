<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('roles')->insert([
            ['code' => 'player', 'label' => 'Player', 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'coach', 'label' => 'Coach', 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'ceo', 'label' => 'CEO', 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'caster', 'label' => 'Caster', 'created_at' => now(), 'updated_at' => now()],
            ['code' => 'content_creator', 'label' => 'Content Creator', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
