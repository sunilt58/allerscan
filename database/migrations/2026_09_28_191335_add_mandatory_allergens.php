<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Reference data: the eight allergens Japan requires on food labels (特定原材料).
 * Stored as a migration so every environment has them, including production where demo seeding is off.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('allergens')->insertOrIgnore([
            ['code' => 'shrimp', 'name_ja' => 'えび', 'name_en' => 'Shrimp'],
            ['code' => 'crab', 'name_ja' => 'かに', 'name_en' => 'Crab'],
            ['code' => 'walnut', 'name_ja' => 'くるみ', 'name_en' => 'Walnut'],
            ['code' => 'wheat', 'name_ja' => '小麦', 'name_en' => 'Wheat'],
            ['code' => 'buckwheat', 'name_ja' => 'そば', 'name_en' => 'Buckwheat'],
            ['code' => 'egg', 'name_ja' => '卵', 'name_en' => 'Egg'],
            ['code' => 'milk', 'name_ja' => '乳', 'name_en' => 'Milk'],
            ['code' => 'peanut', 'name_ja' => '落花生', 'name_en' => 'Peanut'],
        ]);
    }

    public function down(): void
    {
        DB::table('allergens')->whereIn('code', ['crab', 'buckwheat'])->delete();
    }
};
