<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSoyalDevicesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('soyal_devices', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('batch_id')->comment('批次作業代碼');
            $table->string('device_id')->comment('系統唯一識別碼 eg:NK01');
            $table->string('ip')->comment('卡機IP Address');
            $table->integer('port')->comment('卡機Port 號');
            $table->integer('node')->comment('Node Id (1~254)');
            $table->string('event')->comment('update, add, delete');
            $table->string('uid')->comment('ABA10卡號 ex.0123456789');
            $table->string('display')->nullable()->comment('卡號對應的名稱');
            $table->string('pin')->nullable()->comment('密碼 (同一卡機需唯一值');
            $table->string('expire_start')->nullable()->comment('卡號有效起始日期');
            $table->string('expire_end')->nullable()->comment('卡號有效截止日期');
            $table->integer('status')->default(0)->comment('執行狀況 (0:未執行, 1:完成, -1:失敗');
            $table->string('message')->nullable();
            $table->integer('is_job')->comment('0:No, 1:Yes');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('soyal_devices');
    }
}
