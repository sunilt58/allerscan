<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The seeded team account and sample records were named for the graduation demo.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('users')->where('email', 'admin@allerscan.test')->doesntExist()) {
            DB::table('users')->where('email', 'demo@allerscan.test')
                ->update(['email' => 'admin@allerscan.test', 'name' => 'AllerScan team']);
        }
        DB::table('products')->where('source', 'Fictional graduation demo — not a real product label')
            ->update(['source' => 'Sample record — not a real product label']);
    }

    public function down(): void
    {
        DB::table('products')->where('source', 'Sample record — not a real product label')
            ->update(['source' => 'Fictional graduation demo — not a real product label']);
        if (DB::table('users')->where('email', 'demo@allerscan.test')->doesntExist()) {
            DB::table('users')->where('email', 'admin@allerscan.test')
                ->update(['email' => 'demo@allerscan.test', 'name' => 'Demo staff']);
        }
    }
};
