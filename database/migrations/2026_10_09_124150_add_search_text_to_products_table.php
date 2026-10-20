<?php

use App\Models\Product;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            // Readings and other names the team adds, such as かきのたね for 柿の種.
            $table->string('keywords')->nullable()->after('name_en');
            // Names, keywords and barcode in one normalized string, so partial and differently written searches match.
            $table->text('search_text')->nullable()->after('keywords');
        });

        DB::table('products')->orderBy('id')->each(function (object $product): void {
            DB::table('products')->where('id', $product->id)->update([
                'search_text' => Product::searchTextFor($product->name_ja, $product->name_en, null, $product->barcode),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn(['keywords', 'search_text']));
    }
};
