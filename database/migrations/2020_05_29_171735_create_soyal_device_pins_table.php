<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSoyalDevicePinsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('soyal_device_pins', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('batch_id')->nullable()->comment('批次作業代碼');
            $table->string('device_id')->comment('系統唯一識別碼 eg:NK01');
            $table->string('ip')->comment('卡機IP Address');
            $table->integer('port')->comment('卡機Port 號');
            $table->integer('node')->comment('Node Id (1~254)');
            $table->string('pin')->comment('密碼 (同一卡機需唯一值');
            $table->integer('status')->default(0)->comment('執行狀況 (-1:未執行, 1:完成, 0:失敗');
            $table->string('message', 512)->default('');
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
        Schema::dropIfExists('soyal_device_pins');
    }
}
