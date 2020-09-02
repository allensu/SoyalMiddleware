<?php

namespace App\Http\Controllers;

use App\Models\SoyalUidUserAddressMapping;
use App\Exceptions\SCException;
use App\models\AcsDevice;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Support\Facades\Request;
//use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;


class ApiController extends Controller
{

    public function connectDevice() {

//        $deviceId = Request::get('deviceId');
//
//        $acsDevice = AcsDevice::where('device_id', '=', $deviceId)->first();
//
//        if(empty($acsDevice)) {
//            throw new SCException('未知的 Device Id', -1);
//        }

//        [{"device_id":"NK01", "ip":"118.233.72.82","port":"1621","nodeId":"1"},{"device_id":"NK02", "ip":"118.233.72.82","port":"1622","nodeId":"2"},{"device_id":"NK03", "ip":"118.233.72.82","port":"1623","nodeId":"3"}]
        $serverIp = '118.233.72.82';//$acsDevice->device_ip;//Request::get('serverIp');
        $serverPort = 1622; //$acsDevice->device_port;//Request::get('serverPort');
        $nodeNumber = 2;//$acsDevice->node_id;//Request::get('nodeNumber');

        Log::info('connect : '.$serverIp.', '.$serverPort.', '.$nodeNumber);
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        //連接Server端
        $connection = socket_connect($socket, $serverIp, $serverPort);

        $messageBuffer = [126, 4]; // 7E is header, 04 is data length (byte)
        array_push($messageBuffer, $nodeNumber); // node number
        array_push($messageBuffer, 24); // command
        $xorResult = 255 ^ $nodeNumber ^ 24;
        $sum = $nodeNumber + 24 + $xorResult;
        $sum %= 256;
        array_push($messageBuffer, $xorResult);
        array_push($messageBuffer, $sum);

        $stringHex='';
        foreach ($messageBuffer as $decData) {
            $stringHex .= sprintf("%'02s", dechex($decData));
        }
        $stringHex = strtoupper($stringHex);
        Log::info($stringHex);

        $messageBufferStr = $stringHex;
        $bin = hex2bin($messageBufferStr);
//
        socket_write($socket, $bin, strlen($bin));
//        socket_send($socket, $messageBufferStr, 12, MSG_EOF);

        $result = '';
        while($buffer = socket_read($socket,1024))
        {
//            $str = strspn($buffer, '0123456789abcdefABCDEF');
//            //字符串长度不是偶数时pack来处理
//            if (strlen($str) % 2) {
//                echo pack("H*", $str);
//            } else {
//                echo hex2bin($str);
//            }

            $result = bin2hex($buffer);
            break;
        }
        socket_close($socket);

        Log::info($result);
        if(empty($result)) {
            throw new SCException('連線失敗', -1);
        }

        $ackOrNack = substr($result, 6, 2);

        if($ackOrNack === '05') {
            throw new SCException('未知的連線要求', -1);
        }

        return $result;
    }



    public function addUID() {

        $deviceId = Request::get('deviceId');
        $uid = Request::get('uid');

        // allen
//        $acsDevice = AcsDevice::getAcsDevice($deviceId);

        $userAddress = SoyalUidUserAddressMapping::getNotUseUserAddress($deviceId, $uid);



//        Log::info($userAddress);
//        exit();

        $serverIp = '10.0.1.78';//$acsDevice->device_ip;//Request::get('serverIp');
        $serverPort = 1621; //$acsDevice->device_port;//Request::get('serverPort');
        $nodeNumber = 1;//$acsDevice->node_id;//Request::get('nodeNumber');



        $header = 126; // 0x7E
//        $length = 31; // 0x1F
//        $nodeNumber = Request::get('nodeNumber');
        $command = 131; // 0x83
        $records = 1; // 0x01
        //$userAddress = $userAddress;//Request::get('userAddress');

        $tagUidNotUse = 0; // 0x00 0x00 0x00 0x00
        $uidHL = explode(':',$uid);
        $tagUidH = $uidHL[0]; // max. 65535 (0xff 0xff)
        $tagUidL = $uidHL[1]; // max. 65535 (0xff 0xff)
        $pinCode = Request::get('pinCode'); // 0x00 0x00 0x00 0x00

        $accessMode = Request::get('accessMode'); // 0:invalid, 1:Card Only, 2:Card or Pin, 3:Card + Pin
        $mode = 4; // 0x04
        if($accessMode === "1") {
            $mode = 68; // 0x44
        } else if($accessMode === "2") {
            $mode = 132; // 0x84
        } else if($accessMode === "3") {
            $mode = 196; // 0xC4
        }

        $zone = 64; // 0x80
        $group1 = 255; // 0xFF
        $group2 = 255; // 0xFF

        $expireDate = explode('-', Request::get('expireDate'));
        $year = $expireDate[0];
        $month = $expireDate[1];
        $day = $expireDate[2];

        $level = 192; // 0xc0
        $option = 0; // 0x00
        $reserved1 = 0; // 0x00
        $reserved2 = 0; // 0x00
        $reserved3 = 0; // 0x00
        $xorInitalCode = 255; // 0xFF
        $sumInitalCode = 0; // 0x00

        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        // 連接Server端
        $connection = socket_connect($socket, $serverIp, $serverPort);

        $headerHex = sprintf("%'02s", dechex($header));
//        $lengthHex = sprintf("%'02s", dechex($length));

        $nodeNumberHex = sprintf("%'02s", dechex($nodeNumber));
        $commandHex = sprintf("%'02s", dechex($command));
        $recordsHex = sprintf("%'02s", dechex($records));
        $userAddressHex = sprintf("%'04s", dechex($userAddress));
        $tagUidNotUseHex = sprintf("%'08s", dechex($tagUidNotUse));
        $tagUidHHex = sprintf("%'04s", dechex($tagUidH));
        $tagUidLHex = sprintf("%'04s", dechex($tagUidL));
        $pinCodeHex = sprintf("%'08s", dechex($pinCode));
        $modeHex = sprintf("%'02s", dechex($mode));
        $zoneHex = sprintf("%'02s", dechex($zone));
        $group1Hex = sprintf("%'02s", dechex($group1));
        $group2Hex = sprintf("%'02s", dechex($group2));
        $yearHex = sprintf("%'02s", dechex($year));
        $monthHex = sprintf("%'02s", dechex($month));
        $dayHex = sprintf("%'02s", dechex($day));
        $levelHex = sprintf("%'02s", dechex($level));
        $optionHex = sprintf("%'02s", dechex($option));
        $reserved1Hex = sprintf("%'02s", dechex($reserved1));
        $reserved2Hex = sprintf("%'02s", dechex($reserved2));
        $reserved3Hex = sprintf("%'02s", dechex($reserved3));

        //
        $xorResultHexString = '';
        $xorResultHexString .= $nodeNumberHex;
        $xorResultHexString .= $commandHex;
        $xorResultHexString .= $recordsHex;
        $xorResultHexString .= $userAddressHex;
        $xorResultHexString .= $tagUidNotUseHex;
        $xorResultHexString .= $tagUidHHex;
        $xorResultHexString .= $tagUidLHex;
        $xorResultHexString .= $pinCodeHex;
        $xorResultHexString .= $modeHex;
        $xorResultHexString .= $zoneHex;
        $xorResultHexString .= $group1Hex;
        $xorResultHexString .= $group2Hex;
        $xorResultHexString .= $yearHex;
        $xorResultHexString .= $monthHex;
        $xorResultHexString .= $dayHex;
        $xorResultHexString .= $levelHex;
        $xorResultHexString .= $optionHex;
        $xorResultHexString .= $reserved1Hex;
        $xorResultHexString .= $reserved2Hex;
        $xorResultHexString .= $reserved2Hex;
        Log::info($xorResultHexString);

        $bin = hex2bin($xorResultHexString);
        $xorResult = 255;
        $sum = 0;
        $length = 2;
        for ($i=0; $i < strlen($bin); $i++) {
            $data[] = ord(substr($bin,$i,1)); // 使用ord將字元轉成int
            $xorResult = $xorResult ^ ord(substr($bin,$i,1));
            $sum += ord(substr($bin,$i,1));
            $length++;
        }

        $sum += $xorResult;
        $sum %= 256;

        $lengthHex = sprintf("%'02s", dechex($length));
        $xorResultHex = sprintf("%'02s", dechex($xorResult));
        $sumHex = sprintf("%'02s", dechex($sum));

        $stringHex = '';
        $stringHex .= $headerHex;
        $stringHex .= $lengthHex;
        $stringHex .= $nodeNumberHex;
        $stringHex .= $commandHex;
        $stringHex .= $recordsHex;
        $stringHex .= $userAddressHex;
        $stringHex .= $tagUidNotUseHex;
        $stringHex .= $tagUidHHex;
        $stringHex .= $tagUidLHex;
        $stringHex .= $pinCodeHex;
        $stringHex .= $modeHex;
        $stringHex .= $zoneHex;
        $stringHex .= $group1Hex;
        $stringHex .= $group2Hex;
        $stringHex .= $yearHex;
        $stringHex .= $monthHex;
        $stringHex .= $dayHex;
        $stringHex .= $levelHex;
        $stringHex .= $optionHex;
        $stringHex .= $reserved1Hex;
        $stringHex .= $reserved2Hex;
        $stringHex .= $reserved3Hex;
        $stringHex .= $xorResultHex;
        $stringHex .= $sumHex;

        Log::info($stringHex);

        $messageBufferStr = $stringHex;
        $bin = hex2bin($messageBufferStr);

        socket_write($socket, $bin, strlen($bin));

        $result = '';
        while($buffer = socket_read($socket,1024))
        {
            $result = bin2hex($buffer);
            break;
        }
        socket_close($socket);

        if(empty($result)) {
            throw new SCException('連線失敗', -1);
        }

        $ackOrNack = substr($result, 6, 2);

        if($ackOrNack === '05') {
            throw new SCException('未知的連線要求', -1);
        } else if($ackOrNack === '04') {

            $acsUidUserAddressMapping = SoyalUidUserAddressMapping::where('device_id', '=', $deviceId)
                                                                ->where('user_address', '=', $userAddress)
                                                                ->first();

            if($acsUidUserAddressMapping) {
                $acsUidUserAddressMapping->uid = $uid;
                $acsUidUserAddressMapping->save();
            } else {
                $acsUidUserAddressMapping = new SoyalUidUserAddressMapping;
                $acsUidUserAddressMapping->device_id = $deviceId;
                $acsUidUserAddressMapping->user_address = $userAddress;
                $acsUidUserAddressMapping->uid = $uid;
                $acsUidUserAddressMapping->save();
            }
        }




        return $result;
    }


    public function removeUID() {
        $deviceId = Request::get('deviceId');
        $uid = Request::get('uid');

//        $acsDevice = AcsDevice::where('device_id', '=', $deviceId)->first();
//
//        if(empty($acsDevice)) {
//            throw new SCException('未知的 Device Id', -1);
//        }


        // 先不取 by allen at 2020-05-07 10:27 am
//        $acsDevice = AcsDevice::getAcsDevice($deviceId);

        $userAddress = SoyalUidUserAddressMapping::getUserAddressByUid($deviceId, $uid);


        if($userAddress == -1) {
            throw new SCException('未記錄的卡號', -1);
        }

        $serverIp = '10.0.1.78';//$acsDevice->device_ip;//Request::get('serverIp');
        $serverPort = 1621; //$acsDevice->device_port;//Request::get('serverPort');
        $nodeNumber = 1;//$acsDevice->node_id;//Request::get('nodeNumber');


        $serverIp = '10.0.1.78';//$acsDevice->device_ip;//Request::get('serverIp');
        $serverPort = 1621; //$acsDevice->device_port;//Request::get('serverPort');
        $nodeNumber = 1;//$acsDevice->node_id;//Request::get('nodeNumber');

        /*
         * Soyal Socket Api Handle Start
         */
        $header = 126; // 0x7E
        $length = 8;
//        $nodeNumber = $p_nodeNumber;
        $command = 133; // 0x85
        $userAddressStart = $userAddress;
        $userAddressEnd = $userAddress;
        $xorInitalCode = 255; // 0xFF
        $sumInitalCode = 0; // 0x00

        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        // 連接Server端
        $connection = socket_connect($socket, $serverIp, $serverPort);

        $headerHex = sprintf("%'02s", dechex($header));
        $lengthHex = sprintf("%'02s", dechex($length));
        $nodeNumberHex = sprintf("%'02s", dechex($nodeNumber));
        $commandHex = sprintf("%'02s", dechex($command));
        $userAddressStartHex = sprintf("%'04s", dechex($userAddressStart));
        $userAddressEndHex = sprintf("%'04s", dechex($userAddressEnd));

        $xorResult = $xorInitalCode ^ $nodeNumber ^ $command ^ $userAddressStart ^ $userAddressEnd;
        $xorResultHex = sprintf("%'02s", dechex($xorResult));

        $sum = $sumInitalCode + $nodeNumber + $command + $userAddressStart + $userAddressEnd + $xorResult ;
        $sum %= 256;
        $sumHex = sprintf("%'02s", dechex($sum));

        $stringHex = '';
        $stringHex .= $headerHex;
        $stringHex .= $lengthHex;
        $stringHex .= $nodeNumberHex;
        $stringHex .= $commandHex;
        $stringHex .= $userAddressStartHex;
        $stringHex .= $userAddressEndHex;
        $stringHex .= $xorResultHex;
        $stringHex .= $sumHex;

        Log::info($stringHex);

        $messageBufferStr = $stringHex;
        $bin = hex2bin($messageBufferStr);

        socket_write($socket, $bin, strlen($bin));

        $result = '';
        while($buffer = socket_read($socket,1024))
        {
            $result = bin2hex($buffer);
            break;
        }
        socket_close($socket);

        if(empty($result)) {
            throw new SCException('連線失敗', -1);
        }

        $ackOrNack = substr($result, 6, 2);

        if($ackOrNack === '05') {
            throw new SCException('未知的連線要求', -1);
        } else if($ackOrNack === '04') {
            $acsUidUserAddressMapping = SoyalUidUserAddressMapping::where('device_id', '=', $deviceId)
                                                                ->where('user_address', '=', $userAddress)
                                                                ->first();

            if($acsUidUserAddressMapping) {
                $acsUidUserAddressMapping->uid = null;
                $acsUidUserAddressMapping->save();
            }
        }

        return $result;
    }


    public function updatePinCode() {

    }


    public function setUidAlias() {

    }


    public function expireUid() {

    }




}
