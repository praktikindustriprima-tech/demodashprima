<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('olt_history', function (Blueprint $table) {
            $table->index('user_id');
            $table->index('olt_id');
            $table->index('action');
            $table->index('status');
            $table->index('created_at');
        });

        Schema::table('onus', function (Blueprint $table) {
            $table->index('olt_index');
        });

        Schema::table('audit_sessions', function (Blueprint $table) {
            $table->index('olt_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('olt_history', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['olt_id']);
            $table->dropIndex(['action']);
            $table->dropIndex(['status']);
            $table->dropIndex(['created_at']);
        });

        Schema::table('onus', function (Blueprint $table) {
            $table->dropIndex(['olt_index']);
        });

        Schema::table('audit_sessions', function (Blueprint $table) {
            $table->dropIndex(['olt_id']);
            $table->dropIndex(['created_at']);
        });
    }
};
