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
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->softDeletes();
            $table->timestamp('last_message_at')->useCurrent();
            //$table->foreignId('school_id')->constrained()->onDelete('cascade'); if tenant
            $table->foreignId('conversation_group_id')
                  ->nullable()
                  ->constrained('conversation_groups')
                  ->onDelete('set null');
            $table->foreignId('creator_id')->nullable()->constrained('users')->onDelete('set null');
            $table->enum('type', ['direct', 'group', 'livechat'])->default('direct');
            $table->string('name')->nullable();
            
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
