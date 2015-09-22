<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Doctrine\CouchDB\CouchDBClient;
use Exception;
use Config;

class AppDeleteDocs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:delete_docs 
                    {database : Database from which documents will be deleted}
                    {type? : Type of documents to delete.}
                    {--all? : Delete all documents, except special docs starting with _}
                    ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete Multiple Docs';

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

        $database = $this->argument('database');
        $couch = CouchDBClient::create(array(
            'dbname' => $database,
            'user' => env('COUCH_APP_USER'),
            'password' => env('COUCH_APP_PASS'),
        ));
        $type = $this->argument('type');
        $doc_req = $couch->allDocs();

        if($doc_req->status == 200 && $doc_req->body) {
            foreach($doc_req->body['rows'] as $row) {
                $doc = $row['doc'];

                if($doc['_id'][0] == '_') { // special docs
                    continue;
                }

                if($this->option('all')) {
                    $this->info("deleting " . $doc['_id']);
                    $couch->deleteDocument($doc['_id'], $doc['_rev']);
                }
                elseif($type && @$doc['type'] == $type) {
                    $this->info("deleting " . $doc['_id']);
                    $couch->deleteDocument($doc['_id'], $doc['_rev']);
                }

            }
        }
    }
}
