<?php

namespace Database\Seeders;

use App\Models\Allergen;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            if (! config('demo.allow_seed')) {
                throw new \RuntimeException('Demo data may only be seeded locally or in tests. Set DEMO_ALLOW_SEED=true for a public demo deployment.');
            }
            if (config('demo.password') === config('demo.default_password')) {
                throw new \RuntimeException('Set a new DEMO_PASSWORD before seeding a deployed demo; the default password is published in the README.');
            }
        }
        $user = User::firstOrNew(['email' => 'demo@allerscan.test']);
        if (! $user->exists) {
            $user->name = 'Demo staff';
            $user->password = config('demo.password');
            $user->role = 'admin';
            $user->save();
        }
        foreach ([['milk', '乳', 'Milk'], ['wheat', '小麦', 'Wheat'], ['egg', '卵', 'Egg'], ['soy', '大豆', 'Soy'], ['peanut', '落花生', 'Peanut'], ['shrimp', 'えび', 'Shrimp'], ['walnut', 'くるみ', 'Walnut'], ['sesame', 'ごま', 'Sesame'], ['cashew', 'カシューナッツ', 'Cashew']] as [$code, $ja, $en]) {
            Allergen::firstOrCreate(['code' => $code], ['name_ja' => $ja, 'name_en' => $en]);
        }
        // Fictional products and declarations; these are not real label information.
        $samples = [
            ['DEMO001', 'まいにちミルク', 'Everyday milk', '500 ml', 'Drinks', '🥛', 180, ['milk']],
            ['DEMO002', 'ふんわり食パン', 'Soft white bread', '6 slices', 'Food', '🍞', 240, ['wheat', 'milk', 'egg']],
            ['DEMO003', 'しおおにぎり', 'Salted rice ball', '1 piece', 'Food', '🍙', 130, []],
            ['DEMO004', 'ほっと緑茶', 'Green tea', '500 ml', 'Drinks', '🍵', 150, []],
            ['DEMO005', 'ミルクチョコ', 'Milk chocolate', '45 g', 'Snacks', '🍫', 160, ['milk', 'soy']],
            ['DEMO006', 'しょうゆヌードル', 'Soy sauce noodles', '1 cup', 'Food', '🍜', 210, ['wheat', 'soy', 'egg']],
            ['DEMO007', 'りんごジュース', 'Apple juice', '200 ml', 'Drinks', '🧃', 120, []],
            ['DEMO008', 'ナッツクッキー', 'Nut cookies', '6 pieces', 'Snacks', '🍪', 280, ['wheat', 'egg', 'milk', 'peanut']],
            ['DEMO009', '季節のサラダ', 'Seasonal salad', '1 bowl', 'Food', '🥗', 320, null],
            ['DEMO010', 'ハンドソープ', 'Hand soap', '250 ml', 'Daily', '🧴', 380, null],
        ];
        foreach ($samples as [$barcode, $ja, $en, $size, $category, $icon, $price, $codes]) {
            $product = Product::firstOrCreate(['barcode' => $barcode], [
                'name_ja' => $ja, 'name_en' => $en, 'size' => $size, 'category' => $category, 'icon' => $icon,
                'price' => $price, 'tax_rate' => $category === 'Daily' ? 10 : 8, 'is_demo' => true,
                'information_status' => $codes === null ? 'unknown' : 'recorded',
                'source' => 'Fictional graduation demo — not a real product label',
                'verified_at' => $codes === null ? null : now()->toDateString(),
            ]);
            if ($product->wasRecentlyCreated) {
                $product->allergens()->sync(Allergen::whereIn('code', $codes ?? [])->pluck('id'));
            }
        }
    }
}
