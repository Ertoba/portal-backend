<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const METHOD_NAME = 'Keepz Split Receiver';

    public function up(): void
    {
        if (!Schema::hasTable('withdrawal_methods')) {
            return;
        }

        DB::table('withdrawal_methods')->updateOrInsert(
            ['method_name' => self::METHOD_NAME],
            [
                'method_fields' => json_encode([
                    [
                        'input_type' => 'text',
                        'input_name' => 'keepz_receiver_type',
                        'placeholder' => 'BRANCH or IBAN',
                        'is_required' => 1,
                    ],
                    [
                        'input_type' => 'text',
                        'input_name' => 'keepz_receiver_identifier',
                        'placeholder' => 'Keepz branch UUID or GE IBAN',
                        'is_required' => 1,
                    ],
                ]),
                'is_active' => 1,
                'is_default' => 0,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        if (!Schema::hasTable('withdrawal_methods')) {
            return;
        }

        DB::table('withdrawal_methods')
            ->where('method_name', self::METHOD_NAME)
            ->delete();
    }
};
