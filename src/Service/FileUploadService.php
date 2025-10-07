<?php

namespace App\Service;

use App\Entity\UploadedDependencyFile;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Messenger\MessageBusInterface;
use App\Message\ProcessUploadMessage;
use Psr\Log\LoggerInterface;

class FileUploadService
{
    private string $uploadDir;
    private EntityManagerInterface $em;
    private MessageBusInterface $bus;
    private LoggerInterface $logger;

    /**
     * Only allow dependency lock/manifest files supported by Debricked.
     * See: https://debricked.com/docs/scans/dependency-files
     */
    private array $allowedFilenames = [
        'composer.lock',
        'package-lock.json',
        'yarn.lock',
        'requirements.txt',
        'Pipfile.lock',
        'pom.xml',
        'build.gradle',
        'Gemfile.lock',
    ];

    public function __construct(
        string $uploadDir,
        EntityManagerInterface $em,
        MessageBusInterface $bus,
        LoggerInterface $logger
    ) {
        $this->uploadDir = rtrim($uploadDir, '/');
        $this->em = $em;
        $this->bus = $bus;
        $this->logger = $logger;
    }

    /**
     * @param UploadedFile[] $files
     * @param array{email?: string|null, slack_webhook?: string|null, slackWebhook?: string|null} $metadata
     *
     * @return array<array<string, mixed>>
     */
    public function handleUploadedFiles(array $files, array $metadata = []): array
    {
        $uploadedFiles = [];

        $email = $this->normalizeString($metadata['email'] ?? null);
        $slackWebhook = $this->normalizeString($metadata['slack_webhook'] ?? $metadata['slackWebhook'] ?? null);

        foreach ($files as $file) {
            if (!$file instanceof UploadedFile) {
                continue;
            }

            $originalName = $file->getClientOriginalName();

            // Validate against allowed dependency files
            if (!in_array($originalName, $this->allowedFilenames, true)) {
                $this->logger->warning('Rejected unsupported dependency file', [
                    'filename' => $originalName,
                ]);
                continue;
            }

            // Generate unique filename to avoid collisions
            $storedFilename = uniqid() . '-' . $originalName;
            $targetPath = $this->uploadDir . '/' . $storedFilename;

            // Move file into var/uploads
            $file->move($this->uploadDir, $storedFilename);

            // Persist entity
            $uploaded = new UploadedDependencyFile();
            $uploaded->setOriginalFilename($originalName);
            $uploaded->setStoredFilename($storedFilename);
            $uploaded->setFilePath($storedFilename); // for backwards compatibility
            $uploaded->setStatus('pending');
            $uploaded->setVulnerabilityCount(0);
            $uploaded->setCreatedAt(new \DateTimeImmutable());
            $uploaded->setUpdatedAt(new \DateTimeImmutable());
            $uploaded->setScanResultPayload([
                'metadata' => [
                    'email' => $email,
                    'slackWebhook' => $slackWebhook,
                ],
                'ruleState' => [
                    'notifiedRules' => [],
                ],
            ]);

            $this->em->persist($uploaded);
            $this->em->flush();

            // Dispatch async message
            $this->bus->dispatch(new ProcessUploadMessage($uploaded->getId()));

            $uploadedFiles[] = [
                'id' => $uploaded->getId(),
                'filename' => $uploaded->getOriginalFilename(),
                'status' => $uploaded->getStatus(),
                'metadata' => [
                    'email' => $email,
                    'slackWebhook' => $slackWebhook,
                ],
            ];

            $this->logger->info('File saved and message dispatched', [
                'id' => $uploaded->getId(),
                'storedFilename' => $storedFilename,
                'path' => $targetPath,
            ]);
        }

        return $uploadedFiles;
    }

    /**
     * Backwards compatibility for old controller code.
     */
    public function handleUploads(array $files, array $metadata = []): array
    {
        return $this->handleUploadedFiles($files, $metadata);
    }

    private function normalizeString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }
}
