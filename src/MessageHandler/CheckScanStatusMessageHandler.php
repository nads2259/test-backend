<?php

namespace App\MessageHandler;

use App\Entity\ScanResult;
use App\Entity\UploadedDependencyFile;
use App\Message\CheckScanStatusMessage;
use App\Service\DebrickedClient;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final class CheckScanStatusMessageHandler
{
    public function __construct(
        private readonly DebrickedClient $debrickedClient,
        private readonly EntityManagerInterface $em,
        private readonly MessageBusInterface $bus,
        private readonly LoggerInterface $logger
    ) {
    }

    public function __invoke(CheckScanStatusMessage $message): void
    {
        $uploadedFileId = $message->getUploadedFileId();
        $ciUploadId = $message->getCiUploadId();

        /** @var UploadedDependencyFile|null $uploaded */
        $uploaded = $this->em->getRepository(UploadedDependencyFile::class)->find($uploadedFileId);
        if (! $uploaded) {
            $this->logger->warning('UploadedDependencyFile not found in CheckScanStatusMessageHandler', ['id' => $uploadedFileId]);
            return;
        }

        /** @var ScanResult|null $scanResult */
        $scanResult = $this->em->getRepository(ScanResult::class)->findOneBy([
            'uploadedFile' => $uploaded,
            'scanId' => (string) $ciUploadId,
        ]);

        if (! $scanResult) {
            $scanResult = new ScanResult();
            $scanResult->setUploadedFile($uploaded);
            $scanResult->setScanId((string) $ciUploadId);
            $scanResult->setCreatedAt(new \DateTimeImmutable());
        }

        $statusResponse = $this->debrickedClient->checkUploadStatus($ciUploadId);
        $status = $statusResponse['status'] ?? 'processing';

        $scanResult->setStatus($status);
        $scanResult->setSummary(json_encode($statusResponse));
        $scanResult->setUpdatedAt(new \DateTimeImmutable());

        $now = new \DateTimeImmutable();
        $uploaded->setUpdatedAt($now);

        $payload = $uploaded->getScanResultPayload() ?? [];
        $payload['lastStatus'] = $statusResponse;
        $payload['lastStatusAt'] = $now->format(DATE_ATOM);

        $vulnerabilityCount = $this->extractVulnerabilityCount($statusResponse);
        if ($vulnerabilityCount !== null) {
            $uploaded->setVulnerabilityCount($vulnerabilityCount);
        }

        $isDone = $this->isFinished($statusResponse);
        if ($isDone) {
            $scanResult->setStatus('done');
            $uploaded->setStatus('done');
            $payload['finalStatus'] = $statusResponse;
        } else {
            $uploaded->setStatus('processing');
            $this->bus->dispatch(new CheckScanStatusMessage($uploadedFileId, $ciUploadId));
        }

        $uploaded->setScanResultPayload($payload);

        $this->em->persist($scanResult);
        $this->em->persist($uploaded);
        $this->em->flush();
    }

    private function extractVulnerabilityCount(array $statusResponse): ?int
    {
        if (isset($statusResponse['vulnerabilityCount'])) {
            return (int) $statusResponse['vulnerabilityCount'];
        }

        if (isset($statusResponse['results']['vulnerabilityCount'])) {
            return (int) $statusResponse['results']['vulnerabilityCount'];
        }

        if (isset($statusResponse['results']['summary']['vulnerabilitiesFound'])) {
            return (int) $statusResponse['results']['summary']['vulnerabilitiesFound'];
        }

        if (isset($statusResponse['summary']['vulnerabilitiesFound'])) {
            return (int) $statusResponse['summary']['vulnerabilitiesFound'];
        }

        return null;
    }

    private function isFinished(array $statusResponse): bool
    {
        if (isset($statusResponse['percentage']) && (float) $statusResponse['percentage'] >= 100.0) {
            return true;
        }

        if (isset($statusResponse['remainingScans']) && (int) $statusResponse['remainingScans'] === 0) {
            return true;
        }

        if (isset($statusResponse['status']) && in_array($statusResponse['status'], ['done', 'finished', 'completed'], true)) {
            return true;
        }

        return false;
    }
}
