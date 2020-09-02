<?php

namespace App\Jobs;

use App\Exceptions\SCException;
use App\Models\SoyalConnectDevice;
use App\Models\SoyalDevice;
use App\Services\DeviceService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

use Exception;
use stdClass;
use DB;
class SoyalDeviceJob implements ShouldQueue
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
        Log::info('Job Handle SoyalDeviceJob '.$this->batchId);

        try {
            DB::beginTransaction();
            $this->odooServerApi = Config::get('soyal.odooServerApi');

            $soyalDevices = SoyalDevice::where('batch_id', '=', $this->batchId)
                                    ->where('status', '=', 0)
                                    ->get();

            $resultData = new stdClass();
            $resultData->logid = $this->batchId;
            $resultData->device = [];

            foreach ($soyalDevices as $soyalDevice) {

                $resultCard = new stdClass;
                $resultCard->uid = $soyalDevice->uid;
                $resultCard->result = 0;
                $resultCard->message = '';

                try {

                    $deviceServiceResult = [];
                    if($soyalDevice->event === "add") {
                        $deviceServiceResult = DeviceService::addUid($soyalDevice);
                    } else if($soyalDevice->event === "delete") {
                        $deviceServiceResult = DeviceService::deleteUid($soyalDevice);
                    } else if ($soyalDevice->event === "update") {
                        $deviceServiceResult = DeviceService::updateUid($soyalDevice);
                    } else if ($soyalDevice->event === "valid" or $soyalDevice->event === 'invalid') {
                        $deviceServiceResult = DeviceService::changeStatusUid($soyalDevice);
                    }

                    $resultCard->result = $deviceServiceResult[0];

                    if($deviceServiceResult[0] === 1) {
                        $soyalDevice->status = 1;

                        if($soyalDevice->event === "update") {
                            // cancel pin after 3 hour
                            $cancelSoyalDevice = new SoyalDevice;
                            $cancelSoyalDevice->batch_id = $this->batchId;
                            $cancelSoyalDevice->device_id = $soyalDevice->device_id;
                            $cancelSoyalDevice->ip = $soyalDevice->ip;
                            $cancelSoyalDevice->port = $soyalDevice->port;
                            $cancelSoyalDevice->node = $soyalDevice->node;
                            $cancelSoyalDevice->event = 'cancel_pin';
                            $cancelSoyalDevice->uid = $soyalDevice->uid;
                            $cancelSoyalDevice->is_job = 1;
                            $cancelSoyalDevice->status = 0;
                            $cancelSoyalDevice->message = '';
                            $cancelSoyalDevice->save();

                            Log::info('Cancel Soayl Device Id is '.$cancelSoyalDevice->id);
                            $delaySec = env('CANCEL_UID_PINCODE_DELAY', 60);

                            CancelUidPinJob::dispatch($cancelSoyalDevice->id)->delay($delaySec);
//                            $this->dispatch((new CancelUidPinJob($cancelSoyalDevice->id))->delay($delaySec));
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

            DB::commit();

            try {
                Log::info($this->odooServerApi.'devices-async-result');

                $payload = json_encode($resultData);
                Log::info($payload);

                $client = new Client;
                $response = $client->request('POST', $this->odooServerApi.'devices-async-result', [
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
