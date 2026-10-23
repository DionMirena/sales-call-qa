<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained('agents');
            $table->foreignId('template_id')->constrained('templates');
            $table->foreignId('uploaded_by')->constrained('users');
            $table->string('customer_name')->nullable();
            $table->string('policy_type')->nullable();
            $table->string('original_path');
            $table->string('original_filename');
            $table->string('audio_path')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->enum('status', ['queued', 'normalising', 'transcribing', 'scoring', 'done', 'failed'])->default('queued');
            $table->text('error_message')->nullable();
            $table->unsignedTinyInteger('agent_speaker')->default(0);
            $table->decimal('score_percent', 5, 2)->nullable();
            $table->enum('outcome', ['pass', 'fail', 'auto_fail'])->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calls');
    }
};
