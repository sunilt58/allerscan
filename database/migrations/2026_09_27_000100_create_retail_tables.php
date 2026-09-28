<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->string('role')->default('cashier'));
        Schema::create('allergens', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name_ja');
            $table->string('name_en');
        });
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('barcode', 50)->unique();
            $table->string('name_ja');
            $table->string('name_en');
            $table->string('size', 100);
            $table->string('category', 40)->default('Food');
            $table->string('icon', 16)->default('📦');
            $table->unsignedInteger('price');
            $table->unsignedTinyInteger('tax_rate')->default(8);
            $table->string('information_status')->default('unknown');
            $table->string('source')->nullable();
            $table->date('verified_at')->nullable();
            $table->boolean('is_demo')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('allergen_product', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('allergen_id')->constrained()->cascadeOnDelete();
            $table->primary(['product_id', 'allergen_id']);
        });
        Schema::create('register_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->json('cart');
            $table->unsignedBigInteger('revision')->default(0);
            $table->string('language', 2)->default('ja');
            $table->boolean('speech_enabled')->default(false);
            $table->string('status')->default('open');
            $table->foreignId('last_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->uuid('register_session_id');
            $table->foreign('register_session_id')->references('id')->on('register_sessions');
            $table->unsignedBigInteger('checkout_revision');
            $table->foreignId('user_id')->constrained();
            $table->unsignedInteger('subtotal');
            $table->unsignedInteger('tax');
            $table->unsignedInteger('total');
            $table->string('payment_method');
            $table->boolean('is_simulated')->default(true);
            $table->timestamps();
            $table->unique(['register_session_id', 'checkout_revision']);
        });
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name_ja');
            $table->string('name_en');
            $table->string('barcode');
            $table->unsignedInteger('price');
            $table->unsignedSmallInteger('qty');
            $table->unsignedTinyInteger('tax_rate');
            $table->json('allergens');
            $table->string('information_status');
            $table->boolean('is_demo');
        });
    }

    public function down(): void
    {
        foreach (['sale_items', 'sales', 'register_sessions', 'allergen_product', 'products', 'allergens'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('role'));
    }
};
