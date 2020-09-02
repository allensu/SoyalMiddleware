<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSoyalConnectDevicesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('soyal_connect_devices', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('batch_id')->comment('批次作業代碼');
//            $table->string('device_id')->comment('系統唯一識別碼 eg:NK01');
            $table->string('device_ip')->comment('卡機IP Address');
            $table->integer('device_port')->comment('卡機Port 號');
            $table->integer('node_id')->comment('Node Id (1~254)');
            $table->integer('status')->default(0)->comment('執行狀況 (0:未執行, 1:連線成功, 2:連線失敗');
            $table->string('message')->nullable();
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
        Schema::dropIfExists('soyal_connect_devices');
    }
}
