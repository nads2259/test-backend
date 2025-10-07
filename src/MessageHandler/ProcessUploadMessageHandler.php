<?php

namespace App\MessageHandler;

use App\Message\ProcessUploadMessage;
use App\Message\CheckScanStatusMessage;
use App\Service\DebrickedClient;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use App\Entity\UploadedDependencyFile;
use App\Entity\ScanResult;
use Psr\Log\LoggerInterface;

#[AsMessageHandler]
final class ProcessUploadMessageHandler
{
    private DebrickedClient $debrickedClient;
    private EntityManagerInterface $em;
    private MessageBusInterface $bus;
    private LoggerInterface $logger;
    private string $uploadDir;

    public function __construct(
        DebrickedClient $debrickedClient,
        EntityManagerInterface $em,
        MessageBusInterface $bus,
        LoggerInterface $logger,
        string $uploadDir
    ) {
        $this->debrickedClient = $debrickedClient;
        $this->em = $em;
        $this->bus = $bus;
        $this->logger = $logger;
        $this->uploadDir = rtrim($uploadDir, '/');
    }

    public function __invoke(ProcessUploadMessage $message): void
    {
        $uploadedFileId = $message->getUploadedFileId();

        $uploaded = $this->em->getRepository(UploadedDependencyFile::class)->find($uploadedFileId);
        if (!$uploaded) {
            $this->logger->warning('UploadedDependencyFile not found', ['id' => $uploadedFileId]);
            return;
        }

        $filePath = $uploaded->getFullPath($this->uploadDir);

        if (!file_exists($filePath)) {
            $this->logger->error('File not found for Debricked upload', [
                'resolvedPath' => $filePath,
                'uploadDir' => $this->uploadDir,
                'storedFilename' => $uploaded->getStoredFilename(),
                'id' => $uploadedFileId,
            ]);
            $uploaded->setStatus('error');
            $uploaded->setErrorMessage('File not found at ' . $filePath);
            $this->em->flush();
            return;
        }

        $uploaded->setStatus('uploading');
        $uploaded->setUpdatedAt(new \DateTimeImmutable());

        $this->em->persist($uploaded);
        $this->em->flush();

        $this->logger->info('File ready for Debricked upload', [
            'resolvedPath' => $filePath,
            'size' => filesize($filePath),
        ]);

        // Upload file to Debricked
        $response = $this->debrickedClient->uploadDependencyFile($filePath, $uploaded->getOriginalFilename());

        $ciUploadId = $response['ciUploadId'] ?? null;
        $uploadProgramsFileId = $response['uploadProgramsFileId'] ?? null;

        if ($ciUploadId === null) {
            $this->logger->error('Debricked upload missing ciUploadId', [
                'response' => $response,
                'uploadedId' => $uploadedFileId,
            ]);
            $uploaded->setStatus('error');
            $uploaded->setErrorMessage(json_encode($response));
            $this->em->flush();
            return;
        }

        // Persist results on UploadedDependencyFile
        $payload = $uploaded->getScanResultPayload() ?? [];
        $payload['upload'] = array_filter([
            'ciUploadId' => (int) $ciUploadId,
            'uploadProgramsFileId' => $uploadProgramsFileId,
            'response' => $response,
        ]);

        $uploaded->setDebrickedUploadId((string)$ciUploadId);
        $uploaded->setScanResultPayload($payload);
        $uploaded->setStatus('processing');
        $uploaded->setUpdatedAt(new \DateTimeImmutable());

        $this->em->persist($uploaded);

        // Create ScanResult entity
        $scanResult = new ScanResult();
        $scanResult->setUploadedFile($uploaded);
        $scanResult->setScanId((string)$ciUploadId);
        $scanResult->setStatus('processing');
        $scanResult->setSummary(json_encode($response));
        $scanResult->setCreatedAt(new \DateTimeImmutable());
        $scanResult->setUpdatedAt(new \DateTimeImmutable());

        $this->em->persist($scanResult);
        $this->em->flush();

        // Dispatch message to poll scan status
        $this->bus->dispatch(new CheckScanStatusMessage($uploadedFileId, (int)$ciUploadId));

        $this->logger->info('File uploaded to Debricked successfully', [
            'id' => $uploadedFileId,
            'ciUploadId' => $ciUploadId,
        ]);
    }
}
