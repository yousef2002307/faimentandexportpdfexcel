<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateLargePdfJob;
use App\Jobs\GenerateLargeExcelJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
class ReportController extends Controller
{
    public function createReport(Request $request)
    {
        // Dispatch the job to the queue, passing the user for notification
        GenerateLargePdfJob::dispatch();
       
       return "done";
    }
    public function createExcelReport(Request $request)
    {
        // Dispatch the job to the queue, passing the user for notification
        GenerateLargeExcelJob::dispatch();
       
       return "done";
    }
}