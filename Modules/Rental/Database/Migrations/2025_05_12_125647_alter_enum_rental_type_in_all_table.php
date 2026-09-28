<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AlterEnumRentalTypeInAllTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
      public function up(): void
    {
        DB::statement("ALTER TABLE rental_cart_user_data MODIFY rental_type ENUM('hourly', 'distance_wise', 'day_wise') DEFAULT 'hourly'");
        DB::statement("ALTER TABLE trips MODIFY trip_type ENUM('hourly', 'distance_wise', 'day_wise') DEFAULT 'hourly'");
        DB::statement("ALTER TABLE trip_details MODIFY rental_type ENUM('hourly', 'distance_wise', 'day_wise') DEFAULT 'hourly'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
     public function down(): void
    {
        DB::statement("ALTER TABLE rental_cart_user_data MODIFY rental_type ENUM('hourly', 'distance_wise') DEFAULT 'hourly'");
        DB::statement("ALTER TABLE trips MODIFY trip_type ENUM('hourly', 'distance_wise') DEFAULT 'hourly'");
        DB::statement("ALTER TABLE trip_details MODIFY rental_type ENUM('hourly', 'distance_wise') DEFAULT 'hourly'");
    }
}
