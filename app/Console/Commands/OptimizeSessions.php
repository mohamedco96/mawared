<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OptimizeSessions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'optimize:sessions {--prune : Delete expired sessions}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Analyze and optimize the sessions table';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $table = config('session.table', 'sessions');
        $driver = config('session.driver');

        if ($driver !== 'database') {
            $this->error("Session driver is configured as '{$driver}', not 'database'.");

            return;
        }

        if (! Schema::hasTable($table)) {
            $this->error("Table '{$table}' does not exist.");

            return;
        }

        $this->info("Analyzing '{$table}' table...");

        try {
            $status = DB::select("SHOW TABLE STATUS LIKE '{$table}'");
            if (! empty($status)) {
                $row = $status[0];
                $this->info('Engine: '.$row->Engine);
                $this->info('Data Length: '.round($row->Data_length / 1024 / 1024, 2).' MB');
                $this->info('Index Length: '.round($row->Index_length / 1024 / 1024, 2).' MB');
                $this->info('Rows (Approx): '.number_format($row->Rows));
            }
        } catch (\Exception $e) {
        }

        $total = DB::table($table)->count();
        $this->info('Total Sessions: '.number_format($total));

        $lifetime = config('session.lifetime', 120) * 60; // in seconds
        $expiredTimestamp = time() - $lifetime;

        $expired = DB::table($table)->where('last_activity', '<', $expiredTimestamp)->count();
        $this->warn('Expired Sessions: '.number_format($expired));

        if ($this->option('prune')) {
            if ($expired > 0) {
                $this->info('Pruning expired sessions...');
                $deleted = DB::table($table)->where('last_activity', '<', $expiredTimestamp)->delete();
                $this->info("Deleted {$deleted} expired sessions.");
            } else {
                $this->info('No expired sessions to prune.');
            }
        } else {
            if ($expired > 0) {
                $this->comment('Run with --prune to delete expired sessions.');
            }
        }

        // Check payload sizes
        $this->info('Checking payload sizes (top 5 largest)...');
        $largest = DB::table($table)
            ->selectRaw('id, LENGTH(payload) as size')
            ->orderBy('size', 'desc')
            ->limit(5)
            ->get();

        foreach ($largest as $row) {
            $sizeKb = round($row->size / 1024, 2);
            $this->line("Session ID: {$row->id} - Size: {$sizeKb} KB");
            if ($sizeKb > 100) {
                $this->error("Warning: Session {$row->id} is very large ({$sizeKb} KB). This causes slow reads.");
            }
        }

        // Check Index
        $this->info('Checking Indexes...');

        try {
            $rawIndexes = DB::select("SHOW INDEX FROM `{$table}`");
            $pkExists = false;
            foreach ($rawIndexes as $idx) {
                if ($idx->Key_name === 'PRIMARY') {
                    $pkExists = true;
                }
            }

            if ($pkExists) {
                $this->info("PRIMARY KEY index exists on 'id'.");
            } else {
                $this->error("CRITICAL: PRIMARY KEY index is MISSING on 'id'.");
            }
        } catch (\Exception $e) {
            $this->warn('Could not verify indexes: '.$e->getMessage());
        }
    }
}
