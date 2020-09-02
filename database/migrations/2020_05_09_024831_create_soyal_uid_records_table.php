<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSoyalUidRecordsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('soyal_uid_records', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('ip')->comment('remote ip');
            $table->string('date')->comment('刷卡日期 ex. 20\'05/07');
            $table->string('time')->comment('刷卡時間 ex. 11:09:36');
            $table->string('address')->nullable()->comment('User Address');
            $table->string('alias')->nullable()->comment('Display(Alias)');
            $table->string('node_id')->comment('ex. 001');
            $table->string('sub_code')->comment('ex. 17');
            $table->string('function_code')->comment('ex. 0B ');
            $table->string('type')->comment('0-上班, 1-下班, 2-加班上, 3-加班下, 4-午休出, 5-午休回, 6-外出, 7-返回');
            $table->string('card_uid')->comment('卡號');
            $table->string('description')->nullable()->comment('ex. (M28)Access by PIN');
            $table->string('source_data');
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
        Schema::dropIfExists('soyal_uid_records');
    }
}
