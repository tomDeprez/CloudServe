<?php

namespace App\Command;

use App\Entity\File;
use App\Entity\UploadQueue;
use App\Repository\UploadQueueRepository;
use App\Service\AudioCompressionService;
use App\Service\ImageCompressionService;
use App\Service\RawFileUploadService;
use App\Service\ThumbnailService;
use App\Service\VideoCompressionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:process-upload-queue',
    description: 'Process pending file uploads from the queue',
)]
class ProcessUploadQueueCommand extends Command
{
    public function __construct(
        private UploadQueueRepository $uploadQueueRepository,
        private EntityManagerInterface $entityManager,
        private RawFileUploadService $fileUploadService,
        private ImageCompressionService $imageCompression,
        private VideoCompressionService $videoCompression,
        private AudioCompressionService $audioCompression,
        private ThumbnailService $thumbnailService,
        private string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('daemon', 'd', InputOption::VALUE_NONE, 'Run as daemon (continuous processing)')
            ->addOption('sleep', 's', InputOption::VALUE_OPTIONAL, 'Sleep duration between checks (seconds)', 2)
            ->addOption('batch', 'b', InputOption::VALUE_OPTIONAL, 'Number of files to process per batch', 5);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $isDaemon = $input->getOption('daemon');
        $sleepDuration = (int) $input->getOption('sleep');
        $batchSize = (int) $input->getOption('batch');

        $io->title('Upload Queue Processor');

        if ($isDaemon) {
            $io->note(sprintf('Running as daemon (sleep: %ds, batch: %d)', $sleepDuration, $batchSize));
            $io->info('Press Ctrl+C to stop');
        }

        do {
            $processedCount = $this->processBatch($batchSize, $io);

            if ($isDaemon) {
                if ($processedCount === 0) {
                    sleep($sleepDuration);
                }
                // Clear entity manager to prevent memory leaks
                $this->entityManager->clear();
            }
        } while ($isDaemon);

        $io->success('Queue processing completed');
        return Command::SUCCESS;
    }

    private function processBatch(int $batchSize, SymfonyStyle $io): int
    {
        $pendingUploads = $this->uploadQueueRepository->findAllPending($batchSize);

        if (empty($pendingUploads)) {
            return 0;
        }

        $io->text(sprintf('Found %d pending uploads', count($pendingUploads)));

        foreach ($pendingUploads as $upload) {
            try {
                $this->processUpload($upload, $io);
            } catch (\Exception $e) {
                $io->error(sprintf(
                    'Failed to process upload #%d (%s): %s',
                    $upload->getId(),
                    $upload->getFilename(),
                    $e->getMessage()
                ));

                $upload->setStatus('failed');
                $upload->setErrorMessage($e->getMessage());
                $upload->setProcessedAt(new \DateTimeImmutable());
                $this->entityManager->flush();
            }
        }

        return count($pendingUploads);
    }

    private function processUpload(UploadQueue $upload, SymfonyStyle $io): void
    {
        $io->text(sprintf(
            'Processing: %s (%s)',
            $upload->getFilename(),
            $this->formatBytes($upload->getSize())
        ));

        // Mark as processing
        $upload->setStatus('processing');
        $upload->setProgress(10);
        $this->entityManager->flush();

        // Check if temp file still exists
        if (!file_exists($upload->getTempPath())) {
            throw new \RuntimeException('Temporary file not found');
        }

        // Move file to permanent storage
        $upload->setProgress(20);
        $this->entityManager->flush();

        $storedData = [
            'tmp_name' => $upload->getTempPath(),
            'name' => $upload->getFilename(),
            'type' => $upload->getMimeType(),
            'size' => $upload->getSize(),
            'error' => UPLOAD_ERR_OK,
        ];

        $result = $this->fileUploadService->store($storedData);

        $upload->setProgress(40);
        $this->entityManager->flush();

        // Create File entity
        $file = new File();
        $file->setFilename($upload->getFilename());
        $file->setStoredName($result['storedName']);
        $file->setMimeType($result['mimeType']);
        $file->setSize($result['size']);
        $file->setHash($result['hash']);
        $file->setUploadedAt(new \DateTimeImmutable());
        $file->setUser($upload->getUser());
        $file->setParent($upload->getParentFolder());
        $file->setProcessing(true);

        $this->entityManager->persist($file);
        $this->entityManager->flush();

        $upload->setProgress(50);
        $upload->setResultFile($file);
        $this->entityManager->flush();

        // Compress based on type
        $uploadPath = $this->projectDir . '/public/uploads/' . $result['storedName'];
        $mimeType = $result['mimeType'];

        // Image compression
        if (str_starts_with($mimeType, 'image/') && $mimeType !== 'image/gif') {
            $io->text('  → Compressing image...');
            $upload->setProgress(60);
            $this->entityManager->flush();

            $this->imageCompression->compress($uploadPath);

            $upload->setProgress(80);
            $this->entityManager->flush();

            // Generate thumbnail
            $io->text('  → Generating thumbnail...');
            $thumbnail = $this->thumbnailService->generateThumbnail($result['storedName']);
            if ($thumbnail) {
                $file->setThumbnail($thumbnail);
            }
        }

        // Video compression
        if (str_starts_with($mimeType, 'video/')) {
            $io->text('  → Compressing video...');
            $upload->setProgress(60);
            $this->entityManager->flush();

            $this->videoCompression->compress($uploadPath);

            $upload->setProgress(90);
            $this->entityManager->flush();
        }

        // Audio compression
        if (str_starts_with($mimeType, 'audio/')) {
            $io->text('  → Compressing audio...');
            $upload->setProgress(60);
            $this->entityManager->flush();

            $this->audioCompression->compress($uploadPath);

            $upload->setProgress(90);
            $this->entityManager->flush();
        }

        // Update file size after compression
        if (file_exists($uploadPath)) {
            $newSize = filesize($uploadPath);
            $file->setSize((string) $newSize);
        }

        // Mark as completed
        $file->setProcessing(false);
        $upload->setStatus('completed');
        $upload->setProgress(100);
        $upload->setProcessedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        // Clean up temp file
        if (file_exists($upload->getTempPath())) {
            @unlink($upload->getTempPath());
        }

        $io->success(sprintf('  ✓ Completed: %s', $upload->getFilename()));
    }

    private function formatBytes(string $bytes): string
    {
        $bytes = (int) $bytes;
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }
}
