<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for the Rental admin dashboard and trip reports.
 *
 * Lives in the module, not app/database/migrations, because trips and trip_transactions are
 * created here. An app-level migration would be recorded as run while the module is disabled
 * and would then never create these once it was enabled -- the reports would silently return
 * to their unindexed behaviour with nothing in the log.
 *
 * Measured:
 *   trips (schedule_at, trip_status)                trip report ORDER BY: table scan + sort of
 *                                                   1.96M rows (cost 238993) -> cost 0.556
 *   trips (provider_id, trip_status, trip_amount)   provider summary group-by: 12,514ms -> 412ms
 *                                                   (the amount column makes the scan covering)
 *   trip_transactions (trip_id)                     eager-loading one page: 1008ms -> gone; the
 *                                                   table had only a primary key
 *   trips (is_guest, user_id)                       dashboard top customers
 *
 * The narrower (provider_id, trip_status) is a prefix of the amount index and is kept
 * deliberately -- dropping an index changes query plans, which is a separate decision.
 */
return new class extends Migration
{
    private const INDEXES = [
        'trips' => [
            'trips_guest_user_index' => ['is_guest', 'user_id'],
            'trips_provider_status_index' => ['provider_id', 'trip_status'],
            'trips_schedule_status_index' => ['schedule_at', 'trip_status'],
            'trips_module_schedule_status_index' => ['module_id', 'schedule_at', 'trip_status'],
            'trips_provider_status_amount_index' => ['provider_id', 'trip_status', 'trip_amount'],
        ],
        'trip_transactions' => [
            'trip_transactions_trip_id_index' => ['trip_id'],
        ],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($indexes as $name => $columns) {
                if ($this->indexExists($table, $name)) {
                    continue;
                }

                Schema::table($table, function (Blueprint $blueprint) use ($columns, $name) {
                    $blueprint->index($columns, $name);
                });
            }
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach (array_keys($indexes) as $name) {
                if (! $this->indexExists($table, $name)) {
                    continue;
                }

                Schema::table($table, function (Blueprint $blueprint) use ($name) {
                    $blueprint->dropIndex($name);
                });
            }
        }
    }

    private function indexExists(string $table, string $name): bool
    {
        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', $table)
            ->where('index_name', $name)
            ->exists();
    }
};
