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
            $couch = CouchDBClient::create(array(
                'dbname' => 'careapp_log_db',
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
     }
}
