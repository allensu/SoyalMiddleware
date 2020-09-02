<?php namespace App\Services;

use App\Models\SoyalConnectDevice;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\SoyalUidUserAddressMapping;
use App\Exceptions\SCException;
use stdClass;

class DeviceService {

    public static function wg2aba($wgUid) {

    }

    public static function aba2wg($abaUid) {

        $intVal = intval($abaUid / 65536);
        $floatVal = (($abaUid / 65536) - $intVal) * 65536;

        $wgUid = "{$intVal}:{$floatVal}";

        return $wgUid;

    }

    public static function connectDeviceSocket($connectSoyalDevice) {

        $status = 0;
        $message = '';
        $timeout = 1;
        $host = $connectSoyalDevice->device_ip;
        $port = $connectSoyalDevice->device_port;
        try {
            $sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

// switch to non-blocking
            socket_set_nonblock($sock);

// store the current time
            $time = time();

// loop until a connection is gained or timeout reached
            while (!@socket_connect($sock, $host, $port)) {
                $err = socket_last_error($sock);

                // success!
                if($err === 56) {
//                    print('connected ok');
                    $message = 'connect success';
                    $status = 1;
                    break;
                }

                // if timeout reaches then call exit();
                if ((time() - $time) >= $timeout) {

                    socket_close($sock);
//                    print('timeout reached!');
                    $message = 'connect fail';
                    $status = -1;
//                    exit();
                    break;
                }

                // sleep for a bit
                usleep(250000);
            }

            if($status == 1) {
                socket_set_block($sock);
            }
        }
        catch (Exception $ex) {
            Log::info('Exception');
            Log::error($ex->getMessage());
            $message = $ex->getMessage();
        }

        $connectSoyalDevice->status = $status;
        $connectSoyalDevice->save();

        $device = new stdClass();
        $device->ip = $connectSoyalDevice->device_ip;
        $device->port = $connectSoyalDevice->device_port;
        $device->node = $connectSoyalDevice->node_id;
        $device->status = $connectSoyalDevice->status;

        return $device;
    }

    public static function connectDevice($connectSoyalDevice) {
        $device = new stdClass();
        $device->ip = $connectSoyalDevice->device_ip;
        $device->port = $connectSoyalDevice->device_port;
        $device->node = $connectSoyalDevice->node_id;

        try {
            $serverIp = $connectSoyalDevice->device_ip;//$acsDevice->device_ip;//Request::get('serverIp');
            $serverPort = $connectSoyalDevice->device_port; //$acsDevice->device_port;//Request::get('serverPort');
            $nodeNumber = $connectSoyalDevice->node_id;//$acsDevice->node_id;//Request::get('nodeNumber');

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
                throw new SCException($result, -1);
            }

            $connectSoyalDevice->status = 1; // 成功
            $connectSoyalDevice->message = "{$result}";

//                $ackOrNack = substr($result, 6, 2);
//
//                if($ackOrNack === '04') {
//                    $connectSoyalDevice->status = 1; // 成功
//                    $connectSoyalDevice->message = "success({$result})";
//                } else {
//                    throw new SCException($result, -1);
//                }

        } catch (SCException $secx) {
            Log::info('SCException');
            Log::error($secx->getMessage());
            $connectSoyalDevice->status = 2; // 失敗
            $connectSoyalDevice->message = "{$secx->getMessage()}";
        }
        catch (Exception $ex) {
            Log::info('Exception');
            Log::error($ex->getMessage());
            $connectSoyalDevice->status = 2; // 失敗
            $connectSoyalDevice->message = "{$ex->getMessage()}";
        }

        $connectSoyalDevice->save();

        if($connectSoyalDevice->status === 1) {
            $device->status = 1;
        } else {
            $device->status = 0;
        }

        Log::info(print_r($device, true));
        return $device;

    }

    public static function setUserAlias($device, $card) {
        $deviceId = $device->device_id;
        $uid = $card->uid;

        $userAddress = SoyalUidUserAddressMapping::getUserAddressByUid($deviceId, $uid);

        if($userAddress == -1) {
            throw new SCException('未記錄的卡號', -1);
        }

        $serverIp = $device->ip;//$acsDevice->device_ip;//Request::get('serverIp');
        $serverPort = $device->port; //$acsDevice->device_port;//Request::get('serverPort');
        $nodeNumber = $device->node;//$acsDevice->node_id;//Request::get('nodeNumber');







    }

    public static function deleteUid($soyalDevice) {
        Log::info(__METHOD__);
        $dummyMode = env('DUMMY_MODE', true);

        if($dummyMode) {
            return [1, ''];
        }

        $deviceId = $soyalDevice->device_id;
        $uid = $soyalDevice->uid;

        $userAddress = SoyalUidUserAddressMapping::getUserAddressByUid($deviceId, $uid);

        if($userAddress == -1) {
            throw new SCException('未記錄的卡號', -1);
        }

        $serverIp = $soyalDevice->ip;//$acsDevice->device_ip;//Request::get('serverIp');
        $serverPort = $soyalDevice->port; //$acsDevice->device_port;//Request::get('serverPort');
        $nodeNumber = $soyalDevice->node;//$acsDevice->node_id;//Request::get('nodeNumber');

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

        if($ackOrNack === '04') {
            $acsUidUserAddressMapping = SoyalUidUserAddressMapping::where('device_id', '=', $deviceId)
                ->where('user_address', '=', $userAddress)
                ->first();

            if($acsUidUserAddressMapping) {
                $acsUidUserAddressMapping->uid = null;
                $acsUidUserAddressMapping->save();
            }

            return [1, $result];
        } else {
            if($ackOrNack === '05') {
                // 05 handle
            }

            return [0, $result];
        }
    }

    public static function addUid($soyalDevice) {
        Log::info(__METHOD__);
        $dummyMode = env('DUMMY_MODE', true);

        if($dummyMode) {
            return [1, ''];
        }

        $deviceId = $soyalDevice->device_id;
        $uid = $soyalDevice->uid;

        // allen
//        $acsDevice = AcsDevice::getAcsDevice($deviceId);
        $userAddress = -1;
        if($soyalDevice->event === "add") {
            $userAddress = SoyalUidUserAddressMapping::getNotUseUserAddress($deviceId, $uid);
        } else if($soyalDevice->event === "update") {
            $userAddress = SoyalUidUserAddressMapping::getUserAddressByUid($deviceId, $uid);
        }


        $serverIp = $soyalDevice->ip;//$acsDevice->device_ip;//Request::get('serverIp');
        $serverPort = $soyalDevice->port; //$acsDevice->device_port;//Request::get('serverPort');
        $nodeNumber = $soyalDevice->node;//$acsDevice->node_id;//Request::get('nodeNumber');

        $header = 126; // 0x7E
//        $length = 31; // 0x1F
//        $nodeNumber = Request::get('nodeNumber');
        $command = 131; // 0x83
        $records = 1; // 0x01
        //$userAddress = $userAddress;//Request::get('userAddress');

        $tagUidNotUse = 0; // 0x00 0x00 0x00 0x00
        $uidHL = explode(':',self::aba2wg($uid));
        $tagUidH = $uidHL[0]; // max. 65535 (0xff 0xff)
        $tagUidL = $uidHL[1]; // max. 65535 (0xff 0xff)
        $pinCode = '0000';// $soyalDevice->pin; // 0x00 0x00 0x00 0x00

        $accessMode = "1"; // 0:invalid, 1:Card Only, 2:Card or Pin, 3:Card + Pin
        $mode = 0; // 0x00
        if($accessMode === "1") {
            $mode = 64; // 0x40
        } else if($accessMode === "2") {
            $mode = 128; // 0x80
        } else if($accessMode === "3") {
            $mode = 192; // 0xC0
        }
//        $mode = 4; // 0x04
//        if($accessMode === "1") {
//            $mode = 68; // 0x44
//        } else if($accessMode === "2") {
//            $mode = 132; // 0x84
//        } else if($accessMode === "3") {
//            $mode = 196; // 0xC4
//        }

        $zone = 64; // 0x80
        $group1 = 255; // 0xFF
        $group2 = 255; // 0xFF

//        $expireDate = explode('-', $soyalDevice->expire_end);
//        $year = substr($expireDate[0], 2, 2);
//        $month = $expireDate[1];
//        $day = $expireDate[2];
        $year = '98';
        $month = '12';
        $day = '31';

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

        if($ackOrNack === '04') {
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

            return [1, $result];
        } else {
            if($ackOrNack === '05') {
                // handle 05
            }

            return [0, $result];
        }
    }

    public static function updateUid($soyalDevice) {
        Log::info(__METHOD__);
        $dummyMode = env('DUMMY_MODE', true);

        if($dummyMode) {
            return [1, ''];
        }

        Log::info(print_r($soyalDevice, true));

        $deviceId = $soyalDevice->device_id;
        $uid = $soyalDevice->uid;

        $userAddress = SoyalUidUserAddressMapping::getUserAddressByUid($deviceId, $uid);

        if($userAddress == -1) {
            throw new SCException('找不到對應的卡機資料位址(user address)', -1);
        }

        $serverIp = $soyalDevice->ip;//$acsDevice->device_ip;//Request::get('serverIp');
        $serverPort = $soyalDevice->port; //$acsDevice->device_port;//Request::get('serverPort');
        $nodeNumber = $soyalDevice->node;//$acsDevice->node_id;//Request::get('nodeNumber');

        $header = 126; // 0x7E
//        $length = 31; // 0x1F
//        $nodeNumber = Request::get('nodeNumber');
        $command = 131; // 0x83
        $records = 1; // 0x01
        //$userAddress = $userAddress;//Request::get('userAddress');

        $tagUidNotUse = 0; // 0x00 0x00 0x00 0x00
        $uidHL = explode(':',self::aba2wg($uid));
        $tagUidH = $uidHL[0]; // max. 65535 (0xff 0xff)
        $tagUidL = $uidHL[1]; // max. 65535 (0xff 0xff)
        $pinCode = $soyalDevice->pin; // 0x00 0x00 0x00 0x00

        $accessMode = "2"; // 0:invalid, 1:Card Only, 2:Card or Pin, 3:Card + Pin
        $mode = 0; // 0x00
        if($accessMode === "1") {
            $mode = 64; // 0x40
        } else if($accessMode === "2") {
            $mode = 128; // 0x80
        } else if($accessMode === "3") {
            $mode = 192; // 0xC0
        }

        $zone = 64; // 0x80
        $group1 = 255; // 0xFF
        $group2 = 255; // 0xFF

//        $expireDate = explode('-', $soyalDevice->expire_end);
        $year = '98';
        $month = '12';
        $day = '31';

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

        if($ackOrNack === '04') {
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

            return [1, $result];
        } else {
            if($ackOrNack === '05') {
                // handle 05
            }

            return [0, $result];
        }

    }

    // 更新卡機通用密碼
    public static function updateDevicePincode($soyalDevicePin) {
        Log::info(__METHOD__);

        $dummyMode = env('DUMMY_MODE', true);

        if($dummyMode) {
            return [1, ''];
        }

        $deviceId = $soyalDevicePin->device_id;
        $uid = '0000000000'; // system default;
        $userAddress = 0; // system default
        $serverIp = $soyalDevicePin->ip;
        $serverPort = $soyalDevicePin->port;
        $nodeNumber = $soyalDevicePin->node;

        $header = 126; // 0x7E
//        $length = 31; // 0x1F
//        $nodeNumber = Request::get('nodeNumber');
        $command = 131; // 0x83
        $records = 1; // 0x01
        //$userAddress = $userAddress;//Request::get('userAddress');

        $tagUidNotUse = 0; // 0x00 0x00 0x00 0x00
        $uidHL = explode(':',self::aba2wg($uid));
        $tagUidH = $uidHL[0]; // max. 65535 (0xff 0xff)
        $tagUidL = $uidHL[1]; // max. 65535 (0xff 0xff)
        $pinCode = $soyalDevicePin->pin; // 0x00 0x00 0x00 0x00

        $accessMode = "2"; // 0:invalid, 1:Card Only, 2:Card or Pin, 3:Card + Pin
        $mode = 0; // 0x00
        if($accessMode === "1") {
            $mode = 64; // 0x40
        } else if($accessMode === "2") {
            $mode = 128; // 0x80
        } else if($accessMode === "3") {
            $mode = 192; // 0xC0
        }

        $zone = 64; // 0x80
        $group1 = 255; // 0xFF
        $group2 = 255; // 0xFF

//        $expireDate = explode('-', $soyalDevice->expire_end);
        $year = '98';
        $month = '12';
        $day = '31';

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

        if($ackOrNack === '04') {
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

            return [1, $result];
        } else {
            if($ackOrNack === '05') {
                // handle 05
            }

            return [0, $result];
        }
    }

    public static function updateDeviceUidPincode($soyalDevice) {

        $dummyMode = env('DUMMY_MODE', true);

        if($dummyMode) {
            return [1, ''];
        }

        Log::info(print_r($soyalDevice, true));

        $deviceId = $soyalDevice->device_id;
        $uid = $soyalDevice->uid;

        $userAddress = SoyalUidUserAddressMapping::getUserAddressByUid($deviceId, $uid);

        if($userAddress == -1) {
            throw new SCException('找不到對應的卡機資料位址(user address)', -1);
        }

        $serverIp = $soyalDevice->ip;//$acsDevice->device_ip;//Request::get('serverIp');
        $serverPort = $soyalDevice->port; //$acsDevice->device_port;//Request::get('serverPort');
        $nodeNumber = $soyalDevice->node;//$acsDevice->node_id;//Request::get('nodeNumber');

        $header = 126; // 0x7E
//        $length = 31; // 0x1F
//        $nodeNumber = Request::get('nodeNumber');
        $command = 131; // 0x83
        $records = 1; // 0x01
        //$userAddress = $userAddress;//Request::get('userAddress');

        $tagUidNotUse = 0; // 0x00 0x00 0x00 0x00
        $uidHL = explode(':',self::aba2wg($uid));
        $tagUidH = $uidHL[0]; // max. 65535 (0xff 0xff)
        $tagUidL = $uidHL[1]; // max. 65535 (0xff 0xff)
        $pinCode = $soyalDevice->pin; // 0x00 0x00 0x00 0x00

        $accessMode = "2"; // 0:invalid, 1:Card Only, 2:Card or Pin, 3:Card + Pin
        $mode = 0; // 0x00
        if($accessMode === "1") {
            $mode = 64; // 0x40
        } else if($accessMode === "2") {
            $mode = 128; // 0x80
        } else if($accessMode === "3") {
            $mode = 192; // 0xC0
        }

        $zone = 64; // 0x80
        $group1 = 255; // 0xFF
        $group2 = 255; // 0xFF

//        $expireDate = explode('-', $soyalDevice->expire_end);
        $year = '98';
        $month = '12';
        $day = '31';

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

        if($ackOrNack === '04') {
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

            return [1, $result];
        } else {
            if($ackOrNack === '05') {
                // handle 05
            }

            return [0, $result];
        }

    }

    public static function cancelDeviceUidPincode($soyalDevice) {
        Log::info(__METHOD__);
        $dummyMode = env('DUMMY_MODE', true);

        if($dummyMode) {
            return [1, ''];
        }

        Log::info(print_r($soyalDevice, true));

        $deviceId = $soyalDevice->device_id;
        $uid = $soyalDevice->uid;

        $userAddress = SoyalUidUserAddressMapping::getUserAddressByUid($deviceId, $uid);

        if($userAddress == -1) {
            throw new SCException('找不到對應的卡機資料位址(user address)', -1);
        }

        $serverIp = $soyalDevice->ip;//$acsDevice->device_ip;//Request::get('serverIp');
        $serverPort = $soyalDevice->port; //$acsDevice->device_port;//Request::get('serverPort');
        $nodeNumber = $soyalDevice->node;//$acsDevice->node_id;//Request::get('nodeNumber');

        $header = 126; // 0x7E
//        $length = 31; // 0x1F
//        $nodeNumber = Request::get('nodeNumber');
        $command = 131; // 0x83
        $records = 1; // 0x01
        //$userAddress = $userAddress;//Request::get('userAddress');

        $tagUidNotUse = 0; // 0x00 0x00 0x00 0x00
        $uidHL = explode(':',self::aba2wg($uid));
        $tagUidH = $uidHL[0]; // max. 65535 (0xff 0xff)
        $tagUidL = $uidHL[1]; // max. 65535 (0xff 0xff)
        $pinCode = '0000'; // 0x00 0x00 0x00 0x00

        $accessMode = "1"; // 0:invalid, 1:Card Only, 2:Card or Pin, 3:Card + Pin
        $mode = 0; // 0x00
        if($accessMode === "1") {
            $mode = 64; // 0x40
        } else if($accessMode === "2") {
            $mode = 128; // 0x80
        } else if($accessMode === "3") {
            $mode = 192; // 0xC0
        }

        $zone = 64; // 0x80
        $group1 = 255; // 0xFF
        $group2 = 255; // 0xFF

//        $expireDate = explode('-', $soyalDevice->expire_end);
        $year = '98';
        $month = '12';
        $day = '31';

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

        if($ackOrNack === '04') {
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

            return [1, $result];
        } else {
            if($ackOrNack === '05') {
                // handle 05
            }

            return [0, $result];
        }
    }

    public static function changeStatusUid($soyalDevice) {
        Log::info(__METHOD__);
        $dummyMode = env('DUMMY_MODE', true);

        if($dummyMode) {
            return [1, ''];
        }

        Log::info(print_r($soyalDevice, true));

        $deviceId = $soyalDevice->device_id;
        $uid = $soyalDevice->uid;

        $userAddress = SoyalUidUserAddressMapping::getUserAddressByUid($deviceId, $uid);

        if($userAddress == -1) {
            throw new SCException('找不到對應的卡機資料位址(user address)', -1);
        }

        $serverIp = $soyalDevice->ip;//$acsDevice->device_ip;//Request::get('serverIp');
        $serverPort = $soyalDevice->port; //$acsDevice->device_port;//Request::get('serverPort');
        $nodeNumber = $soyalDevice->node;//$acsDevice->node_id;//Request::get('nodeNumber');

        $header = 126; // 0x7E
//        $length = 31; // 0x1F
//        $nodeNumber = Request::get('nodeNumber');
        $command = 131; // 0x83
        $records = 1; // 0x01
        //$userAddress = $userAddress;//Request::get('userAddress');

        $tagUidNotUse = 0; // 0x00 0x00 0x00 0x00
        $uidHL = explode(':',self::aba2wg($uid));
        $tagUidH = $uidHL[0]; // max. 65535 (0xff 0xff)
        $tagUidL = $uidHL[1]; // max. 65535 (0xff 0xff)
        $pinCode = $soyalDevice->pin; // 0x00 0x00 0x00 0x00

        if($soyalDevice->event === "invalid") {
            $accessMode = "0"; // 0:invalid, 1:Card Only, 2:Card or Pin, 3:Card + Pin
        } else {
            $accessMode = "1"; // 0:invalid, 1:Card Only, 2:Card or Pin, 3:Card + Pin
        }

        $mode = 0; // 0x00
        if($accessMode === "1") {
            $mode = 64; // 0x40
        } else if($accessMode === "2") {
            $mode = 128; // 0x80
        } else if($accessMode === "3") {
            $mode = 192; // 0xC0
        }

        $zone = 64; // 0x80
        $group1 = 255; // 0xFF
        $group2 = 255; // 0xFF

//        $expireDate = explode('-', $soyalDevice->expire_end);
        $year = '98';
        $month = '12';
        $day = '31';

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

        if($ackOrNack === '04') {
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

            return [1, $result];
        } else {
            if($ackOrNack === '05') {
                // handle 05
            }

            return [0, $result];
        }

    }
}
