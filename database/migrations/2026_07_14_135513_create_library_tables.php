<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. 既存の users テーブルに role（権限区分）カラムを追加
        Schema::table('users', function (Blueprint $table) {
            // パスワードの直後に role カラムを追加。デフォルトは一般社員('user')
            $table->string('role')->default('user')->after('password');
            $table->string('user_code')->unique()->after('role');
        });

        // 2. books（書籍マスター）テーブルの作成
        Schema::create('books', function (Blueprint $table) {
            $table->id();                                    // 書籍ID (PK)
            $table->string('title');                         // 書籍タイトル
            $table->string('author')->nullable();            // 著者名（空でもOK）
            $table->string('isbn')->unique()->nullable();    // ISBNコード（重複不可・空でもOK）
            $table->string('category')->nullable();          // カテゴリ（空でもOK）
            $table->string('cover')->nullable();
            $table->string('status')->default('available');  // 貸出ステータス (初期値: available)
            $table->timestamps();                            // 登録日時・更新日時
        });

        // 3. loans（貸出・返却履歴テーブル）の作成
        Schema::create('loans', function (Blueprint $table) {
            $table->id();                                    // 履歴ID (PK)
            // 外部キー：usersテーブルのidと連動（社員が消えたら履歴も消す設定）
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            // 外部キー：booksテーブルのidと連動（本が消えたら履歴も消す設定）
            $table->foreignId('book_id')->constrained()->onDelete('cascade');
            $table->timestamp('borrowed_at');                // 貸出日時
            $table->timestamp('returned_at')->nullable();    // 返却日時（空なら現在貸出中）
            $table->timestamps();                            // 登録日時・更新日時
        });
    }

    /**
     * マイグレーションのロールバック（テーブルの削除・変更の取り消し）
     */
    public function down(): void
    {
        // 作成した順番とは【逆の順番】で削除していきます
        Schema::dropIfExists('loans');
        Schema::dropIfExists('books');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
