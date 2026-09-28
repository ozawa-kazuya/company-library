<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('must_change_password')->default(false)->after('password');
        });

        $initial = (string) env('INITIAL_PASSWORD', 'password');

        if ($initial === '') {
            return;
        }

        foreach (DB::table('users')->orderBy('id')->get() as $user) {
            if (Hash::check($initial, (string) $user->password)) {
                DB::table('users')->where('id', $user->id)->update([
                    'must_change_password' => true,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('must_change_password');
        });
    }
};
