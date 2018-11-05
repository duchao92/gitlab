<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateRegisterTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::beginTransaction();

        Schema::create('open_platforms', function (Blueprint $table) {
            // 开放平台
            $table->string('platform')->comment('平台标识');
            $table->string('open_id')->comment('开放平台ID');
            $table->string('nickname')->comment('开放平台用户名');
            $table->bigInteger('uid')->nullable()->comment('用户UID');
            $table->string('access_token')->comment('开放平台access_token');
            $table->string('refresh_token')->comment('开放平台refrefresh_token');
            $table->timestamp('expire_in')->comment('过期时间');
            $table->timestamps();

            $table->primary(['platform', 'open_id', 'uid']);
            $table->index(['uid', 'platform']);
        });

        Schema::create('email_codes', function (Blueprint $table) {
            // 邮箱验证码
            $table->bigIncrements('id');
            $table->string('email')->comment('用户邮箱');
            $table->string('code')->comment('邀请码');
            $table->string('uid')->default(0)->comment('绑定邮箱时对应的用户UID');
            $table->timestamp('expire_in')->comment('过期时间');
            $table->tinyInteger('type')->comment('类型，1注册，2忘记密码，3绑定邮箱');
            $table->tinyInteger('status')->comment('状态，1未使用，2已使用');
            $table->timestamps();

            $table->index(['email', 'status', 'code']);
        });

        Schema::create('sms_codes', function (Blueprint $table) {
            // 短信验证码
            $table->bigIncrements('id');
            $table->string('phone', 45)->comment('手机号');
            $table->string('code')->comment('验证码');
            $table->timestamp('expire_in')->comment('过期时间');
            $table->integer('times')->default(1)->comment('发送次数');
            $table->tinyInteger('type')->comment('类型，1注册，2忘记密码，3绑定手机号');
            $table->tinyInteger('status')->comment('状态，1未使用，2已使用');
            $table->timestamps();

            $table->index(['phone', 'type', 'status', 'code']);
        });

        Schema::create('extra_set', function (Blueprint $table) {
            // 用户其他设置
            $table->bigInteger('uid')->comment('用户UID');
            $table->tinyInteger('wechat_bind')->default(1)->comment('绑定微信,1否，2是');
            $table->tinyInteger('sms_notice')->default(1)->comment('短信提醒,1否，2是');
            $table->tinyInteger('email_notice')->default(1)->comment('邮件提醒,1否，2是');
            $table->tinyInteger('wechat_notice')->default(1)->comment('微信提醒,1否，2是');
            $table->tinyInteger('web_notice')->default(1)->comment('站内信提醒,1否，2是');
            $table->timestamps();

            $table->primary('uid');
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
        Schema::dropIfExists('open_platforms');
        Schema::dropIfExists('email_codes');
        Schema::dropIfExists('sms_codes');
        Schema::dropIfExists('extra_set');
    }
}
