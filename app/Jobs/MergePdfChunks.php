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

    public function __construct(string $batchId, int $totalChunks)
    {
        $this->batchId = $batchId;
        $this->totalChunks = $totalChunks;
    }

    public function handle()
    {
        $merger = new Fpdi('L', 'mm', 'A4');
        $outputDir = storage_path('app/reports');
        $outputPath = $outputDir . '/report_' . now()->format('Ymd_His') . '.pdf';
        $tempDir = storage_path('app/temp_pdf');
        
        if (!file_exists($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $merger->setPrintHeader(false);
        $merger->setPrintFooter(false);
        $merger->SetAutoPageBreak(true, 10);
        $merger->SetMargins(5, 5, 5);
    
        $processedChunks = 0;
        
        for ($i = 1; $i <= $this->totalChunks; $i++) {
            $chunkFile = "{$tempDir}/chunk_{$this->batchId}_{$i}.pdf";
            
            if (!file_exists($chunkFile)) {
                Log::warning("Chunk file not found: {$chunkFile}");
                continue;
            }

            try {
                $pageCount = $merger->setSourceFile($chunkFile);
                
                for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                    $template = $merger->importPage($pageNo);
                    $size = $merger->getTemplateSize($template);
                    
                    $merger->AddPage(
                        $size['width'] > $size['height'] ? 'L' : 'P',
                        [$size['width'], $size['height']]
                    );
                    $merger->useTemplate($template);
                }
                
                $processedChunks++;
                @unlink($chunkFile);
                
            } catch (\Exception $e) {
                Log::error("Error processing chunk {$i}: " . $e->getMessage());
                continue;
            }
        }
        
        if ($processedChunks > 0) {
            $merger->Output($outputPath, 'F');
            Log::info("Successfully merged {$processedChunks} chunks into {$outputPath}");
        } else {
            Log::error("No chunks were processed successfully");
        }
        
        // Clean up any remaining chunk files
        $this->cleanupChunkFiles();
    }
    
    protected function cleanupChunkFiles(): void
    {
        $tempDir = storage_path('app/temp_pdf');
        $pattern = "{$tempDir}/chunk_{$this->batchId}_*.pdf";
        
        foreach (glob($pattern) as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }
    }
}

