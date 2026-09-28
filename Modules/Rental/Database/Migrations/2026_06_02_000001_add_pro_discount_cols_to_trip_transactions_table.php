<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('trip_transactions', 'pro_discount')) {
                $table->double('pro_discount', 23, 8)->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('trip_transactions', function (Blueprint $table) {
            $table->dropColumn('pro_discount');
        });
    }
};
