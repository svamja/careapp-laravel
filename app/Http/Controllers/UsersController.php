<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use Log;

class UsersController extends Controller
{

    public function log_fb_response(Request $request) {
        Log::info('log_fb_response started');
        Log::info($request->input('fb_response'));
        $response['status'] = 'success';
        return $response;
    }


}
