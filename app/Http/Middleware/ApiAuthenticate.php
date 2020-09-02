<?php

namespace App\Http\Middleware;

//use App\Models\Member;
//use App\Models\MemberToken;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;

use Illuminate\Support\Facades\Session;
use Closure;
use stdClass;
use Exception;

class ApiAuthenticate {

    public function handle($request, Closure $next) {
        Log::info(__METHOD__);

        $token = Request::header('token');

        if (empty($token)) {
            $token = Request::get('token');
        }

        if (empty($token)) {
            // 未登入
            throw new Exception('invalid api token');
        }

        $userRepository = new UserRepository();
        $authUser = $userRepository->checkToken($token);

        if(empty($authUser)) {
            throw new Exception('無效的token');
        } else {
            Session::put('authUser', $authUser);
            return $next($request);
        }

        return $next($request);
    }
}
