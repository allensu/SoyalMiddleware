<?php

namespace App\Jobs;

use App\Models\SoyalDevice;
use App\Models\SoyalDevicePin;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use SebastianBergmann\CodeCoverage\Report\PHP;
use App\Services\DeviceService;
use Illuminate\Support\Facades\Log;


class CancelUidPinJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $soyalDeviceId;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($soyalDeviceId)
    {
        $this->soyalDeviceId = $soyalDeviceId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Log::info('Job Handle CancelUidPinJob');


        try {
            Log::info($this->soyalDeviceId);
            $soyalDevices = SoyalDevice::find($this->soyalDeviceId);
            $deviceServiceResult = DeviceService::cancelDeviceUidPincode($soyalDevices);

            if($deviceServiceResult[0] === 1) {
                $soyalDevices->status = 1;
            } else {
                $soyalDevices->status = -1;
            }
            $soyalDevices->message = $deviceServiceResult[1];

        } catch(RequestException $ex) {
            Log::info("requestException");
            Log::error($ex);
            $soyalDevices->status = -1;
            $soyalDevices->message = $ex->getMessage();
        } catch(Exception $ex) {
            Log::info("exception");
            Log::error($ex);
            $soyalDevices->status = -1;
            $soyalDevices->message = $ex->getMessage();
        }

        $soyalDevices->save();
    }
}
