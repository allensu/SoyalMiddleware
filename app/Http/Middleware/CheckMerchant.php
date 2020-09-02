<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Session;
use Exception;

class CheckMerchant
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     * @throws Exception
     */
    public function handle($request, Closure $next)
    {
        $authUser = Session::get('authUser');

        if($authUser->is_merchant == 0) {
            throw new Exception('非商戶, 禁止使用.');
        }

        return $next($request);
    }
}
