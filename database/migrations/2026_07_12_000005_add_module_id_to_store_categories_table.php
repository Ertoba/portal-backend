<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_categories', function (Blueprint $table) {
            $table->foreignId('module_id')->nullable()->after('store_id')->constrained('modules')->nullOnDelete();
        });

    }

    public function down(): void
    {
        Schema::table('store_categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('module_id');
        });
    }
};
