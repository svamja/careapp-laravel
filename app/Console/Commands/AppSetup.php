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

    protected function _slug($text)
    { 
        // replace non letter or digits by -
        $text = preg_replace('~[^\\pL\d]+~u', '-', $text);
        // trim
        $text = trim($text, '-');
        // transliterate
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        // lowercase
        $text = strtolower($text);
        // remove unwanted characters
        $text = preg_replace('~[^-\w]+~', '', $text);
        if (empty($text))
        {
            return 'n-a';
        }
        return $text;
    }


    protected function _create_categories() {

        $couch = CouchDBClient::create(array(
            'dbname' => 'careapp_passions_db',
            'user' => env('COUCH_APP_USER'),
            'password' => env('COUCH_APP_PASS'),
        ));

        $categories = [
            "Basic Needs",
            "Health",
            "Social",
            "Children & Youth",
            "Women",
            "Seniors & Specially Abled",
            "Animals",
            "Safety",
            "Environment",
            "Spiritual",
            "Happiness",
            "Others",
        ];

        foreach($categories as $i => $category_text) {
            $order = $i + 1;
            $category_slug = $this->_slug($category_text);
            $category_id = "cat-" . $category_slug;
            $category = [
                "_id" =>  $category_id,
                "type" => "category",
                "slug" => $category_slug,
                "name" => $category_text,
                "order" => $order
            ];

            try {
                $couch->putDocument($category, $category['_id']);
                $this->info("Category added: $category_text");
            }
            catch(Exception $e) {
                $this->info("Unable to add category: $category_text");
            }

        }

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

            // Create Categories
            $this->_create_categories();



     }
}
