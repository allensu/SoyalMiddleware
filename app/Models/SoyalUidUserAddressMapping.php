<?php

namespace App\Models;

use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;
use App\Exceptions\SCException;

class SoyalUidUserAddressMapping extends Model
{

    public static function getNotUseUserAddress($deviceId, $uid) {
        $userAddress = 101; // 0~100:保留, 101~16383:可用
        $acsUidUserAddressMapping = SoyalUidUserAddressMapping::where('device_id', '=' , $deviceId)
            ->where('uid', '=', $uid)
            ->first();

        if($acsUidUserAddressMapping) {
            throw new SCException('卡號已存在', -1);
        } else {
            $acsUidUserAddressMapping = SoyalUidUserAddressMapping::where('device_id', '=', $deviceId)
                ->whereNotNull('uid')
                ->orderBy('user_address', 'desc')
                ->first();

            if($acsUidUserAddressMapping) {
                $userAddress = $acsUidUserAddressMapping->user_address + 1;
            } else {
                $userAddress = 101;
            }
        }

        return $userAddress;
    }

    public static function getUserAddressByUid($deviceId, $uid)
    {
        $acsUidUserAddressMapping = SoyalUidUserAddressMapping::where('device_id', '=' , $deviceId)
            ->where('uid', '=', $uid)
            ->first();

        if($acsUidUserAddressMapping)
        {
            return $acsUidUserAddressMapping->user_address;
        } else {
            return -1;
        }
    }
}
