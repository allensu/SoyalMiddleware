<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSoyalUidUserAddressMappingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('soyal_uid_user_address_mappings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('device_id');
            $table->integer('user_address')->comment('0~100:保留, 101~16383:可用');
            $table->string('uid')->nullable(true);
            $table->timestamps();
            $table->unique(['device_id', 'user_address']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('soyal_uid_user_address_mappings');
    }
}
