<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\App;
use GuzzleHttp\Client;

class LaravelHTMLValidator extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'html:validate
                            {--email=*}
                            ';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Validate all of your HTML files';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /*
     * Protected variables
    */

    protected $viewFiles = array();
    protected $validatorURI = 'https://validator.nu';
    protected $userEmail;

    protected function sendMail($email, $data)
    {
        Mail::send([], [], function ($message) use ($email, $data)
        {
            $message->from(env('ADMIN_EMAIL'));
            $message->to($email);
            $message->subject('HTML Validation results');
            $message->setBody($data, 'text/html');
        });
    }

    protected function validateHTML($html, $view)
    {

        $args = array(
            'fragment'  => $html,
            'out'       => 'json'
        );

        $headers = array(
            'Content-Type'  => 'text/html'
        );

        $client = new Client();

        $res = $client->post($this->validatorURI,
               [
                   'body'       => $html,
                   'headers'    => $headers,
                   'query'      => $args,
               ]);

        $validatorMessages = json_decode($res->getBody());
        $emailData = [];

        foreach ($validatorMessages->messages as $validatorMessage)
        {
            if(empty($validatorMessage->message) || empty($validatorMessage->lastLine)) continue;
            
            $customValidationMessage = "Found on line " . $validatorMessage->lastLine . " for view " . $view . ": " . $validatorMessage->message . "\n";

            if (strtolower(App::environment()) == "production" && strlen($this->userEmail) > 0)
            {
                array_push($emailData, $customValidationMessage);
                continue;
            }
            
            echo $customValidationMessage;
        }

        $emailData = implode("\n", $emailData);

        if (strlen($emailData) > 0 && strlen($this->userEmail) > 0)
        {
            $this->sendMail($this->userEmail, $emailData);
        }

    }

    protected function recursiveScan($dir, &$results = array())
    {
        $files = scandir($dir);
        unset($files[0]);
        unset($files[1]);
        
        foreach($files as $key => $value)
        {
            $path = realpath($dir.DIRECTORY_SEPARATOR.$value);
            
            if(!is_dir($path) && strpos($path, '.blade.php') !== false) $results[] = $path;

            else
            {
                $this->recursiveScan($path, $results);
            }
        }

        return $results;
    }

    private function getViews()
    {
        $viewPaths = Config::get('view.paths');
        
        if (count($viewPaths) < 1) return;

        $viewTemplates = $this->recursiveScan($viewPaths[0]);

        foreach ($viewTemplates as $index => $viewTemplate)
        {
            $viewTemplate = preg_replace('/(.*)views[\/]/', '', $viewTemplate);

            $viewTemplate = strtr($viewTemplate, array("/" => ".", ".blade.php" => ""));
            $viewTemplates[$index] = $viewTemplate;
        }

        return $viewTemplates;

    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $emailAll = $this->option('email');
        $comma = ",";

        if ( strpos($emailAll[0], $comma) !== false)
        {
            $arrayNew = explode($comma, $emailAll[0]);
            $emailAll = [];
            foreach ($arrayNew as $arrayEmail)
            {
                array_push($emailAll, $arrayEmail);
            }
        } 

        if (count($emailAll) > 1)
        {
            $emailAll = implode(",", $emailAll);
            $this->userEmail = $emailAll;
        }
        elseif (count($emailAll) == 1)
        {
            $this->userEmail = $emailAll[0];
        }

        $viewNames = $this->getViews();
        foreach ($viewNames as $viewName)
        {
            if(!view()->exists($viewName)) continue;
            try {
                $viewHTML = View::make($viewName)->render();
                $this->validateHTML($viewHTML, $viewName);
            } catch (\Exception $e) {
                continue;
            }
        }
        
    }
}
