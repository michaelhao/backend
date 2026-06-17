<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_conversations', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_one_id'); // 規範：較小的 user id
            $table->unsignedInteger('user_two_id'); // 規範：較大的 user id
            $table->unsignedInteger('last_message_id')->nullable();
            $table->dateTime('last_message_at')->nullable();
            $table->dateTime('created_at')->nullable();
            $table->dateTime('updated_at')->nullable();

            $table->unique(['user_one_id', 'user_two_id'], 'chat_conv_pair_unique');
            $table->index('last_message_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_conversations');
    }
};
