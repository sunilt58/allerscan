<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Path on the public disk; the emoji icon is shown when there is no photo.
        Schema::table('products', fn (Blueprint $table) => $table->string('image_path')->nullable()->after('icon'));
    }

    public function down(): void
    {
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('image_path'));
    }
};
