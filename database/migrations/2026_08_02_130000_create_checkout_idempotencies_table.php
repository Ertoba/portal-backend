<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkout_idempotencies', function (Blueprint $table) {
            $table->id();
            $table->string('idempotency_key', 100)->unique();
            $table->string('user_id', 64);
            $table->boolean('is_guest')->default(false);
            $table->unsignedBigInteger('module_id')->nullable()->index();
            $table->unsignedBigInteger('order_id')->nullable()->index();
            $table->char('request_fingerprint', 64);
            $table->string('status', 20)->default('pending')->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkout_idempotencies');
    }
};
