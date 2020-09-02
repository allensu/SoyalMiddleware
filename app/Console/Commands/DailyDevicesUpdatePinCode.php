<?php

namespace App\Console\Commands;

use App\Jobs\DailyDevicesUpdatePinCodeJob;
use App\Models\SoyalDevice;
use App\Services\DeviceService;
use App\Services\HelpService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Console\Command;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use stdClass;
use App\Models\SoyalDevicePin;
use App\Models\SoyalIpPin;
use Exception;
use DB;

class DailyDevicesUpdatePinCode extends Command
{
    use DispatchesJobs;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'soyal:daily-devices-update-pin-code';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Daily update pincode to every device';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        Log::info(__METHOD__);
        try {
            DB::beginTransaction();
//            // get devices data from odoo api
            $odooServerApi = Config::get('soyal.odooServerApi');
            $client = new Client;
//            $response = $client->request('POST', $odooServerApi.'devices-query');
//            https://morespace.smiletime.com.tw/access/api/devices
            $response = $client->request('GET', $odooServerApi.'api/devices');
            $body = $response->getBody();
            Log::info($body);
            $returnData = json_decode($body);

            SoyalIpPin::truncate();

            $resultData = new stdClass();
            $resultData->logid = '';
            $resultData->device = [];
            $batchId = '_'.date("YmdHis");
            foreach ($returnData->data as $device) {
                Log::info(print_r($device, true));

//                "id": 1,
//                "code": "SLD001",
//                "name": "士林門市入口",
//                "ip": "118.233.72.82",
//                "model_type": "Soyal",
//                "port": 1621,
//                "node_id": "1",
//                "floor": 1,
//                "pass_code": null,
//                "pass_code_updated_at": "2020-07-01T12:00:00+08:00",
//                "created": "2020-07-08T15:17:06.254602+08:00",
//                "store": 1
                $soyalDevicePin = new SoyalDevicePin;
                $soyalDevicePin->batch_id = $batchId;
                $soyalDevicePin->device_id = $device->code;
                $soyalDevicePin->ip = $device->ip;
                $soyalDevicePin->port = $device->port;
                $soyalDevicePin->node = $device->node_id;
                $soyalDevicePin->pin = '';
                $soyalDevicePin->status = -1;
                $soyalDevicePin->save();
            }

            $this->dispatch(new DailyDevicesUpdatePinCodeJob($batchId));

            DB::commit();
        } catch(RequestException $ex) {
            Log::info("requestException");
            Log::error($ex);
            DB::rollback();
        } catch(Exception $ex) {
            Log::info("exception");
            Log::error($ex);
            DB::rollback();
        }
    }
}
