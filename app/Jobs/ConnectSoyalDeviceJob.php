<?php

namespace App\Jobs;

use App\Exceptions\SCException;
use App\Models\SoyalConnectDevice;
use App\Services\DeviceService;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Exception;
use stdClass;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Config;
use DB;

class ConnectSoyalDeviceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $odooServerApi;
    protected $batchId;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($batchId)
    {
        $this->batchId = $batchId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Log::info('Job Handle ConnectSoyalDeviceJob '.$this->batchId);
        $dummyMode = env('DUMMY_MODE', true);

        try {
            DB::beginTransaction();
            $this->odooServerApi = Config::get('soyal.odooServerApi');

            $connectSoyalDevices = SoyalConnectDevice::where('batch_id', '=', $this->batchId)
                ->where('status', '=', 0)
                ->get();
            $resultData = new stdClass();
            $resultData->logid = $this->batchId;
            $resultData->device = [];

            foreach ($connectSoyalDevices as $connectSoyalDevice) {

                if($dummyMode) {
                    $device = new stdClass();
                    $device->ip = $connectSoyalDevice->device_ip;
                    $device->port = $connectSoyalDevice->device_port;
                    $device->node = $connectSoyalDevice->node_id;
                    $device->status = 1;
                } else {
//                    $device = DeviceService::connectDevice($connectSoyalDevice);
                    $device = DeviceService::connectDeviceSocket($connectSoyalDevice);
                }

                $resultData->device[] = $device;
            }

            DB::commit();

            try {
                Log::info($this->odooServerApi.'device-test-result');

                $payload = json_encode($resultData);
//            Log::info($payload);

                $client = new Client;
                $response = $client->request('POST', $this->odooServerApi.'device-test-result', [
                    'body' => $payload
                ]);

                $body = $response->getBody();
                $returnData = json_decode($body);

                Log::info(json_encode($returnData));
            } catch(RequestException $ex) {
                Log::info("requestException to moreapi");
                Log::error($ex);
            } catch(Exception $ex) {
                Log::info("exception to moreapi");
                Log::error($ex);
            }

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
