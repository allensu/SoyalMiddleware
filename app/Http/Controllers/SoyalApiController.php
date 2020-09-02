<?php

namespace App\Http\Controllers;

use App\Jobs\CancelUidPinJob;
use App\Jobs\DailyDevicesUpdatePinCodeJob;
use App\Jobs\ConnectSoyalDeviceJob;
use App\Jobs\SoyalDeviceJob;
use App\Models\SoyalConnectDevice;
use App\Models\SoyalDevice;
use App\Models\SoyalDevicePin;
use App\Models\SoyalIpPin;
use App\Models\SoyalUidRecord;
use App\Services\DeviceService;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Request;
use App\Services\HelpService;
use Illuminate\Support\Facades\Log;
use PhpParser\JsonDecoder;
use stdClass;
use Exception;
use Illuminate\Support\Facades\Config;
use App\Exceptions\SCException;

class SoyalApiController extends Controller
{
    protected $dummyMode = true;

    function __construct()
    {
        $this->dummyMode = env('DUMMY_MODE', true);
    }


    /**
     * @SWG\Post(
     *   path="/deviceRecordTest",
     *   summary="deviceRecordTest",
     *   description="deviceRecordTest",
     *   tags={"SoyalAPI"},
     *   deprecated=false,
     *   @SWG\Parameter(
     *   	name="data",
     *      in="formData",
     *      description="20'05/11 08:52:26 [001.17:0B](7)123456791     AllenSu00000000 (M11)Normal Access",
     *      required=true,
     *      type="string",
     *   ),
     *   @SWG\Response(
     *     response=200, description="successful operation"
     *   )
     * )
     *
     * Display a listing of the resource.
     * @throws Exception
     * @return \Illuminate\Http\Response
     */
    public function deviceRecordTest() {
        Log::info(__METHOD__);
        $data = Request::get('data');//"20'05/11 08:52:26 [001.17:0B](7)123456791     AllenSu00000000 (M11)Normal Access";
//        $data = "20'05/09 09:00:19 [001.17:1C](0)0123456789      (M28)Access by PIN";
//        $data = "20'05/04 18:05:41 [001.17:03](0)0123456789      (M03)Invalid card";
//        $data = "20'05/11 08:54:14 [001.17:01](0) (M01)Keyin the invalid card number";

//        20'05/11 08:54:14 [001.17:01](0) (M01)Keyin the invalid card number
//        20'05/04 18:05:41 [001.17:03](0)00105:06593      (M03)Invalid card
//        20'05/09 09:00:11 [001.17:0B](0)00103:63605      (M11)Normal Access
//        20'05/09 09:00:19 [001.17:1C](0)00103:63605      (M28)Access by PIN
//        20'05/11 08:52:26 [001.17:0B](0)00103:63605     AllenSu00000000 (M11)Normal Access
//        20'05/11 08:57:08 [001.17:1C](0)00103:63605     AllenSu00000000 (M28)Access by PIN

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
            $dataArray[] = $cardUid = substr($data, strpos($data, ')', 0) + 1, 10); // xxxxx:xxxxx
        }


        $soyalUidRecord = new SoyalUidRecord;
        $soyalUidRecord->ip = '118.233.72.82';
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

        Log::info($odooServerApi.'device-record');
        $client = new Client;
        $response = $client->request('POST', $odooServerApi.'device-record', [
            'body' => $payload
        ]);

        $body = $response->getBody();
        $returnData = json_decode($body);

        Log::info(json_encode($returnData));

    }

    /**
     * @SWG1\Post(
     *   path="/socketTimeoutTest",
     *   summary="socketTimeoutTest",
     *   description="socketTimeoutTest",
     *   tags={"SoyalAPI"},
     *   deprecated=false,
     *      @SWG1\Parameter(
     *          name="body",
     *          in="body",
     *          description="JSON Payload",
     *          required=true,
     *          format="application/json",
     *          @SWG1\Schema(
     *              type="object",
     *              @SWG1\Property(property="logid", type="string", example="20200423-2356-5503-19"),
     *              @SWG1\Property(property="device", type="array",
     *                  @SWG1\Items(
     *                      @SWG1\Property(type="string", property="ip", description="IP", example="118.233.72.82"),
     *                      @SWG1\Property(type="string", property="port", description="Node Id", example="1621"),
     *                      @SWG1\Property(type="string", property="node", description="Port", example="001")
     *                  )
     *              )
     *          )
     *      ),
     *   @SWG1\Response(
     *     response=200, description="successful operation"
     *   )
     * )
     *
     * Display a listing of the resource.
     * @throws Exception
     * @return \Illuminate\Http\Response
     */
    public function socketTimeoutTest() {
        Log::info(__METHOD__);
        $payLoad = json_decode(request()->getContent());

        $batchId = $payLoad->logid;
        $retrunData = [];

        foreach ($payLoad->device as $data) {
            $connectSoyalDevice = new SoyalConnectDevice;
            $connectSoyalDevice->batch_id = $batchId;
            $connectSoyalDevice->device_ip = $data->ip;
            $connectSoyalDevice->device_port = $data->port;
            $connectSoyalDevice->node_id = $data->node;
            $connectSoyalDevice->status = 0;

            $status = 0;
            $message = '';
            $timeout = 1;
            $host = $data->ip;
            $port = $data->port;
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
                        $status = 0;
//                    exit();
                        break;
                    }

                    // sleep for a bit
                    usleep(250000);
                }

// re-block the socket if needed
                if($status == 1) {
                    socket_set_block($sock);
                }


                Log::info('b');
            }
            catch (Exception $ex) {
                Log::info('Exception');
                Log::error($ex->getMessage());
                $message = $ex->getMessage();
            }

            $connectSoyalDevice->status = $status;
            $retrunData[] = $connectSoyalDevice;
        }

        return $retrunData;
    }

    /**
     * @SWG1\Post(
     *   path="/aba2wg",
     *   summary="ABA to WG",
     *   description="ABA卡號轉成 WG卡號",
     *   tags={"SoyalAPI"},
     *   deprecated=false,
     *   @SWG1\Parameter(
     *   	name="abaUid",
     *      in="formData",
     *      description="ABA卡號 (0123456789)",
     *      required=true,
     *      type="string",
     *   ),
     *   @SWG1\Response(
     *     response=200, description="successful operation"
     *   )
     * )
     *
     * Display a listing of the resource.
     * @throws Exception
     * @return \Illuminate\Http\Response
     */
    public function aba2wg() {
        Log::info(__METHOD__);
        $abaUid = Request::get('abaUid');

        $wgUid = DeviceService::aba2wg($abaUid);

        return $wgUid;
    }

    /**
     * @SWG\Post(
     *   path="/device-test",
     *   summary="卡機連線測試",
     *   description="發起多部卡機的非同步連線測試",
     *   tags={"SoyalAPI"},
     *   deprecated=false,
     *      @SWG\Parameter(
     *          name="body",
     *          in="body",
     *          description="JSON Payload",
     *          required=true,
     *          format="application/json",
     *          @SWG\Schema(
     *              type="object",
     *              @SWG\Property(property="logid", type="string", example="20200423-2356-5503-19"),
     *              @SWG\Property(property="device", type="array",
     *                  @SWG\Items(
     *                      @SWG\Property(type="string", property="ip", description="IP", example="118.233.72.82"),
     *                      @SWG\Property(type="string", property="port", description="Node Id", example="1621"),
     *                      @SWG\Property(type="string", property="node", description="Port", example="001")
     *                  )
     *              )
     *          )
     *      ),
     *   @SWG\Response(
     *     response=200, description="successful operation"
     *   )
     * )
     *
     * Display a listing of the resource.
     * @throws Exception
     * @return \Illuminate\Http\Response
     */
    public function deviceTest() {
        Log::info(__METHOD__);
        $payLoad = json_decode(request()->getContent());

        $batchId = $payLoad->logid;
        foreach ($payLoad->device as $data) {
            $connectSoyalDevice = new SoyalConnectDevice;
            $connectSoyalDevice->batch_id = $batchId;
            $connectSoyalDevice->device_ip = $data->ip;
            $connectSoyalDevice->device_port = $data->port;
            $connectSoyalDevice->node_id = $data->node;
            $connectSoyalDevice->status = 0; // init
            $connectSoyalDevice->save();

            Log::info(Config::get('database.default'));
        }

        $this->dispatch(new ConnectSoyalDeviceJob($batchId));

        $resultData = new stdClass();
        $resultData->logid = $payLoad->logid;

        return json_encode($resultData);
    }

    /**
     * @SWG\Post(
     *   path="/device-connect",
     *   summary="卡機連線測試 (device-connect)",
     *   description="發起多部卡機的連線測試 (device-connect)",
     *   tags={"SoyalAPI"},
     *   deprecated=false,
     *      @SWG\Parameter(
     *          name="body",
     *          in="body",
     *          description="JSON Payload",
     *          required=true,
     *          format="application/json",
     *          @SWG\Schema(
     *              type="object",
     *              @SWG\Property(property="logid", type="string", example="20200423-2356-5503-19"),
     *              @SWG\Property(property="device", type="array",
     *                  @SWG\Items(
     *                      @SWG\Property(type="string", property="device_id", description="Device ID", example="1"),
     *                      @SWG\Property(type="string", property="ip", description="IP", example="59.120.150.59"),
     *                      @SWG\Property(type="string", property="port", description="Node Id", example="1621"),
     *                      @SWG\Property(type="string", property="node", description="Port", example="1")
     *                  )
     *              )
     *          )
     *      ),
     *   @SWG\Response(
     *     response=200, description="successful operation"
     *   )
     * )
     *
     * Display a listing of the resource.
     * @throws Exception
     * @return \Illuminate\Http\Response
     */
    public function deviceConnect() {
        Log::info(__METHOD__);
        $payLoad = json_decode(request()->getContent());

        $batchId = $payLoad->logid;
        Log::info($batchId);
        foreach ($payLoad->device as $data) {
            $connectSoyalDevice = new SoyalConnectDevice;
            $connectSoyalDevice->batch_id = $batchId;
            $connectSoyalDevice->device_ip = $data->ip;
            $connectSoyalDevice->device_port = $data->port;
            $connectSoyalDevice->node_id = $data->node;
            $connectSoyalDevice->status = 0; // init
            $connectSoyalDevice->save();

            Log::info(Config::get('database.default'));
        }

        // 開始測試
        $connectSoyalDevices = SoyalConnectDevice::where('batch_id', '=', $batchId)
            ->where('status', '=', 0)
            ->get();
        $resultData = new stdClass();
        $resultData->logid = $batchId;
        $resultData->device = [];

        foreach ($connectSoyalDevices as $connectSoyalDevice) {

            $device = DeviceService::connectDeviceSocket($connectSoyalDevice);

            $resultData->device[] = $device;
        }


        return json_encode($resultData);
    }


    /**
     * @SWG\Post(
     *   path="/device",
     *   summary="更新或新增卡片資料",
     *   description="更新或新增一部卡機設定以及卡機內的密碼設定",
     *   tags={"SoyalAPI"},
     *   deprecated=false,
     *      @SWG\Parameter(
     *          name="body",
     *          in="body",
     *          description="JSON Payload",
     *          required=true,
     *          format="application/json",
     *          @SWG\Schema(
     *              type="object",
     *              @SWG\Property(property="logid", type="string", example="20200423-2356-5503-19"),
     *              @SWG\Property(type="string", property="device_id", description="Device ID", example="NK01"),
     *              @SWG\Property(type="string", property="ip", description="IP", example="118.233.72.82"),
     *              @SWG\Property(type="string", property="port", description="Port", example="1621"),
     *              @SWG\Property(type="string", property="node", description="Node Id", example="001"),
     *              @SWG\Property(property="card", type="array",
     *                  @SWG\Items(
     *                      @SWG\Property(type="string", property="event", description="Event", example="add"),
     *                      @SWG\Property(type="string", property="uid", description="Uid", example="0123456789"),
     *                      @SWG\Property(type="string", property="display", description="Display", example="Admin"),
     *                      @SWG\Property(type="string", property="pin", description="Pin Code", example="1999")
     *                  )
     *              )
     *          )
     *      ),
     *   @SWG\Response(
     *     response=200, description="successful operation"
     *   )
     * )
     *
     * Display a listing of the resource.
     * @throws Exception
     * @return \Illuminate\Http\Response
     */
    public function device() {
        Log::info(__METHOD__);
        $payLoad = json_decode(request()->getContent());

        $device = new stdClass;
        $device->device_id = $payLoad->device_id;
        $device->ip = $payLoad->ip;
        $device->port = $payLoad->port;
        $device->node = $payLoad->node;
        $cards = $payLoad->card;

        $resultData = new stdClass;
        $resultData->logid = $payLoad->logid;
        $resultData->card = [];
        foreach ($cards as $card) {
            $resultCard = new stdClass;
            $resultCard->uid = $card->uid;
            $resultCard->result = 0;
            $resultCard->message = '';

            $event = $card->event;

            $soyalDevice = new SoyalDevice;
            $soyalDevice->batch_id = $payLoad->logid;
            $soyalDevice->device_id = $payLoad->device_id;
            $soyalDevice->ip = $payLoad->ip;
            $soyalDevice->port = $payLoad->port;
            $soyalDevice->node = $payLoad->node;
            $soyalDevice->event = $event;
            $soyalDevice->uid = $card->uid;
            $soyalDevice->is_job = 0;

            if($event !== 'delete') {
                $soyalDevice->display = $card->display;
                $soyalDevice->pin = $card->pin;
//                $soyalDevice->pin = $card->pin;
//                $soyalDevice->expire_start = $card->expire_start;
//                $soyalDevice->expire_end = $card->expire_end;
            }
            $soyalDevice->status = 0;

            try {

                $deviceServiceResult = [];
                if($event === "add") {
                    $deviceServiceResult = DeviceService::addUid($soyalDevice); // [1, $result]
                } else if($event === "delete") {
                    $deviceServiceResult = DeviceService::deleteUid($soyalDevice); // [1, $result]
                } else if($event === "update") {
                    $deviceServiceResult = DeviceService::updateUid($soyalDevice); // [1, $result]
                }

                $resultCard->result = $deviceServiceResult[0];

                if($deviceServiceResult[0] === 1) {
                    $soyalDevice->status = 1;

                    if($event === "update") {
                        // cancel pin after 3 hour
                        $cancelSoyalDevice = new SoyalDevice;
                        $cancelSoyalDevice->batch_id = $payLoad->logid;
                        $cancelSoyalDevice->device_id = $payLoad->device_id;
                        $cancelSoyalDevice->ip = $payLoad->ip;
                        $cancelSoyalDevice->port = $payLoad->port;
                        $cancelSoyalDevice->node = $payLoad->node;
                        $cancelSoyalDevice->event = 'cancel_pin';
                        $cancelSoyalDevice->uid = $card->uid;
                        $cancelSoyalDevice->is_job = 1;
                        $cancelSoyalDevice->status = 0;
                        $cancelSoyalDevice->message = '';
                        $cancelSoyalDevice->save();

                        $delaySec = env('CANCEL_UID_PINCODE_DELAY', 60);
                        $this->dispatch((new CancelUidPinJob($cancelSoyalDevice->id))->delay($delaySec));
                    }
                } else {
                    $soyalDevice->status = -1;
                }
                $soyalDevice->message = $deviceServiceResult[1];

            } catch (Exception $ex) {
                Log::error($ex);
                $resultCard->result = 0;
                $resultCard->message = $ex->getMessage();
                $soyalDevice->status = -1;
                $soyalDevice->message = $ex->getMessage();
            }

            $resultData->card[] = $resultCard;
            $soyalDevice->save();
        }

        return json_encode($resultData);
    }


    /**
     * @SWG\Post(
     *   path="/devices-async",
     *   summary="發起多部卡機設定的非同步更新",
     *   description="發起多部卡機設定的非同步更新(一般用於修改通用密碼), (可能會跨群組修改多部卡機)",
     *   tags={"SoyalAPI"},
     *   deprecated=false,
     *      @SWG\Parameter(
     *          name="body",
     *          in="body",
     *          description="JSON Payload",
     *          required=true,
     *          format="application/json",
     *          @SWG\Schema(
     *              type="object",
     *              @SWG\Property(property="logid", type="string", example="20200423-2356-5503-19"),
     *              @SWG\Property(property="device", type="array",
     *                  @SWG\Items(
     *                      @SWG\Property(type="string", property="device_id", description="Device ID", example="NK01"),
     *                      @SWG\Property(type="string", property="ip", description="IP", example="118.233.72.82"),
     *                      @SWG\Property(type="string", property="port", description="Port", example="1621"),
     *                      @SWG\Property(type="string", property="node", description="Node Id", example="001"),
     *                      @SWG\Property(property="card", type="array",
     *                          @SWG\Items(
     *                              @SWG\Property(type="string", property="event", description="Event", example="add"),
     *                              @SWG\Property(type="string", property="uid", description="Uid", example="0123456789"),
     *                              @SWG\Property(type="string", property="display", description="Display", example="Admin"),
     *                              @SWG\Property(type="string", property="pin", description="Pin Code", example="1999")
     *                          )
     *                      )
     *                  )
     *              )
     *          )
     *      ),
     *   @SWG\Response(
     *     response=200, description="successful operation"
     *   )
     * )
     *
     * Display a listing of the resource.
     * @throws Exception
     * @return \Illuminate\Http\Response
     */
    public function devicesAsync() {
        Log::info(__METHOD__);
        $payLoad = json_decode(request()->getContent());

        Log::info(print_r($payLoad, true));

        $batchId = $payLoad->logid;
        $logid = $payLoad->logid;
        $devices = $payLoad->device;

        foreach ($devices as $device) {
            $cards = $device->card;
            $resultData = new stdClass;
            $resultData->logid = $logid;
            $resultData->card = [];
            foreach ($cards as $card) {
                $event = $card->event;

                $soyalDevice = new SoyalDevice;
                $soyalDevice->batch_id = $logid;
                $soyalDevice->device_id = $device->device_id;
                $soyalDevice->ip = $device->ip;
                $soyalDevice->port = $device->port;
                $soyalDevice->node = $device->node;
                $soyalDevice->event = $event;
                $soyalDevice->uid = $card->uid;
                $soyalDevice->is_job = 1;


                if($event !== 'delete' && $event !== 'valid' && $event !== 'invalid') {
                    $soyalDevice->display = $card->display;
                    $soyalDevice->pin = $card->pin;
                }

                $soyalDevice->status = 0;
                $soyalDevice->message = '未處理(Async)';
                $soyalDevice->save();
            }
        }

        $this->dispatch(new SoyalDeviceJob($batchId));

        $resultData = new stdClass;
        $resultData->logid = $logid;

        return json_encode($resultData);
    }


    /**
     * @SWG\Post(
     *   path="/devices-update-pincode",
     *   summary="更新卡機密碼",
     *   description="更新卡機密碼",
     *   tags={"SoyalAPI"},
     *   deprecated=false,
     *      @SWG\Parameter(
     *          name="body",
     *          in="body",
     *          description="JSON Payload",
     *          required=true,
     *          format="application/json",
     *          @SWG\Schema(
     *              type="object",
     *              @SWG\Property(property="logid", type="string", example="20200423-2356-5503-19"),
     *              @SWG\Property(property="device", type="array",
     *                  @SWG\Items(
     *                      @SWG\Property(type="string", property="device_id", description="Device ID", example="NK00"),
     *                      @SWG\Property(type="string", property="ip", description="IP", example="59.120.150.59"),
     *                      @SWG\Property(type="string", property="port", description="Port", example="1621"),
     *                      @SWG\Property(type="string", property="node", description="Node Id", example="1")
     *                  )
     *              )
     *          )
     *      ),
     *   @SWG\Response(
     *     response=200, description="successful operation"
     *   )
     * )
     *
     * Display a listing of the resource.
     * @throws Exception
     * @return \Illuminate\Http\Response
     */
    public function devicesUpdatePincode() {
        Log::info(__METHOD__);
        $payLoad = json_decode(request()->getContent());

        Log::info(print_r($payLoad, true));

        $batchId = $payLoad->logid;
        $logid = $payLoad->logid;
        $devices = $payLoad->device;
        $uid = env('DEVICE_SYSTEM_UID', true);

        foreach ($devices as $device) {

            $soyalDevicePin = new SoyalDevicePin;
            $soyalDevicePin->batch_id = $logid;
            $soyalDevicePin->device_id = $device->device_id;
            $soyalDevicePin->ip = $device->ip;
            $soyalDevicePin->port = $device->port;
            $soyalDevicePin->node = $device->node;
            $soyalDevicePin->pin = '';
            $soyalDevicePin->status = -1;
            $soyalDevicePin->save();
        }


        $soyalDevicePins = SoyalDevicePin::where('batch_id', '=', $batchId)
            ->where('status', '=', -1)
            ->get();

        $resultData = new stdClass();
        $resultData->logid = '';
        $resultData->device = [];

        foreach ($soyalDevicePins as $soyalDevicePin) {
            Log::info(print_r($soyalDevicePin, true));

            try {
                $newPin = '0'.HelpService::generateRandomString(3, '0123456789');
                $ipPin = SoyalIpPin::where('ip', '=', $soyalDevicePin->ip)->first();
                if($ipPin) {
                    $newPin = $ipPin->pin;
                } else {
                    $soyalIpPin = new SoyalIpPin;
                    $soyalIpPin->ip = $soyalDevicePin->ip;
                    $soyalIpPin->pin = $newPin;
                    $soyalIpPin->save();
                }

                $soyalDevicePin->pin = $newPin;

                $deviceServiceResult = DeviceService::updateDevicePincode($soyalDevicePin);

                if($deviceServiceResult[0] === 1) {
                    $soyalDevicePin->status = 1;
                } else {
                    $soyalDevicePin->status = 0;
                }
                $soyalDevicePin->message = $deviceServiceResult[1];

                $soyalDevicePin->save();

            } catch (Exception $ex) {
                Log::error($ex);
                $soyalDevicePin->status = 0;
                $soyalDevicePin->message = $ex->getMessage();
            }

            $resultData->device[] = $soyalDevicePin;
        }


        return json_encode($resultData);
    }


    /**
     * @SWG1\Post(
     *   path="/device-uid-update-pincode",
     *   summary="更新密碼 (UID) 已失效",
     *   description="更新密碼 (UID) 已失效",
     *   tags={"SoyalAPI"},
     *   deprecated=false,
     *      @SWG1\Parameter(
     *          name="body",
     *          in="body",
     *          description="JSON Payload",
     *          required=true,
     *          format="application/json",
     *          @SWG1\Schema(
     *              type="object",
     *              @SWG1\Property(property="logid", type="string", example="20200423-2356-5503-19"),
     *              @SWG1\Property(property="device", type="array",
     *                  @SWG1\Items(
     *                      @SWG1\Property(type="string", property="device_id", description="Device ID", example="NK01"),
     *                      @SWG1\Property(type="string", property="ip", description="IP", example="118.233.72.82"),
     *                      @SWG1\Property(type="string", property="port", description="Port", example="1621"),
     *                      @SWG1\Property(type="string", property="node", description="Node Id", example="001"),
     *                      @SWG1\Property(type="string", property="pin", description="Pin Code", example="1999"),
     *                      @SWG1\Property(property="card", type="array",
     *                          @SWG1\Items(
     *                              @SWG1\Property(type="string", property="uid", description="Uid", example="0123456789")
     *                          )
     *                      )
     *                  )
     *              )
     *          )
     *      ),
     *   @SWG1\Response(
     *     response=200, description="successful operation"
     *   )
     * )
     *
     * Display a listing of the resource.
     * @throws Exception
     * @return \Illuminate\Http\Response
     */
    public function deviceUidUpdatePincode() {
        Log::info(__METHOD__);
        $payLoad = json_decode(request()->getContent());

        Log::info(print_r($payLoad, true));

        $batchId = $payLoad->logid;
        $logid = $payLoad->logid;
        $devices = $payLoad->device;

        $resultData = new stdClass;
        $resultData->logid = $logid;
        $resultData->device = [];
        foreach ($devices as $device) {
            $cards = $device->card;
            $pin = $device->pin;

            $resultDevice = new stdClass;
            $resultDevice->device_id = $device->device_id;
            $resultDevice->ip = $device->ip;
            $resultDevice->port = $device->port;
            $resultDevice->node = $device->node;

            $resultDevice->card = [];
            foreach ($cards as $card) {
                $resultCard = new stdClass;
                $resultCard->uid = $card->uid;
                $resultCard->result = 0;
                $resultCard->message = '';

                $event = 'pin';

                $soyalDevice = new SoyalDevice;
                $soyalDevice->batch_id = $logid;
                $soyalDevice->device_id = $device->device_id;
                $soyalDevice->ip = $device->ip;
                $soyalDevice->port = $device->port;
                $soyalDevice->node = $device->node;
                $soyalDevice->event = $event;
                $soyalDevice->uid = $card->uid;
                $soyalDevice->is_job = 1;
                $soyalDevice->pin = $pin;

//                $soyalDevice->display = $card->display;
//                $soyalDevice->expire_start = $card->expire_start;
//                $soyalDevice->expire_end = date("Y-m-d");;

                $soyalDevice->status = 0;
                $soyalDevice->message = '';

                try {

                    $deviceServiceResult = DeviceService::updateDeviceUidPincode($soyalDevice); // [1, $result]

                    $resultCard->result = $deviceServiceResult[0];

                    if($deviceServiceResult[0] === 1) {
                        $soyalDevice->status = 1;
                    } else {
                        $soyalDevice->status = -1;
                    }
                    $soyalDevice->message = $deviceServiceResult[1];

                } catch (Exception $ex) {
                    Log::error($ex);
                    $resultCard->result = 0;
                    $resultCard->message = $ex->getMessage();
                    $soyalDevice->status = -1;
                    $soyalDevice->message = $ex->getMessage();
                }

                $resultDevice->card[] = $resultCard;
                $soyalDevice->save();

                // cancel pin after 3 hour

                $cancelSoyalDevice = new SoyalDevice;
                $cancelSoyalDevice->batch_id = $logid;
                $cancelSoyalDevice->device_id = $device->device_id;
                $cancelSoyalDevice->ip = $device->ip;
                $cancelSoyalDevice->port = $device->port;
                $cancelSoyalDevice->node = $device->node;
                $cancelSoyalDevice->event = 'cancel_pin';
                $cancelSoyalDevice->uid = $card->uid;
                $cancelSoyalDevice->is_job = 1;
                $cancelSoyalDevice->status = 0;
                $cancelSoyalDevice->message = '';
                $cancelSoyalDevice->save();

                $delaySec = env('CANCEL_UID_PINCODE_DELAY', 60);
                $this->dispatch((new CancelUidPinJob($cancelSoyalDevice->id))->delay($delaySec));
            }

            $resultData->device[] = $resultDevice;
        }

//        $resultData = new stdClass;
//        $resultData->logid = $logid;

        return json_encode($resultData);
    }



}
