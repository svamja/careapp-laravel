<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Doctrine\CouchDB\CouchDBClient;
use Exception;

class AppSetup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:setup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create Database Structure';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {

            // Create Admin User
            $couch = CouchDBClient::create(array(
                'dbname' => '_users',
            ));
            $http_client = $couch->getHttpClient();
            $http_client->request('PUT', '/_config/admins/' . env('COUCH_ADMIN_USER'), '"' . env('COUCH_ADMIN_PASS') . '"');

            // Create Databases
            $couch = CouchDBClient::create(array(
                'dbname' => '_users',
                'user' => env('COUCH_ADMIN_USER'),
                'password' => env('COUCH_ADMIN_PASS'),
            ));
            $databases = [
                "careapp_profiles_db",
                "careapp_passions_db",
                "careapp_messages_db",
                "careapp_log_db", 
            ];
            foreach($databases as $database) {
                try {
                    $couch->createDatabase($database);
                    $this->info("$database created");
                }
                catch(Exception $e) {
                     $this->info("$database not created : " . $e->getMessage());
                }
            }

            // Create App User
            $username = env("COUCH_APP_USER");
            $user_id = "org.couchdb.user:$username";
            $user = [
                '_id' => $user_id,
                'type' => 'user',
                'roles' => [],
                'name' => $username,
                'password' => env("COUCH_APP_PASS"),
            ];
            try {
                $couch->putDocument($user, $user['_id']);
                $this->info("App user created");
            }
            catch(Exception $e) {
                $this->info("App user not created.");
            }

     }
}
