<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('criterion_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('call_id')->constrained()->cascadeOnDelete();
            $table->foreignId('criterion_id')->nullable()->nullOnDelete()->constrained('criteria');

            // Snapshot of criterion at scoring time
            $table->string('criterion_name');
            $table->text('criterion_description');
            $table->enum('criterion_type', ['pass_fail', 'scale_1_5', 'yes_no_na', 'extract']);
            $table->unsignedTinyInteger('criterion_weight');
            $table->boolean('criterion_is_critical')->default(false);

            // AI output
            $table->string('ai_result', 20);
            $table->decimal('ai_score', 4, 3)->nullable();
            $table->decimal('confidence', 4, 3);
            $table->text('reasoning');
            $table->json('evidence');
            $table->enum('verification_status', ['verified', 'partial', 'unverified'])->default('unverified');
            $table->boolean('needs_review')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('criterion_results');
    }
};
