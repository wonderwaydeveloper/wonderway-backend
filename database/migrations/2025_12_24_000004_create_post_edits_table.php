<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('post_edits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->onDelete('cascade');
            $table->text('original_content');
            $table->text('new_content');
            $table->string('edit_reason')->nullable();
            $table->timestamp('edited_at');
            $table->timestamps();

            $table->index(['post_id', 'edited_at']);
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->timestamp('last_edited_at')->nullable();
            $table->boolean('is_edited')->default(false);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_edits');
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn(['last_edited_at', 'is_edited']);
        });
    }
};
