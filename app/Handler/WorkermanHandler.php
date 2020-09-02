<?php

namespace Handler;

use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Workerman\Worker;
use Workerman\Lib\Timer;
use App\Models\SoyalUidRecord;

// 心跳间隔10秒
define('HEARTBEAT_TIME', 10);

class WorkermanHandler
{
    protected $remoteIp;

    // 处理客户端连接
    public function onConnect($connection)
    {
        $this->remoteIp = $connection->getRemoteIp();
        echo "new connection from ip " . $connection->getRemoteIp() . "\n";
    }

    // 处理客户端消息
    public function onMessage($connection, $data)
    {
//        20'05/04 18:05:41 [001.17:03](0)00105:06593      (M03)Invalid card
//        20'05/06 20:39:51 [001.17:06](0)00152:12010     Allen1 (M06)Expiry Date
//        20'05/06 20:41:46 [001.17:0B](0)00152:12010     Allen1 (M11)Normal Access
//        20'05/06 20:42:20 [001.17:02](0) (M02)
//        20'05/06 20:42:20 [001.17:03](0)00094:23409      (M03)Invalid card
//
///

//        20'05/11 08:54:14 [001.17:01](0) (M01)Keyin the invalid card number
//        20'05/04 18:05:41 [001.17:03](0)00105:06593      (M03)Invalid card
//        20'05/09 09:00:11 [001.17:0B](0)00103:63605      (M11)Normal Access
//        20'05/09 09:00:19 [001.17:1C](0)00103:63605      (M28)Access by PIN
//        20'05/11 08:52:26 [001.17:0B](0)00103:63605     AllenSu00000000 (M11)Normal Access
//        20'05/11 08:57:08 [001.17:1C](0)00103:63605     AllenSu00000000 (M28)Access by PIN

//        M01

//        0 - 上班
//        1 - 下班
//        2 - 加班上
//        3 - 加班下
//        4 - 午休出
//        5 - 午休回
//        6 - 外出
//        7 - 返回

        echo 'receive : '.$data;
//        $data = "20'05/11 08:52:26 [001.17:0B](0)00103:63605     AllenSu00000000 (M11)Normal Access";

        $dataArray = [];
        $dateOrg = substr($data, 0, 8);
        $dataArray[] = $date = '20'.str_replace(array('\'', '/'), '-', $dateOrg); // 20'05/11
        $dataArray[] = $time = substr($data, 9, 8); // 08:52:26
        $dataArray[] = $commandCode = substr($data, strpos($data, '[', 0) + 1, 9); // 001.17:0B
        $commandCodeArray = explode('.', $commandCode);
        $dataArray[] = $nodeId = $commandCodeArray[0];
        $dataArray[] = $subCode = explode(':', $commandCodeArray[1])[0];
        $dataArray[] =  $functionCode = explode(':', $commandCodeArray[1])[1];
        $dataArray[] = $type = substr($data, strpos($data, '(', 0) + 1, 1); // 0-上班, 1-下班, 2-加班上, 3-加班下, 4-午休出, 5-午休回, 6-外出, 7-返回

        if($functionCode === '01') {
            // 輸入密碼錯誤
            $dataArray[] = $cardUid = '';
        } else {
            $dataArray[] = $cardUid = substr($data, strpos($data, ')', 0) + 1, 11); // xxxxx:xxxxx
        }
//        $dataArray[] = $cardUid = substr($data, strpos($data, ')', 0) + 1, 11); // xxxxx:xxxxx

        $soyalUidRecord = new SoyalUidRecord;
        $soyalUidRecord->ip = $this->remoteIp;
        $soyalUidRecord->date = $date;
        $soyalUidRecord->time = $time;
//        $soyalUidRecord->address = '';
//        $soyalUidRecord->alias = '';
        $soyalUidRecord->node_id = $nodeId;
        $soyalUidRecord->sub_code = $subCode;
        $soyalUidRecord->function_code = $functionCode;
        $soyalUidRecord->type = $type;
        $soyalUidRecord->card_uid = $cardUid;
//        $soyalUidRecord->description = '';
        $soyalUidRecord->source_data = $data;
        $soyalUidRecord->save();


        Log::info(print_r(json_encode($soyalUidRecord), true));


        $payload = json_encode($soyalUidRecord);
        Log::info($payload);

        $odooServerApi = Config::get('soyal.odooServerApi');

        $client = new Client;
        $response = $client->request('POST', $odooServerApi.'device-record', [
            'body' => $payload
        ]);

        $body = $response->getBody();
        $returnData = json_decode($body);

        Log::info(json_encode($returnData));
    }

    // 处理客户端断开
    public function onClose($connection)
    {
        echo "connection closed from ip {$connection->getRemoteIp()}\n";
    }

    public function onWorkerStart($worker)
    {
        Timer::add(1, function () use ($worker) {
            $time_now = time();
            foreach ($worker->connections as $connection) {
                // 有可能该connection还没收到过消息，则lastMessageTime设置为当前时间
                if (empty($connection->lastMessageTime)) {
                    $connection->lastMessageTime = $time_now;
                    continue;
                }
                // 上次通讯时间间隔大于心跳间隔，则认为客户端已经下线，关闭连接
                if ($time_now - $connection->lastMessageTime > HEARTBEAT_TIME) {
                    echo "Client ip {$connection->getRemoteIp()} timeout!!!\n";
                    $connection->close();
                }
            }
        });
    }

}
