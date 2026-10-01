<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Japan's recommended labeling items (特定原材料に準ずるもの), added to the mandatory eight.
 * Pistachio is included ahead of its planned addition to the official list.
 */
return new class extends Migration
{
    /**
     * @var list<array{code: string, name_ja: string, name_en: string}>
     */
    private array $allergens = [
        ['code' => 'almond', 'name_ja' => 'アーモンド', 'name_en' => 'Almond'],
        ['code' => 'abalone', 'name_ja' => 'あわび', 'name_en' => 'Abalone'],
        ['code' => 'squid', 'name_ja' => 'いか', 'name_en' => 'Squid'],
        ['code' => 'salmon_roe', 'name_ja' => 'いくら', 'name_en' => 'Salmon roe'],
        ['code' => 'orange', 'name_ja' => 'オレンジ', 'name_en' => 'Orange'],
        ['code' => 'cashew', 'name_ja' => 'カシューナッツ', 'name_en' => 'Cashew'],
        ['code' => 'kiwi', 'name_ja' => 'キウイフルーツ', 'name_en' => 'Kiwi fruit'],
        ['code' => 'beef', 'name_ja' => '牛肉', 'name_en' => 'Beef'],
        ['code' => 'sesame', 'name_ja' => 'ごま', 'name_en' => 'Sesame'],
        ['code' => 'salmon', 'name_ja' => 'さけ', 'name_en' => 'Salmon'],
        ['code' => 'mackerel', 'name_ja' => 'さば', 'name_en' => 'Mackerel'],
        ['code' => 'soy', 'name_ja' => '大豆', 'name_en' => 'Soy'],
        ['code' => 'chicken', 'name_ja' => '鶏肉', 'name_en' => 'Chicken'],
        ['code' => 'banana', 'name_ja' => 'バナナ', 'name_en' => 'Banana'],
        ['code' => 'pork', 'name_ja' => '豚肉', 'name_en' => 'Pork'],
        ['code' => 'macadamia', 'name_ja' => 'マカダミアナッツ', 'name_en' => 'Macadamia nut'],
        ['code' => 'peach', 'name_ja' => 'もも', 'name_en' => 'Peach'],
        ['code' => 'yam', 'name_ja' => 'やまいも', 'name_en' => 'Yam'],
        ['code' => 'apple', 'name_ja' => 'りんご', 'name_en' => 'Apple'],
        ['code' => 'gelatin', 'name_ja' => 'ゼラチン', 'name_en' => 'Gelatin'],
        ['code' => 'pistachio', 'name_ja' => 'ピスタチオ', 'name_en' => 'Pistachio'],
    ];

    public function up(): void
    {
        DB::table('allergens')->insertOrIgnore($this->allergens);
    }

    public function down(): void
    {
        // Soy, sesame, and cashew may predate this migration through local seeding, so they stay.
        DB::table('allergens')
            ->whereIn('code', array_diff(array_column($this->allergens, 'code'), ['soy', 'sesame', 'cashew']))
            ->delete();
    }
};
