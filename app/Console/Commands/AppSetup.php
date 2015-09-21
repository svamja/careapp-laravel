<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Doctrine\CouchDB\CouchDBClient;
use Exception;
use Config;

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

    protected function _upsert($couch, $doc) {
        $doc_req = [];
        if(empty($doc['_id'])) {
            return false;
        }
        try {
            $doc_req = $couch->findDocument($doc['_id']);
            if($doc_req->status == 200 && $doc_req->body['_rev']) {
                $couch->putDocument($doc, $doc['_id'], $doc_req->body['_rev']);
            }
            else {
                $couch->putDocument($doc, $doc['_id']);
            }
        }
        catch(Exception $e) {
            throw($e);
            return false;
        }
        return true;
    }

    protected function _create_users_dbs() {
        
        // Create Admin User
        $couch = CouchDBClient::create(array(
            'dbname' => '_users',
        ));
        $http_client = $couch->getHttpClient();
        $http_client->request('PUT', '/_config/admins/' . env('COUCH_ADMIN_USER'), '"' . env('COUCH_ADMIN_PASS') . '"');
        $this->info("Admin user (re)created.");


        // Create Databases
        $couch = CouchDBClient::create(array(
            'dbname' => '_users',
            'user' => env('COUCH_ADMIN_USER'),
            'password' => env('COUCH_ADMIN_PASS'),
        ));

        $databases = Config::get("careapp.databases");
        if(empty($databases)) {
            $this->info("Warning: No Databases found in Config file.");
            return;
        }
        foreach($databases as $database) {
            try {
                $couch->createDatabase($database);
                $this->info("$database created");
            }
            catch(Exception $e) {
                 $this->info("$database not created.");
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

    protected function _create_categories() {

        $couch = CouchDBClient::create(array(
            'dbname' => 'careapp_passions_db',
            'user' => env('COUCH_APP_USER'),
            'password' => env('COUCH_APP_PASS'),
        ));

        $categories = Config::get("careapp.categories");
        if(empty($categories)) {
            $this->info("Warning: No Categories found in Config file.");
            return;
        }

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

    protected function _create_design_docs() {
        $design_docs = Config::get("careapp.design_docs");
        if(empty($design_docs)) {
            $this->info("Warning: No Design Docs definitions found in Config file.");
            return;
        }
        foreach($design_docs as $design_doc) {
            $couch = CouchDBClient::create(array(
                'dbname' => $design_doc['db'],
                'user' => env('COUCH_ADMIN_USER'),
                'password' => env('COUCH_ADMIN_PASS'),
            ));
            unset($design_doc['db']);
            if($this->_upsert($couch, $design_doc)) {
                $this->info("Design Doc Upserted: " . $design_doc['_id']);
            }
            else {
                $this->info("Unable to Upsert Design Doc: " . $design_doc['_id']);
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

            // Users and Databases
            $this->info("-- USERS & DATABASES --");
            $this->_create_users_dbs();

            // Categories
            $this->info("-- CATEGORIES --");
            $this->_create_categories();

            // Design Docs
            $this->info("-- DESIGN DOCS --");
            $this->_create_design_docs();
     }
}
