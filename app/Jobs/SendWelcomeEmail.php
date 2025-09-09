<?php
namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Mail\WelcomeEmail;
use Illuminate\Support\Facades\Mail;
use App\Models\User;
class SendWelcomeEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $user;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
  
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try{
            for($i=0;$i<100;$i++){
       $user=User::create([
        "name"=>"gdfd",
        "email"=>uniqid()."jo@jj.com",
        "password"=>"fdgg"
       ]);
    }
    }
    catch(Exception $e){
        Log::error($e->getMessage());
    }
}
}