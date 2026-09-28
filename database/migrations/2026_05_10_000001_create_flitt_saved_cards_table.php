<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flitt_saved_cards', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id')->index();
            $table->string('gateway', 40)->default('flitt');
            $table->string('rectoken', 255);
            $table->string('masked_card', 80)->nullable();
            $table->string('card_type', 40)->nullable();
            $table->string('card_bin', 20)->nullable();
            $table->string('rectoken_lifetime', 80)->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['customer_id', 'gateway', 'rectoken'], 'flitt_saved_cards_unique_token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flitt_saved_cards');
    }
};
