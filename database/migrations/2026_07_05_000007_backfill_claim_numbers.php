<?php

use App\Models\Claim;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Claims created before the `number` column existed have a NULL number,
        // which shows as an empty "Claim ID: #". Assign each a unique number.
        Claim::withTrashed()
            ->whereNull('number')
            ->orderBy('id')
            ->get()
            ->each(function (Claim $claim) {
                $claim->number = Claim::generateNumber();
                $claim->saveQuietly();
            });
    }

    public function down(): void
    {
        // No-op: numbers are permanent identifiers.
    }
};
