<?php

namespace App\Jobs;

use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessPdfChunk implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $startId;
    public $chunkSize;
    public $chunkNumber;
    public $batchId;

    public function __construct(int $startId, int $chunkSize, int $chunkNumber, string $batchId)
    {
        $this->startId = $startId;
        $this->chunkSize = $chunkSize;
        $this->chunkNumber = $chunkNumber;
        $this->batchId = $batchId;
    }

    public function handle()
    {
        try {
            // Free up memory
            gc_collect_cycles();
            
            // Get users with cursor to save memory
            $users = User::select(['id', 'name', 'email', 'created_at'])
                ->where('id', '>=', $this->startId)
                ->orderBy('id')
                ->limit($this->chunkSize)
                ->cursor();
    
            if ($users->isEmpty()) {
                \Log::info("No users found for chunk", [
                    'startId' => $this->startId,
                    'chunkSize' => $this->chunkSize
                ]);
                return;
            }
    
            // Build HTML in chunks
            $rows = '';
            foreach ($users as $user) {
                $rows .= sprintf('
                <tr>
                    <td>%s</td>
                    <td>%s</td>
                    <td>%s</td>
                    <td>%s</td>
                </tr>',
                    e($user->id),
                    e($user->name),
                    e($user->email),
                    $user->created_at
                );
            }
    
            $html = view('pdfs.report', [
                'rows' => $rows,
                'isFirstChunk' => $this->chunkNumber === 1,
                'isLastChunk' => ($this->chunkNumber * $this->chunkSize) >= User::count()
            ])->render();
    
            // Generate PDF
            $pdf = Pdf::loadHtml($html)
                ->setPaper('a4', 'landscape')
                ->setOption('isPhpEnabled', true)
                ->setOption('isHtml5ParserEnabled', true)
                ->setOption('enable_remote', true)
                ->setOption('defaultFont', 'Arial')
                ->setWarnings(false);
    
            $tempDir = storage_path('app/temp_pdf');
            if (!file_exists($tempDir)) {
                mkdir($tempDir, 0755, true);
            }
            
            $tempFile = "{$tempDir}/chunk_{$this->batchId}_{$this->chunkNumber}.pdf";
            $pdf->save($tempFile);
    
            // Free memory
            unset($pdf, $html, $rows);
            gc_collect_cycles();
    
        } catch (\Exception $e) {
            \Log::error("Error in ProcessPdfChunk: " . $e->getMessage(), [
                'exception' => $e,
                'chunkNumber' => $this->chunkNumber
            ]);
            throw $e;
        }
    }
}