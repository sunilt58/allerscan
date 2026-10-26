<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pages and photos an AI draft was based on, as a list of {title, url}, for the team's review.
        Schema::table('products', fn (Blueprint $table) => $table->json('ai_sources')->nullable()->after('source'));
    }

    public function down(): void
    {
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('ai_sources'));
    }
};
