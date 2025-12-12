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
        Schema::create('conversation_message_templates', function (Blueprint $table) {
            $table->id();
            // MULTI-TENANCY: Szablon zawsze należy do konkretnej szkoły
            //$table->foreignId('school_id')->constrained()->onDelete('cascade');
            $table->foreignId('creator_id')->constrained('users')->onDelete('cascade');
            $table->boolean('is_shared')->default(false);
            $table->string('shortcut', 50);
            $table->text('body');
            $table->string('category', 50)->nullable();
            $table->timestamps();
            //$table->index(['school_id', 'creator_id', 'is_shared'], 'templates_lookup_idx');
            $table->index(['creator_id', 'is_shared'], 'templates_lookup_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversation_message_templates');
    }
};
