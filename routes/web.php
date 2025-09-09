<?php

use Illuminate\Support\Facades\Route;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Cache;
use App\Models\Post;
use Illuminate\Support\Facades\Redis;
use App\Jobs\SendWelcomeEmail;
use Illuminate\Pipeline\Pipeline;
use App\Pipes\AddCheese;
use App\Pipes\AddLettuce;
use App\Pipes\ToastBread;
use App\Http\Controllers\sandwich;
use App\Service\ScanService;
use App\Http\Controllers\ReportController;

Route::get('/generate-report', [ReportController::class, 'createReport']);
Route::get('/generate-report2', [ReportController::class, 'createExcelReport']);
Route::get("/downloadfilefromstorage",function(){
    $file = storage_path('app/exports/users_export_20250909_082916.xlsx');
    return response()->download($file);
});
Route::get('/', function () {
       // Get the current cache driver
  
    
    // Store and retrieve a test value
    $testKey = 'tes555555tj';
 Redis::set($testKey, 'Te33st val444ue');
  Redis::expire($testKey, 10);
  $value = Redis::get($testKey);
    if(Redis::exists($testKey)){
        return $value;
    }
    return 'not found';

});
Route::get("/send",function(){
    SendWelcomeEmail::dispatch();
});
Route::get("/pdf",function(){
// Load the view with data
$posts = Post::all();
$pdf = PDF::loadView('pdf',['posts'=>$posts]);

// Optional: Configure PDF settings
$pdf->setPaper('A4', 'portrait'); // Paper size & orientation
$pdf->setOptions(['defaultFont' => 'sans-serif']); // Font

// Return the PDF as a download
return $pdf->download('document.pdf');

});
Route::get("/tttt/{product}/{quantity}/{id}",function($product,$quantity,$id){
    // A unique lock name for this specific product
    $lockName = 'product-stock' . $id;

    // Acquire a lock for 10 seconds.
    // The second parameter is the waiting duration in seconds.
    $lock = Cache::lock($lockName, 10);
    if (!$lock->get()) {
        return response()->json(['message' => 'The system is busy, please try again.'], 503);
    }
    try {
        $lock->block(10);
        sleep(10);
        // Check if there is enough stock
        if (true) {
            // Decrement the stock
            // Process the order and create the order record...
            return response()->json(['message' => 'Order placed successfully.']);
        }

        return response()->json(['message' => 'Not enough stock available.'], 409);
    } finally {
        // Ensure the lock is released, even if an exception occurs
        $lock->release();
    }
});



Route::get("/sandwich",function(){
try{
// 1. Create the initial object (the "traveler")
$sandwich = new ScanService();
$sandwich->cheese = false;
$sandwich->lettuce = false;
$sandwich->toasted = false;

// 2. Define the pipes
$pipes = [
    AddCheese::class,
    AddLettuce::class,
    ToastBread::class,
];
\DB::transaction(function () use ($sandwich, $pipes) {
// 3. Create a new Pipeline instance and process the object
 app(Pipeline::class)
    ->send($sandwich)
    ->through($pipes)
    ->thenReturn();
    
});
// The final state of the sandwich after passing through all pipes
return response()->json([
    'sandwich' => 'finished',
]);
}
catch(Exception $e){
    return response()->json(['error' => $e->getMessage()]);
}
});