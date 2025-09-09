<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use setasign\Fpdi\Tcpdf\Fpdi;
use Illuminate\Support\Facades\Log;

class MergePdfChunks implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $batchId;
    public $totalChunks;
    protected $tempMergedFile;

    public function __construct(string $batchId, int $totalChunks)
    {
        $this->batchId = $batchId;
        $this->totalChunks = $totalChunks;
        $this->tempMergedFile = storage_path('app/temp_pdf/merged_' . $batchId . '.pdf');
    }

    public function handle()
    {
        try {
            $outputDir = storage_path('app/reports');
            $outputPath = $outputDir . '/report_' . now()->format('Ymd_His') . '.pdf';
            $tempDir = storage_path('app/temp_pdf');
            
            if (!file_exists($outputDir)) {
                mkdir($outputDir, 0755, true);
            }

            // Initialize the final PDF
            $pdf = new Fpdi();
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            
            // Process one chunk at a time
            for ($i = 1; $i <= $this->totalChunks; $i++) {
                $chunkFile = "{$tempDir}/chunk_{$this->batchId}_{$i}.pdf";
                
                if (!file_exists($chunkFile)) {
                    Log::warning("Chunk file not found: {$chunkFile}");
                    continue;
                }

                try {
                    // Get the number of pages in the current chunk
                    $pageCount = $pdf->setSourceFile($chunkFile);
                    
                    // Process each page in the chunk
                    for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                        // Import the page
                        $template = $pdf->importPage($pageNo);
                        $size = $pdf->getTemplateSize($template);
                        
                        // Add a new page with the same orientation and dimensions
                        $pdf->AddPage(
                            $size['width'] > $size['height'] ? 'L' : 'P',
                            [$size['width'], $size['height']]
                        );
                        
                        // Use the imported page
                        $pdf->useTemplate($template);
                    }
                    
                    // Remove the processed chunk file
                    @unlink($chunkFile);
                    
                    // Save progress after each chunk to avoid memory buildup
                    if ($i % 5 === 0 || $i === $this->totalChunks) {
                        $tempFile = "{$tempDir}/temp_merge_{$this->batchId}.pdf";
                        $pdf->Output($tempFile, 'F');
                        $pdf = new Fpdi();
                        $pdf->setPrintHeader(false);
                        $pdf->setPrintFooter(false);
                        
                        // Import all pages from the temporary file
                        $pageCount = $pdf->setSourceFile($tempFile);
                        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                            $template = $pdf->importPage($pageNo);
                            $size = $pdf->getTemplateSize($template);
                            $pdf->AddPage(
                                $size['width'] > $size['height'] ? 'L' : 'P',
                                [$size['width'], $size['height']]
                            );
                            $pdf->useTemplate($template);
                        }
                        
                        @unlink($tempFile);
                    }
                    
                    // Force garbage collection
                    gc_collect_cycles();
                    
                    Log::info("Processed chunk {$i}/{$this->totalChunks}");
                    
                } catch (\Exception $e) {
                    Log::error("Error processing chunk {$i}: " . $e->getMessage());
                    continue;
                }
            }
            
            // Save the final merged PDF
            if ($pdf->getNumPages() > 0) {
                $pdf->Output($outputPath, 'F');
                Log::info("Successfully merged all chunks into {$outputPath}");
            } else {
                Log::error("No pages were added to the final PDF");
            }
            
            // Free memory
            $pdf = null;
            
        } catch (\Exception $e) {
            Log::error("Error in MergePdfChunks: " . $e->getMessage());
            throw $e;
        } finally {
            // Clean up any remaining files
            $this->cleanupChunkFiles();
        }
    }
    
    protected function cleanupChunkFiles(): void
    {
        $tempDir = storage_path('app/temp_pdf');
        $patterns = [
            "{$tempDir}/chunk_{$this->batchId}_*.pdf",
            "{$tempDir}/batch_{$this->batchId}_*.pdf",
            "{$tempDir}/merged_{$this->batchId}.pdf",
            "{$tempDir}/temp_merge_{$this->batchId}.pdf"
        ];
        
        foreach ($patterns as $pattern) {
            foreach (glob($pattern) as $file) {
                if (file_exists($file)) {
                    @unlink($file);
                }
            }
        }
    }
}