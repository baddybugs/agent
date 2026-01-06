<?php

namespace BaddyBugs\Agent\Commands;

use Illuminate\Console\Command;
use BaddyBugs\Agent\Sender\SenderInterface;

class SendCommand extends Command
{
    protected $signature = 'baddybugs:send
                            {--limit=1000}
                            {--batch=10 : Number of events per batch}
                            {--force}
                            {--max-retries=3}';
    protected $description = "Send buffered events from local storage to BaddyBugs";

    protected int $maxRetries;
    protected int $batchSize;

    public function handle(SenderInterface $sender): int
    {
        $this->maxRetries = (int) $this->option("max-retries");
        $this->batchSize = (int) $this->option("batch");
        $path = config("baddybugs.storage_path");

        // 1. First, process any orphaned processing files (from previous failed runs)
        $this->processOrphanedFiles($path, $sender);

        // 2. Process the main events file
        $file = $path . "/events.jsonl";

        if (!file_exists($file) || filesize($file) === 0) {
            $this->verbose("No new buffered events found.");
            return 0;
        }

        // Rename file to lock it for processing
        $processingFile = $path . "/events_processing_" . time() . ".jsonl";
        if (!rename($file, $processingFile)) {
            $this->error("Failed to acquire lock on buffer file.");
            return 1;
        }

        $this->verbose("Processing buffer...");

        return $this->processFile($processingFile, $sender);
    }

    /**
     * Output only in verbose mode (-v)
     */
    protected function verbose(string $message): void
    {
        if ($this->getOutput()->isVerbose()) {
            $this->info($message);
        }
    }

    /**
     * Process backlog files: orphaned processing files and rotated buffer files
     */
    protected function processOrphanedFiles(
        string $path,
        SenderInterface $sender,
    ): void {
        // Find all backlog files: orphaned processing files AND rotated buffer files
        $processingFiles = glob($path . "/events_processing_*.jsonl") ?: [];
        $rotatedFiles = glob($path . "/events_[0-9]*.jsonl") ?: [];

        $backlogFiles = array_merge($processingFiles, $rotatedFiles);

        if (empty($backlogFiles)) {
            return;
        }

        // Sort by modification time (oldest first)
        usort($backlogFiles, fn($a, $b) => filemtime($a) - filemtime($b));

        $this->verbose(
            "Found " . count($backlogFiles) . " backlog files to process...",
        );

        foreach ($backlogFiles as $file) {
            // Skip files modified in the last 60 seconds (might be in active writing)
            if (filemtime($file) > time() - 60) {
                $this->verbose(
                    "Skipping " . basename($file) . " (recently modified)",
                );
                continue;
            }

            $this->verbose("Processing: " . basename($file));
            $this->processFile($file, $sender);
        }
    }

    /**
     * Process a single buffer file with retry logic
     */
    protected function processFile(
        string $processingFile,
        SenderInterface $sender,
    ): int {
        $handle = fopen($processingFile, "r");

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

                if ($result["success"]) {
                    $totalSent += count($batch);
                    $this->verbose("Sent batch of {$count} events.");
                } else {
                    // Store failed events for potential retry or logging
                    $failedEvents = array_merge($failedEvents, $batch);
                    $this->warn(
                        "Failed to send batch after {$this->maxRetries} retries. Queuing for retry: {$count}",
                    );
                }

                $batch = [];
                $count = 0;
            }
        }

        // Send remaining events
        if (!empty($batch)) {
            $result = $this->sendBatchWithRetry($sender, $batch);

            if ($result["success"]) {
                $totalSent += count($batch);
                $this->verbose("Sent remaining " . count($batch) . " events.");
            } else {
                $failedEvents = array_merge($failedEvents, $batch);
                $this->warn("Failed to send remaining batch.");
            }
        }

        fclose($handle);

        // Always delete the processing file
        if (file_exists($processingFile)) {
            unlink($processingFile);
            $this->verbose("Cleaned up: " . basename($processingFile));
        }

        // If there are failed events, save them back for retry
        if (!empty($failedEvents)) {
            $this->saveFailedEvents($failedEvents, dirname($processingFile));
            $this->warn("Saved " . count($failedEvents) . " events for retry.");
        }

        $this->verbose("Done. Total sent: {$totalSent}");

        return 0;
    }

    /**
     * Save failed events back to the buffer for retry on next run
     */
    protected function saveFailedEvents(array $events, string $path): void
    {
        $filename = $path . "/events.jsonl";
        $isNewFile = !file_exists($filename);

        foreach ($events as $event) {
            $line = json_encode(
                $event,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            );
            if ($line !== false) {
                if (@file_put_contents(
                    $filename,
                    $line . PHP_EOL,
                    FILE_APPEND | LOCK_EX,
                ) !== false) {
                    if ($isNewFile) {
                        @chmod($filename, 0666);
                        $isNewFile = false; // Only chmod once
                    }
                }
            }
        }
    }

    /**
     * Send a batch with retry logic
     */
    protected function sendBatchWithRetry(
        SenderInterface $sender,
        array $batch,
    ): array {
        $attempts = 0;
        $lastError = null;

        while ($attempts < $this->maxRetries) {
            $attempts++;

            try {
                if ($sender->send($batch)) {
                    return ["success" => true, "attempts" => $attempts];
                }
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
            }

            // Exponential backoff: 1s, 2s, 4s...
            if ($attempts < $this->maxRetries) {
                $sleepTime = pow(2, $attempts - 1);
                $this->verbose(
                    "Retry {$attempts}/{$this->maxRetries} failed. Waiting {$sleepTime}s...",
                );
                sleep($sleepTime);
            }
        }

        return [
            "success" => false,
            "attempts" => $attempts,
            "error" => $lastError,
        ];
    }
}
