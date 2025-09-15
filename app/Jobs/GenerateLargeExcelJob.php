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
use App\Jobs\ProcessExcelChunk;
use App\Jobs\MergeExcelChunks;
use Illuminate\Support\Facades\DB;
class GenerateLargeExcelJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $chunkSize;
    protected $exportPath;

    public function __construct(int $chunkSize = 4000, string $exportPath = null)
    {
        $this->chunkSize = $chunkSize;
        $this->exportPath = $exportPath ?? storage_path('app/exports');
    }

    public function handle()
    {
        $totalUsers = DB::table('users')->count();
        $chunkSize = $this->chunkSize;
        $batchId = (string) Str::uuid();
        $chunkCount = 0;
        
        // Ensure we're using proper storage paths
        $this->exportPath = rtrim($this->exportPath, '/\\');
        $tempDir = storage_path('app/temp_excel');
        
        // Create necessary directories with proper permissions
        $directories = [
            'export' => $this->exportPath,
            'temp' => $tempDir,
            'app_temp' => storage_path('app/temp_excel')
        ];
        
        foreach ($directories as $type => $dir) {
            if (!file_exists($dir)) {
                Log::info("Creating {$type} directory: {$dir}");
                if (!@mkdir($dir, 0755, true) && !is_dir($dir)) {
                    $error = error_get_last();
                    throw new \RuntimeException(sprintf(
                        'Failed to create directory "%s". Error: %s',
                        $dir,
                        $error['message'] ?? 'Unknown error'
                    ));
                }
            } elseif (!is_writable($dir)) {
                throw new \RuntimeException("Directory is not writable: {$dir}");
            }
        }
        
        Log::info("Starting Excel export", [
            'total_users' => $totalUsers,
            'batch_id' => $batchId,
            'chunk_size' => $chunkSize,
            'export_path' => $this->exportPath,
            'temp_dir' => $tempDir,
            'storage_path' => storage_path(),
            'base_path' => base_path(),
            'directories' => array_map(function($dir) {
                return [
                    'path' => $dir,
                    'exists' => file_exists($dir) ? 'yes' : 'no',
                    'writable' => is_writable($dir) ? 'yes' : 'no',
                    'permissions' => substr(sprintf('%o', fileperms($dir)), -4)
                ];
            }, $directories)
        ]);
    
        try {
            // Process users in chunks
            DB::table('users')->select(['id', 'name', 'email', 'created_at'])
               
                ->orderBy('id')
                ->chunk($chunkSize, function($users) use (&$chunkCount, $batchId, $tempDir) {
                    $chunkCount++;
                    
                    Log::info("Dispatching Excel chunk", [
                        'chunk_number' => $chunkCount,
                        'batch_id' => $batchId,
                        'users_count' => $users->count(),
                        'first_user_id' => $users->first() ? $users->first()->id : null,
                        'last_user_id' => $users->last() ? $users->last()->id : null
                    ]);
                    
                    // Get the first user's ID in this chunk
                    $firstUser = $users->first();
                    $startId = $firstUser ? $firstUser->id : 0;
                    
                    ProcessExcelChunk::dispatch(
                        $startId,
                        $this->chunkSize,
                        $chunkCount,
                        $batchId,
                        $tempDir
                    )->onQueue('default');
                });
            
            if ($chunkCount > 0) {
                // Dispatch final merge job with a delay to allow all chunks to be processed
                $mergeDelay = now()->addMinutes(1);
                MergeExcelChunks::dispatch($batchId, $chunkCount, $tempDir, $this->exportPath)
                    ->onQueue('merge')
                    ->delay($mergeDelay);
                    
                Log::info("All chunks dispatched for merging", [
                    'total_chunks' => $chunkCount,
                    'batch_id' => $batchId,
                    'merge_scheduled_for' => $mergeDelay->toDateTimeString()
                ]);
            } else {
                Log::warning("No Excel chunks were created");
            }
            
        } catch (\Exception $e) {
            Log::error("Error in GenerateLargeExcelJob: " . $e->getMessage(), [
                'exception' => $e,
                'batch_id' => $batchId,
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}
