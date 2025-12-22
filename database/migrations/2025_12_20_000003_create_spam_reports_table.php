<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('spam_reports', function (Blueprint $table) {
            $table->id();
            $table->string('reportable_type');
            $table->unsignedBigInteger('reportable_id');
            $table->unsignedBigInteger('user_id');
            $table->integer('spam_score');
            $table->json('detection_reasons');
            $table->boolean('auto_detected')->default(true);
            $table->enum('status', ['pending', 'reviewed', 'confirmed', 'false_positive'])->default('pending');
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamps();

            $table->index(['reportable_type', 'reportable_id']);
            $table->index(['user_id', 'spam_score']);
            $table->index(['status', 'auto_detected']);

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('reviewed_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spam_reports');
    }
};
