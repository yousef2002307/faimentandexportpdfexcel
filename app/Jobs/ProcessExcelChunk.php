<?php

namespace App\Jobs;

use App\Models\User;
use App\Exports\UserExport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Log;

class ProcessExcelChunk implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $startId;
    public $chunkSize;
    public $chunkNumber;
    public $batchId;
    public $tempDir;

    public function __construct(
        int $startId, 
        int $chunkSize, 
        int $chunkNumber, 
        string $batchId,
        string $tempDir
    ) {
        $this->startId = $startId;
        $this->chunkSize = $chunkSize;
        $this->chunkNumber = $chunkNumber;
        $this->batchId = $batchId;
        $this->tempDir = $tempDir;
    }

    public function handle()
    {
        try {
            // Ensure the temp directory exists and is writable
            $tempDir = rtrim($this->tempDir, '/\\');
            $appTempDir = storage_path('app/temp_excel');
            
            // Create directories if they don't exist
            foreach ([$tempDir, $appTempDir] as $dir) {
                if (!file_exists($dir)) {
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
            
            // Generate filenames for both possible locations
            $filename = "chunk_{$this->batchId}_{$this->chunkNumber}.xlsx";
            $relativePath = "temp_excel/{$filename}";
            $fullPath = "{$tempDir}/{$filename}";
            $appFullPath = storage_path("app/{$relativePath}");
            
            // Get users for this chunk
            $users = User::select(['id', 'name', 'email', 'created_at'])
                ->where('id', '>=', $this->startId)
                ->limit($this->chunkSize)
                ->orderBy('id')
                ->get();
            
            if ($users->isEmpty()) {
                Log::warning("No users found for chunk", [
                    'chunk_number' => $this->chunkNumber,
                    'batch_id' => $this->batchId,
                    'start_id' => $this->startId,
                    'chunk_size' => $this->chunkSize
                ]);
                return;
            }
            
            Log::info("Processing Excel chunk", [
                'chunk_number' => $this->chunkNumber,
                'batch_id' => $this->batchId,
                'users_count' => $users->count(),
                'first_user_id' => $users->first()->id,
                'last_user_id' => $users->last()->id,
                'temp_dir' => $tempDir,
                'app_temp_dir' => $appTempDir,
                'full_path' => $fullPath,
                'app_full_path' => $appFullPath,
                'is_writable_temp' => is_writable($tempDir) ? 'yes' : 'no',
                'is_writable_app_temp' => is_writable($appTempDir) ? 'yes' : 'no'
            ]);
            
            // Create a temporary file in the system temp directory
            $tempFile = tempnam(sys_get_temp_dir(), 'excel_chunk_');
            if ($tempFile === false) {
                throw new \RuntimeException('Failed to create temporary file');
            }
            register_shutdown_function(function() use ($tempFile) {
                if (file_exists($tempFile)) {
                    @unlink($tempFile);
                }
            });
            
            // Export to the temporary file
            $export = new UserExport($users);
            
            // Use Maatwebsite's Excel facade to store the export
            $stored = Excel::store(
                $export,
                $tempFile,
                null,
                \Maatwebsite\Excel\Excel::XLSX
            );
            
            if (!$stored) {
                throw new \RuntimeException("Failed to store Excel export to temporary file");
            }
            
            // Verify the temporary file was created and has content
            if (!file_exists($tempFile) || filesize($tempFile) === 0) {
                throw new \RuntimeException("Temporary Excel file was not created or is empty");
            }
            
            // Try to copy the file to both possible locations
            $saved = false;
            $savedPaths = [];
            
            // Try saving to the app's storage first (storage/app/temp_excel/...)
            $appDir = dirname($appFullPath);
            if (!file_exists($appDir)) {
                @mkdir($appDir, 0755, true);
            }
            
            if (is_writable($appDir)) {
                if (@copy($tempFile, $appFullPath)) {
                    $saved = true;
                    $savedPaths[] = $appFullPath;
                    Log::info("Successfully saved chunk to app storage", [
                        'chunk_number' => $this->chunkNumber,
                        'saved_path' => $appFullPath,
                        'file_size' => filesize($appFullPath)
                    ]);
                } else {
                    $error = error_get_last();
                    Log::warning("Failed to save to app storage", [
                        'chunk_number' => $this->chunkNumber,
                        'path' => $appFullPath,
                        'error' => $error['message'] ?? 'Unknown error'
                    ]);
                }
            }
            
            // Also try saving to the custom temp directory
            if ($tempDir !== dirname($appFullPath)) {
                if (!file_exists(dirname($fullPath))) {
                    @mkdir(dirname($fullPath), 0755, true);
                }
                
                if (is_writable(dirname($fullPath))) {
                    if (@copy($tempFile, $fullPath)) {
                        $saved = true;
                        $savedPaths[] = $fullPath;
                        Log::info("Successfully saved chunk to custom temp directory", [
                            'chunk_number' => $this->chunkNumber,
                            'saved_path' => $fullPath,
                            'file_size' => filesize($fullPath)
                        ]);
                    } else {
                        $error = error_get_last();
                        Log::warning("Failed to save to custom temp directory", [
                            'chunk_number' => $this->chunkNumber,
                            'path' => $fullPath,
                            'error' => $error['message'] ?? 'Unknown error'
                        ]);
                    }
                }
            }
            
            if (!$saved) {
                throw new \RuntimeException(sprintf(
                    'Failed to save Excel chunk to any location. Tried: %s',
                    implode(', ', [$appFullPath, $fullPath])
                ));
            }
            
            Log::info("Successfully processed Excel chunk", [
                'chunk_number' => $this->chunkNumber,
                'batch_id' => $this->batchId,
                'users_count' => $users->count(),
                'saved_paths' => $savedPaths,
                'file_sizes' => array_map('filesize', $savedPaths)
            ]);
            
        } catch (\Exception $e) {
            Log::error("Error in ProcessExcelChunk: " . $e->getMessage(), [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'chunk_number' => $this->chunkNumber,
                'batch_id' => $this->batchId,
                'start_id' => $this->startId,
                'chunk_size' => $this->chunkSize,
                'temp_dir' => $this->tempDir ?? 'not set'
            ]);
            
            // Re-throw to mark the job as failed
            throw $e;
        } finally {
            // Clean up the temporary file if it still exists
            if (isset($tempFile) && file_exists($tempFile)) {
                @unlink($tempFile);
            }
        }
    }
    
    /**
     * Helper method to get directory listing for debugging
     */
    protected function getDirectoryListing($path)
    {
        if (!is_dir($path)) {
            return [];
        }
        
        $files = [];
        $items = scandir($path);
        
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $filePath = $path . '/' . $item;
            $files[] = [
                'name' => $item,
                'size' => filesize($filePath),
                'modified' => date('Y-m-d H:i:s', filemtime($filePath)),
                'is_dir' => is_dir($filePath) ? 'yes' : 'no',
                'is_writable' => is_writable($filePath) ? 'yes' : 'no'
            ];
        }
        
        return $files;
    }
}
