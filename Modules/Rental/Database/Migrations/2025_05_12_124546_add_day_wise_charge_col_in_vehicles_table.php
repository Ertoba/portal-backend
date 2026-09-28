<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddDayWiseChargeColInVehiclesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->double('trip_day_wise')->default(0)->after('trip_distance');
            $table->decimal('day_wise_price',23,8)->default(0.00)->after('trip_day_wise');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn('trip_day_wise');
            $table->dropColumn('day_wise_price');
        });
    }
}
