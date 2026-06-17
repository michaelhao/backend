<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('conversation_id');
            $table->unsignedInteger('sender_id');
            $table->text('body');
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();

            $table->index(['conversation_id', 'id'], 'chat_msg_conv_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
