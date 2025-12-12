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
        Schema::create('conversation_broadcasts', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->timestamp('expires_at')->nullable();
            //$table->foreignId('school_id')->constrained()->onDelete('cascade');
            $table->foreignId('author_id')->constrained('users');
            $table->string('title');
            $table->text('body');
            $table->json('target_roles')->nullable(); 
            $table->boolean('is_pinned')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversation_broadcasts');
    }
};
