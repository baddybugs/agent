<?php

namespace BaddyBugs\Agent\Commands;

use Illuminate\Console\Command;
use BaddyBugs\Agent\Sender\SenderInterface;

class SendCommand extends Command
{
    protected $signature = 'baddybugs:send 
                            {--limit=1000} 
                            {--batch=1000 : Number of events per batch}
                            {--force} 
                            {--max-retries=3}';
    protected $description = 'Send buffered events from local storage to BaddyBugs';

    protected int $maxRetries;
    protected int $batchSize;

    public function handle(SenderInterface $sender): int
    {
        $this->maxRetries = (int) $this->option('max-retries');
        $this->batchSize = (int) $this->option('batch');
        $path = config('baddybugs.storage_path');

        // 1. First, process any orphaned processing files (from previous failed runs)
        $this->processOrphanedFiles($path, $sender);

        // 2. Process the main events file
        $file = $path . '/events.jsonl';

        if (!file_exists($file) || filesize($file) === 0) {
            $this->info("No new buffered events found.");
            return 0;
        }

        // Rename file to lock it for processing
        $processingFile = $path . '/events_processing_' . time() . '.jsonl';
        if (!rename($file, $processingFile)) {
            $this->error("Failed to acquire lock on buffer file.");
            return 1;
        }

        $this->info("Processing buffer...");
        
        return $this->processFile($processingFile, $sender);
    }

    /**
     * Process backlog files: orphaned processing files and rotated buffer files
     */
    protected function processOrphanedFiles(string $path, SenderInterface $sender): void
    {
        // Find all backlog files: orphaned processing files AND rotated buffer files
        $processingFiles = glob($path . '/events_processing_*.jsonl') ?: [];
        $rotatedFiles = glob($path . '/events_[0-9]*.jsonl') ?: [];
        
        $backlogFiles = array_merge($processingFiles, $rotatedFiles);
        
        if (empty($backlogFiles)) {
            return;
        }

        // Sort by modification time (oldest first)
        usort($backlogFiles, fn($a, $b) => filemtime($a) - filemtime($b));

        $this->info("Found " . count($backlogFiles) . " backlog files to process...");

        foreach ($backlogFiles as $file) {
            // Skip files modified in the last 60 seconds (might be in active writing)
            if (filemtime($file) > time() - 60) {
                $this->info("Skipping " . basename($file) . " (recently modified)");
                continue;
            }

            $this->info("Processing: " . basename($file));
            $this->processFile($file, $sender);
        }
    }

    /**
     * Process a single buffer file with retry logic
     */
    protected function processFile(string $processingFile, SenderInterface $sender): int
    {
        $handle = fopen($processingFile, 'r');
        
        if (!$handle) {
            $this->error("Cannot open file: {$processingFile}");
            return 1;
        }

        $batch = [];
        $count = 0;
        $totalSent = 0;
        $failedEvents = [];

        while (($line = fgets($handle)) !== false) {
            $data = json_decode($line, true);
            if ($data) {
                $batch[] = $data;
                $count++;
            }

            if ($count >= $this->batchSize) {
                $result = $this->sendBatchWithRetry($sender, $batch);
                
                if ($result['success']) {
                    $totalSent += count($batch);
                    $this->info("Sent batch of {$count} events.");
                } else {
                    // Store failed events for potential retry or logging
                    $failedEvents = array_merge($failedEvents, $batch);
                    $this->warn("Failed to send batch after {$this->maxRetries} retries. Events lost: {$count}");
                }
                
                $batch = [];
                $count = 0;
            }
        }
        
        // Send remaining events
        if (!empty($batch)) {
            $result = $this->sendBatchWithRetry($sender, $batch);
            
            if ($result['success']) {
                $totalSent += count($batch);
                $this->info("Sent remaining " . count($batch) . " events.");
            } else {
                $failedEvents = array_merge($failedEvents, $batch);
                $this->warn("Failed to send remaining batch.");
            }
        }
        
        fclose($handle);
        
        // Always delete the processing file after we're done
        // (whether successful or not - we've tried our best)
        if (file_exists($processingFile)) {
            unlink($processingFile);
            $this->info("Cleaned up: " . basename($processingFile));
        }
        
        // Report results
        if (!empty($failedEvents)) {
            $this->warn("Total events lost due to send failures: " . count($failedEvents));
        }
        
        $this->info("Done. Total sent: {$totalSent}");
        
        return 0;
    }

    /**
     * Send a batch with retry logic
     */
    protected function sendBatchWithRetry(SenderInterface $sender, array $batch): array
    {
        $attempts = 0;
        $lastError = null;

        while ($attempts < $this->maxRetries) {
            $attempts++;
            
            try {
                if ($sender->send($batch)) {
                    return ['success' => true, 'attempts' => $attempts];
                }
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
            }

            // Exponential backoff: 1s, 2s, 4s...
            if ($attempts < $this->maxRetries) {
                $sleepTime = pow(2, $attempts - 1);
                $this->warn("Retry {$attempts}/{$this->maxRetries} failed. Waiting {$sleepTime}s...");
                sleep($sleepTime);
            }
        }

        return [
            'success' => false, 
            'attempts' => $attempts,
            'error' => $lastError
        ];
    }
}
