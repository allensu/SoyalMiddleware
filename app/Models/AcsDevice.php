<?php

namespace App\models;

use Illuminate\Database\Eloquent\Model;

class AcsDevice extends Model
{
    protected $table = 'acs_device';

    public static function getAcsDevice($deviceId) {


        $acsDevice = AcsDevice::where('device_id', '=', $deviceId)->first();

        if(empty($acsDevice)) {
            throw new SCException('未知的 Device Id', -1);
        }

        return $acsDevice;
    }
}
