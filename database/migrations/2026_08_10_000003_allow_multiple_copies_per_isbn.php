<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->unsignedSmallInteger('copy_number')->default(1)->after('isbn');
        });

        DB::table('books')->update(['copy_number' => 1]);

        Schema::table('books', function (Blueprint $table) {
            $table->dropUnique(['isbn']);
            $table->index('isbn');
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropIndex(['isbn']);
            $table->unique('isbn');
            $table->dropColumn('copy_number');
        });
    }
};
