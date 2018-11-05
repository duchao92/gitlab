<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMessagesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::beginTransaction();
        
        Schema::create('messages', function (Blueprint $table) {
            $table->increments('id');
            $table->string('message_id', 32)->comment('消息内容编号');
            $table->integer('sender_id')->comment('发送者id, 系统发送0');
            $table->string('title', 255)->comment('消息标题');
            $table->text('content')->comment('消息内容');
            $table->tinyInteger('type')->comment('类型, 1系统消息，2业务消息');
            $table->timestamps();

            // 索引
            $table->index('type');
            $table->index('sender_id');
            $table->index('title');
            $table->index('message_id');
        });

        Schema::create('message_send', function (Blueprint $table) {
            $table->increments('id');
            $table->string('message_id', 32)->comment('消息内容编号');
            $table->integer('receiver_id')->comment('接收者id');
            $table->tinyInteger('status')->comment('状态, 1未读，2已读');
            $table->timestamps();

            // 索引
            $table->index('status');
            $table->index('receiver_id');
            $table->index('message_id');
        });

        Schema::create('message_logs', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('sender_id')->comment('发送者id');
            $table->integer('receiver_id')->comment('接收者id');
            $table->text('content')->comment('消息内容');
            $table->tinyInteger('type')->comment('类型, 1短信，2邮箱');
            $table->tinyInteger('status')->comment('状态, 1成功，2失败');
            $table->timestamps();

            // 索引
            $table->index(['type', 'status']);
            $table->index('sender_id');
            $table->index('receiver_id');
        });
        
        DB::commit();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('messages');
        Schema::dropIfExists('message_send');
        Schema::dropIfExists('message_logs');
    }
}
