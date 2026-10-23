<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('criteria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('name');
            $table->text('description');
            $table->text('fail_conditions')->nullable();
            $table->enum('type', ['pass_fail', 'scale_1_5', 'yes_no_na', 'extract']);
            $table->unsignedTinyInteger('weight')->default(1);
            $table->boolean('is_critical')->default(false);
            $table->enum('phase_hint', ['opening', 'discovery', 'pitch', 'objections', 'close', 'anywhere'])->default('anywhere');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('criteria');
    }
};
