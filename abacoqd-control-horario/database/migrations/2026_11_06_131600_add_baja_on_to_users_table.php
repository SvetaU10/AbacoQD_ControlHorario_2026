<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->date('baja_on')->nullable()->after('ends_on');
        });

        DB::table('users')->where('active', false)->orderBy('id')->each(function (object $persona): void {
            DB::table('users')->where('id', $persona->id)->update([
                'baja_on' => $persona->baja_on ?? $persona->ends_on,
                'ends_on' => null,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('baja_on');
        });
    }
};
