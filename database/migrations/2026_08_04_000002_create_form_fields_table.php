<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_fields', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('form_id')->constrained()->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('label');
            $table->string('name');
            $table->string('type')->index();
            $table->string('placeholder')->nullable();
            $table->text('help_text')->nullable();
            $table->text('default_value')->nullable();
            $table->json('validation_rules')->nullable();
            $table->json('field_options')->nullable();
            $table->boolean('is_required')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->unique(['form_id', 'name']);
            $table->index(['form_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_fields');
    }
};
