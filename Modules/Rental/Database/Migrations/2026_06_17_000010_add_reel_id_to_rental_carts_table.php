<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rental_carts', function (Blueprint $table) {
            if (!Schema::hasColumn('rental_carts', 'reel_id')) {
                $table->unsignedBigInteger('reel_id')->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('rental_carts', function (Blueprint $table) {
            if (Schema::hasColumn('rental_carts', 'reel_id')) {
                $table->dropColumn('reel_id');
            }
        });
    }
};
