<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per unregistered barcode, so each barcode is researched by AI only once.
        Schema::create('ai_lookups', function (Blueprint $table) {
            $table->id();
            $table->string('barcode', 50)->unique();
            // pending → searching → found / not_found / failed
            $table->string('status', 20)->default('pending');
            // reading → found / not_label / failed, when a shopper sends a label photo
            $table->string('label_status', 20)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_lookups');
    }
};
