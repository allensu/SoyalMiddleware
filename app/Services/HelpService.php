<?php namespace App\Services;

use Illuminate\Support\Facades\Log;
use Carbon\Carbon;


class HelpService {

    public static function generateRandomString($length = 10, $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ') {
        //$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    public static function GetRandPassword()
    {

        $pw = md5(rand(0, 65535).microtime(true));
        return $pw;
    }

    public static function getUniqueKey($leaderCode)
    {
        $key = md5($leaderCode.microtime(true).rand(0, 65535));
        return $key;
    }

    public static function getNowTime()
    {
        return Carbon::now();
    }

    public static function getNowTimeAddMonths($addMonth)
    {
        return Carbon::now()->addMonths($addMonth);
    }

    /**
     * 判斷字串是否為JSON格式
     * @param $string
     * @return bool
     */
    public static function isJSON($string)
    {
        return is_string($string) && is_array(json_decode($string, true)) && (json_last_error() == JSON_ERROR_NONE) ? true : false;
    }

    /**
     * 統一數字到小數點後兩位
     */
    public static function formatNumber($value, $precision = 2)
    {
        return str_replace(",", "", number_format($value, $precision));
    }
}
