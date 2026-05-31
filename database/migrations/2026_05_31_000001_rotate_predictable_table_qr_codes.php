<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        $usedCodes = DB::table('tables')->pluck('qr_code')->filter()->all();

        DB::table('tables')
            ->select(['id', 'qr_code'])
            ->orderBy('id')
            ->chunkById(100, function ($tables) use (&$usedCodes): void {
                foreach ($tables as $table) {
                    if (is_string($table->qr_code) && preg_match('/^tbl_[A-Za-z0-9]{40}$/', $table->qr_code) === 1) {
                        continue;
                    }

                    do {
                        $code = 'tbl_' . Str::random(40);
                    } while (in_array($code, $usedCodes, true));

                    $usedCodes[] = $code;

                    DB::table('tables')
                        ->where('id', $table->id)
                        ->update([
                            'qr_code' => $code,
                            'updated_at' => now(),
                        ]);
                }
            });
    }

    public function down(): void
    {
        // Rotated public QR tokens cannot be restored safely.
    }
};
