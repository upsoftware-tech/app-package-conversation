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
        Schema::create('conversation_message_read_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_message_id');
            $table->foreignId('user_id');
            $table->timestamp('read_at')->useCurrent();
            
            $table->unique(['conversation_message_id', 'user_id'], 'cmr_message_user_unique');
        
            $table->foreign('conversation_message_id', 'cmr_msg_id_fk')
                  ->references('id')
                  ->on('conversation_messages')
                  ->onDelete('cascade');
        
            $table->foreign('user_id', 'cmr_user_id_fk')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversation_message_read_receipts');
    }
};
