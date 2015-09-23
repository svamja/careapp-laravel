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

        try {

            // Logging Response from Facebook into DB
            $couch = CouchDBClient::create(array(
                'dbname' => 'careapp_log_db',
                'user' => env('COUCH_APP_USER'),
                'password' => env('COUCH_APP_PASS'),
            ));
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
            $access_token = $fb_response['authResponse']['accessToken'];
            $fb_query = $fb->get('/me?fields=id,name,email,gender,picture', $access_token);
            $fb_user = $fb_query->getGraphUser();

            // Check if requested UserID and AuthToken are for same FB User
            if($fb_response['authResponse']['userID'] != $fb_user['id']) {
                // Possible forgery
                Log::warning('Possible threat: UC59');
                $response['status'] = 'error';
                return $response;
            }

            // Check if user exists in DB
            $couch = CouchDBClient::create(array(
                'dbname' => '_users',
                'user' => env('COUCH_ADMIN_USER'),
                'password' => env('COUCH_ADMIN_PASS'),
            ));


            $username = 'fb-' . $fb_user['id'];
            $user_id = "org.couchdb.user:$username";

            $user_req = $couch->findDocument($user_id);
            $is_existing_user = false;
            if($user_req->status == 200 && $user_req->body['_id']) {
                // User Exists
                $is_existing_user = true;
                $user = $user_req->body;
            } 
            else {
                // New User
                $user = [
                    '_id' => $user_id,
                    'type' => 'user',
                    'roles' => [],
                    'fb_id' => $fb_user['id'],
                    'display_name' => $fb_user['name'],
                    'email' => $fb_user['email'],
                    'name' => $username,
                    'gender' => $fb_user['gender'],
                ];
            }

            $user['password'] = $this->_generate_password();
            $user['fb_access_token'] = $fb_response['authResponse']['accessToken'];
            $user['fb_expires_in'] = $fb_response['authResponse']['expiresIn'];
            $user['fb_expire_time'] = time() + $fb_response['authResponse']['expiresIn'];

            // Create/Update User
            if($fb_user['picture']['url'] && !$fb_user['picture']['is_silhouette']) {
                $user['fb_picture'] = $fb_user['picture']['url'];
                $response['fb_picture'] = $user['fb_picture'];
            }
            $couch->putDocument($user, $user['_id']);

            $response['gender'] = $user['gender'];
            $response['display_name'] = $user['display_name'];
            $response['status'] = 'success';
            $response['token'] = $user['password'];

            // If existing user, fetch profile too
            if($is_existing_user) {
                $couch = CouchDBClient::create(array(
                    'dbname' => 'careapp_profiles_db',
                    'user' => env('COUCH_APP_USER'),
                    'password' => env('COUCH_APP_PASS'),
                ));
                $profile_req = $couch->findDocument($username);
                if($profile_req->status == 200 && $profile_req->body['_id']) {
                    $response['profile'] = $profile_req->body;
                }
            }
            return $response;

        }

        catch(Exception $e) {
            Log::error($e->getMessage());
            $response['status'] = 'error';
            return $response;
        }

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
