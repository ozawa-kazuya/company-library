<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('books', 'author')) {
            return;
        }

        Schema::table('books', function (Blueprint $table) {
            $table->dropColumn('author');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('books', 'author')) {
            return;
        }

        Schema::table('books', function (Blueprint $table) {
            $table->string('author')->nullable()->after('title');
        });
    }
};
