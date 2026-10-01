<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suggestions', function (Blueprint $table) {
            $table->id();
            // Suggestions are deleted with the account that made them.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('status', 20)->default('pending')->index();
            $table->string('barcode', 50)->nullable();
            $table->string('name_ja')->nullable();
            $table->string('name_en')->nullable();
            $table->string('size', 100)->nullable();
            $table->string('category', 40)->nullable();
            $table->json('allergen_codes')->nullable();
            $table->string('note', 500)->nullable();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suggestions');
    }
};
