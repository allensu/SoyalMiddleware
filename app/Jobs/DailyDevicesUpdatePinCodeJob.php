<?php

namespace App\Jobs;

use App\Models\SoyalDevicePin;
use App\Models\SoyalIpPin;
use App\Services\DeviceService;
use App\Services\HelpService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Exception;
use stdClass;

class DailyDevicesUpdatePinCodeJob implements ShouldQueue
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
        Log::info('Job Handle DailyDevicesUpdatePinCodeJob'.$this->batchId);

        try {
            DB::beginTransaction();
            $this->odooServerApi = Config::get('soyal.odooServerApi');

            $soyalDevicePins = SoyalDevicePin::where('batch_id', '=', $this->batchId)
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

                } catch (Exception $ex) {
                    Log::error($ex);
                    $soyalDevicePin->status = 0;
                    $soyalDevicePin->message = $ex->getMessage();
                }

                $resultData->device[] = $soyalDevicePin;
                $soyalDevicePin->save();
            }

            DB::commit();


            try {
                //            if(false) {
                $payload = json_encode($resultData);
                $client = new Client;
                Log::info($this->odooServerApi.'device-update');
                $response = $client->request('POST', $this->odooServerApi.'device-update', [
                    'body' => $payload
                ]);

                $body = $response->getBody();
                $returnData = json_decode($body);
//            }
                Log::info($body);
                Log::info(json_encode($resultData));
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
