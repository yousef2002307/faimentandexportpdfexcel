<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Maatwebsite\Excel\Excel;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class MergeExcelChunks implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $batchId;
    public $totalChunks;
    public $tempDir;
    public $exportPath;

    public function __construct(
        string $batchId, 
        int $totalChunks, 
        string $tempDir, 
        string $exportPath
    ) {
        $this->batchId = $batchId;
        $this->totalChunks = $totalChunks;
        $this->tempDir = $tempDir;
        $this->exportPath = $exportPath;
    }

    public function handle()
    {
        try {
            $outputFile = rtrim($this->exportPath, '/\\') . '/users_export_' . now()->format('Ymd_His') . '.xlsx';
            $masterSpreadsheet = new Spreadsheet();
            $masterSheet = $masterSpreadsheet->getActiveSheet();
            $currentRow = 1;
            $processedChunks = 0;
            
            // Ensure the export directory exists
            if (!file_exists($this->exportPath)) {
                if (!@mkdir($this->exportPath, 0755, true) && !is_dir($this->exportPath)) {
                    $error = error_get_last();
                    throw new \RuntimeException(sprintf(
                        'Failed to create export directory "%s". Error: %s',
                        $this->exportPath,
                        $error['message'] ?? 'Unknown error'
                    ));
                }
            }

            Log::info("Starting to merge Excel chunks", [
                'batch_id' => $this->batchId,
                'total_chunks' => $this->totalChunks,
                'temp_dir' => $this->tempDir,
                'output_file' => $outputFile,
                'export_path' => $this->exportPath,
                'export_dir_exists' => file_exists($this->exportPath) ? 'yes' : 'no',
                'export_dir_writable' => is_writable($this->exportPath) ? 'yes' : 'no',
                'temp_dir_readable' => is_readable($this->tempDir) ? 'yes' : 'no'
            ]);

            for ($i = 1; $i <= $this->totalChunks; $i++) {
                $chunkFile = "{$this->tempDir}/chunk_{$this->batchId}_{$i}.xlsx";
                $storageChunkPath = storage_path("app/temp_excel/chunk_{$this->batchId}_{$i}.xlsx");
                
                // Try both paths for backward compatibility
                if (!file_exists($chunkFile) && file_exists($storageChunkPath)) {
                    $chunkFile = $storageChunkPath;
                }
                
                if (!file_exists($chunkFile)) {
                    Log::warning("Chunk file not found", [
                        'chunk_number' => $i,
                        'chunk_file' => $chunkFile,
                        'storage_path' => $storageChunkPath,
                        'file_exists' => file_exists($chunkFile) ? 'yes' : 'no',
                        'storage_file_exists' => file_exists($storageChunkPath) ? 'yes' : 'no'
                    ]);
                    continue;
                }

                try {
                    // Verify the chunk file is readable and not empty
                    if (!is_readable($chunkFile)) {
                        throw new \RuntimeException("Chunk file is not readable: {$chunkFile}");
                    }
                    
                    $fileSize = filesize($chunkFile);
                    if ($fileSize === 0) {
                        throw new \RuntimeException("Chunk file is empty: {$chunkFile}");
                    }
                    
                    Log::debug("Loading chunk file", [
                        'chunk_number' => $i,
                        'file_size' => $fileSize,
                        'file_path' => $chunkFile
                    ]);
                    
                    $spreadsheet = IOFactory::load($chunkFile);
                    $worksheet = $spreadsheet->getActiveSheet();
                    $rows = $worksheet->toArray();

                    // Skip header for all chunks after the first one
                    $startRow = ($i === 1) ? 0 : 1;
                    $rowsProcessed = 0;
                    
                    foreach (array_slice($rows, $startRow) as $row) {
                        $masterSheet->fromArray($row, null, "A{$currentRow}");
                        $currentRow++;
                        $rowsProcessed++;
                    }

                    $processedChunks++;
                    
                    // Try to delete the chunk file after processing
                    if (!@unlink($chunkFile)) {
                        Log::warning("Failed to delete chunk file", [
                            'chunk_number' => $i,
                            'file_path' => $chunkFile
                        ]);
                    }
                    
                    Log::debug("Processed chunk", [
                        'chunk_number' => $i,
                        'rows_processed' => $rowsProcessed,
                        'current_row' => $currentRow - 1
                    ]);
                    
                } catch (\Exception $e) {
                    Log::error("Error processing chunk {$i}", [
                        'error' => $e->getMessage(),
                        'chunk_file' => $chunkFile,
                        'trace' => $e->getTraceAsString()
                    ]);
                    continue;
                }
            }

            if ($processedChunks > 0) {
                // Ensure the directory exists
                $outputDir = dirname($outputFile);
                if (!file_exists($outputDir)) {
                    if (!@mkdir($outputDir, 0755, true) && !is_dir($outputDir)) {
                        $error = error_get_last();
                        throw new \RuntimeException(sprintf(
                            'Failed to create output directory "%s". Error: %s',
                            $outputDir,
                            $error['message'] ?? 'Unknown error'
                        ));
                    }
                }
                
                // Verify the directory is writable
                if (!is_writable($outputDir)) {
                    throw new \RuntimeException("Output directory is not writable: {$outputDir}");
                }
                
                Log::info("Saving merged Excel file", [
                    'output_file' => $outputFile,
                    'total_chunks_processed' => $processedChunks,
                    'total_rows' => $currentRow - 1
                ]);
                
                try {
                    $writer = new Xlsx($masterSpreadsheet);
                    $writer->save($outputFile);
                    
                    if (!file_exists($outputFile)) {
                        throw new \RuntimeException("Failed to save merged file: {$outputFile}");
                    }
                    
                    $finalSize = filesize($outputFile);
                    if ($finalSize === 0) {
                        @unlink($outputFile);
                        throw new \RuntimeException("Merged file was created but is empty: {$outputFile}");
                    }
                    
                    Log::info("Successfully merged Excel chunks", [
                        'batch_id' => $this->batchId,
                        'output_file' => $outputFile,
                        'file_size' => $finalSize,
                        'total_chunks_processed' => $processedChunks,
                        'total_rows' => $currentRow - 1
                    ]);
                    
                } catch (\Exception $e) {
                    Log::error("Error saving merged Excel file", [
                        'error' => $e->getMessage(),
                        'output_file' => $outputFile,
                        'trace' => $e->getTraceAsString()
                    ]);
                    throw $e;
                }
            } else {
                Log::error("No Excel chunks were processed successfully", [
                    'batch_id' => $this->batchId,
                    'total_chunks_attempted' => $this->totalChunks,
                    'temp_dir' => $this->tempDir,
                    'temp_dir_listing' => $this->getDirectoryListing($this->tempDir)
                ]);
                
                throw new \RuntimeException("No Excel chunks were processed successfully. Check logs for details.");
            }
            
            // Clean up any remaining chunk files
            $this->cleanupChunkFiles();
            
        } catch (\Exception $e) {
            Log::error("Error in MergeExcelChunks: " . $e->getMessage(), [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'batch_id' => $this->batchId ?? 'unknown'
            ]);
            throw $e;
        } finally {
            // Always clean up the master spreadsheet to free memory
            if (isset($masterSpreadsheet)) {
                $masterSpreadsheet->disconnectWorksheets();
                unset($masterSpreadsheet);
            }
        }
    }
    
    /**
     * Get directory listing for debugging
     */
    protected function getDirectoryListing($path)
    {
        if (!is_dir($path)) {
            return [];
        }
        
        $result = [];
        $items = @scandir($path);
        
        if ($items === false) {
            return ['error' => 'Failed to scan directory'];
        }
        
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            
            $itemPath = $path . DIRECTORY_SEPARATOR . $item;
            $result[] = [
                'name' => $item,
                'size' => is_file($itemPath) ? filesize($itemPath) : null,
                'is_dir' => is_dir($itemPath) ? 'yes' : 'no',
                'is_readable' => is_readable($itemPath) ? 'yes' : 'no',
                'is_writable' => is_writable($itemPath) ? 'yes' : 'no',
                'permissions' => substr(sprintf('%o', fileperms($itemPath)), -4)
            ];
        }
        
        return $result;
    }
    
    protected function cleanupChunkFiles(): void
    {
        if (empty($this->batchId)) return;
        
        $pattern = "{$this->tempDir}/chunk_{$this->batchId}_*.xlsx";
        
        foreach (glob($pattern) as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }
    }
}
