<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_fields', function (Blueprint $table): void {
            $table->unsignedInteger('step')->default(1)->after('sort_order');
            $table->index(['form_id', 'step', 'sort_order']);
        });

        Schema::table('ai_generation_logs', function (Blueprint $table): void {
            $table->foreignId('form_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->unsignedInteger('latency_ms')->nullable()->after('tokens');
            $table->string('mode')->default('generate')->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('ai_generation_logs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('form_id');
            $table->dropColumn(['latency_ms', 'mode']);
        });

        Schema::table('form_fields', function (Blueprint $table): void {
            $table->dropIndex(['form_id', 'step', 'sort_order']);
            $table->dropColumn('step');
        });
    }
};
