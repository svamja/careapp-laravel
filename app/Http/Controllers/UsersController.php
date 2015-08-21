<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use Log;
use Doctrine\CouchDB\CouchDBClient;
use Facebook;
use Exception;

class UsersController extends Controller
{

    public function login(Request $request) {

        // Logging Response from Facebook into DB
        $couch = CouchDBClient::create(array('dbname' => 'careapp_full_db'));
        $fb_response = $request->input('fb_response');
        $fb_response['type'] = 'fb_response';
        $fb_response['timestamp'] = time();
        $couch->postDocument($fb_response);
        $response = [];

        // Validate response from FB
        if(empty($fb_response['status']) ||
            $fb_response['status'] != 'connected' ||
            empty($fb_response['authResponse']['accessToken']) ||
            empty($fb_response['authResponse']['userID']) ||
            empty($fb_response['authResponse']['expiresIn']))
        {
            Log::error('FB Status: Not Connected / Invalid');
            $response['status'] = 'error';
            return $response;
        }

        // Get User Profile
        $fb = new Facebook\Facebook([
            'app_id' => env('FB_APP_ID'),
            'app_secret' => env('FB_APP_SECRET'),
            'default_graph_version' => 'v2.2',
        ]);
        try {
            $access_token = $fb_response['authResponse']['accessToken'];
            $fb_query = $fb->get('/me?fields=id,name,email,gender,picture', $access_token);
        }
        catch(Exception $e) {
            Log::error('FB Error: ' . $e->getMessage());
            $response['status'] = 'error';
            return $response;
        }
        $fb_user = $fb_query->getGraphUser();

        // Check if requested UserID and AuthToken are for same FB User
        if($fb_response['authResponse']['userID'] != $fb_user['id']) {
            // Possible forgery
            Log::warning('Possible threat: UC59');
            $response['status'] = 'error';
            return $response;
        }

        // Check if user exists in DB
        $couch = CouchDBClient::create(array('dbname' => 'careapp_user_db'));
        $user_req = $couch->findDocument('fb-' . $fb_user['id']);
        if($user_req->status == 200 && $user_req->body['_id']) {
            // User Exists
            $user = $user_req->body;
        } 
        else {
            // New User
            $user = [
                '_id' => 'fb-' . $fb_user['id'],
                'type' => 'user',
                'fb_id' => $fb_user['id'],
                'email' => $fb_user['email'],
                'name' => $fb_user['name'],
                'gender' => $fb_user['gender'],
            ];
        }

        $user['password'] = $this->_generate_password();
        $user['fb_access_token'] = $fb_response['authResponse']['accessToken'];
        $user['fb_expires_in'] = $fb_response['authResponse']['expiresIn'];
        $user['fb_expire_time'] = time() + $fb_response['authResponse']['expiresIn'];

        // Creating/Update User
        if($fb_user['picture']['url'] && !$fb_user['picture']['is_silhouette']) {
            $user['fb_picture'] = $fb_user['picture']['url'];
        }
        try {
            $couch->putDocument($user, $user['_id']);
        }
        catch(Exception $e) {
            Log::error('DB Error: ' . $e->getMessage());
            $response['status'] = 'error';
            return $response;
        }

        $response['status'] = 'success';
        $response['token'] = $user['password'];

        return $response;
    }

    protected function _generate_password() {
        $length = 64;
        $curr_length = 0;

        $chars = "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ";
        $base = strlen($chars);
        $r = 0;
        $password = "";
        while($curr_length < $length) {
            if($r < $base) {
                $r = intval(100000*(rand(1100100100,9998998998) + microtime(true)));
            }
            $k = $r % $base;
            $r = intval($r/$base);
            $password .= $chars[$k];
            $curr_length++;
        }

        return $password;
    }

}
