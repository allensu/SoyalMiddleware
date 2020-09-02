<?php

namespace App\Http\Middleware;

use App\Services\CommonService;
use App\Services\HelpService;
use Closure;
use Log;
use Request;

class ApiResponse {
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next, $guard = null) {
        $response = $next($request);
        $content = $response->content();
        if (HelpService::isJSON($content)) {
            $content = json_decode($content);
        }

        $statusCode = $response->getStatusCode();
        if (!in_array($statusCode, [200, 201])) {
            $msg = $content->message;
            $code = -1;
            $data = null;
        } else {
            $msg = "";
            $code = 1;
            $data = $content;
        }

        return response()->json([
            'message' => $msg,
            'status' => $code,
            'data' => $data,
        ], 200);
    }
}
