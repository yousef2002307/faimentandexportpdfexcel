<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use App\Jobs\ProcessPdfChunk;
use App\Jobs\MergePdfChunks;
use Illuminate\Support\Facades\DB;
class GenerateLargePdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $chunkSize;

    public function __construct(int $chunkSize = 200)
    {
        $this->chunkSize = $chunkSize;
    }

    public function handle()
    {
        $totalUsers = DB::table('users')->count();
        $chunkSize = $this->chunkSize;
        $batchId = (string) Str::uuid();
        $chunkCount = 0;
        
        Log::info("Starting PDF generation", [
            'total_users' => $totalUsers,
            'batch_id' => $batchId
        ]);
    
        // Create reports directory if it doesn't exist
        $publicPath = storage_path('app/reports');
        if (!file_exists($publicPath)) {
            mkdir($publicPath, 0755, true);
        }
    
        // Process users in chunks
        DB::table('users')->select(['id', 'name', 'email', 'created_at'])
            
            ->orderBy('id')
            ->chunk($chunkSize, function($users) use (&$chunkCount, $batchId, $chunkSize) {
                $chunkCount++;
                
                Log::info("Dispatching chunk", [
                    'chunk_number' => $chunkCount,
                    'batch_id' => $batchId
                ]);
                
                // Get the first user's ID in this chunk
                $firstUser = $users->first();
                $startId = $firstUser ? $firstUser->id : 0;
                
                ProcessPdfChunk::dispatch(
                    $startId,
                    $chunkSize,  // Keep using the original chunk size
                    $chunkCount,
                    $batchId
                )->onQueue('default');  
            });
        
        // Dispatch final merge job
        MergePdfChunks::dispatch($batchId, $chunkCount)
            ->onQueue('merge')
            ->delay(now()->addMinutes(1));
            
        Log::info("All chunks dispatched", [
            'total_chunks' => $chunkCount,
            'batch_id' => $batchId
        ]);
    }
}

