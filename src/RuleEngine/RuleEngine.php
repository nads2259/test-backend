<?php

namespace App\RuleEngine;

use App\Entity\ScanResult;
use App\Entity\UploadedDependencyFile;
use App\Service\NotificationService;
use DateInterval;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;

final class RuleEngine
{
    public const RULE_HIGH_VULNERABILITY = 'high_vulnerability';
    public const RULE_UPLOAD_FAILED = 'upload_failed';
    public const RULE_UPLOAD_STUCK = 'upload_stuck';

    private DateInterval $stuckInterval;

    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly LoggerInterface $logger,
        private readonly int $highVulnerabilityThreshold,
        int $stuckMinutes
    ) {
        $this->stuckInterval = new DateInterval('PT' . max(1, $stuckMinutes) . 'M');
    }

    public function evaluate(UploadedDependencyFile $file, ?ScanResult $latestScan = null): RuleEvaluationResult
    {
        $payload = $file->getScanResultPayload() ?? [];
        $ruleState = $payload['ruleState'] ?? ['notifiedRules' => []];
        $metadata = $payload['metadata'] ?? [];

        $triggered = [];
        $now = new DateTimeImmutable();

        if ($this->shouldNotifyUploadFailed($file, $ruleState)) {
            $actions = $this->notify(
                $metadata,
                sprintf('Upload %s failed', $file->getOriginalFilename()),
                $file->getErrorMessage() ?? 'Unknown error occurred'
            );

            if ($actions) {
                $ruleState['notifiedRules'][self::RULE_UPLOAD_FAILED] = [
                    'notifiedAt' => $now->format(DATE_ATOM),
                    'actions' => $actions,
                ];
                $triggered[self::RULE_UPLOAD_FAILED] = ['actions' => $actions];
            }
        }

        if ($this->shouldNotifyHighVulnerability($file, $ruleState)) {
            $count = (int) $file->getVulnerabilityCount();
            $actions = $this->notify(
                $metadata,
                'High vulnerability count detected',
                sprintf(
                    'Scan for %s reported %d vulnerabilities (threshold %d).',
                    $file->getOriginalFilename(),
                    $count,
                    $this->highVulnerabilityThreshold
                )
            );

            if ($actions) {
                $ruleState['notifiedRules'][self::RULE_HIGH_VULNERABILITY] = [
                    'notifiedAt' => $now->format(DATE_ATOM),
                    'actions' => $actions,
                    'vulnerabilityCount' => $count,
                ];
                $triggered[self::RULE_HIGH_VULNERABILITY] = [
                    'actions' => $actions,
                    'vulnerabilityCount' => $count,
                ];
            }
        }

        if ($this->shouldNotifyStuckUpload($file, $ruleState)) {
            $actions = $this->notify(
                $metadata,
                'Upload appears stuck',
                sprintf(
                    'Upload %s has been in status "%s" since %s.',
                    $file->getOriginalFilename(),
                    $file->getStatus(),
                    $file->getUpdatedAt()?->format(DATE_ATOM) ?? 'unknown'
                )
            );

            if ($actions) {
                $ruleState['notifiedRules'][self::RULE_UPLOAD_STUCK] = [
                    'notifiedAt' => $now->format(DATE_ATOM),
                    'actions' => $actions,
                    'status' => $file->getStatus(),
                ];
                $triggered[self::RULE_UPLOAD_STUCK] = [
                    'actions' => $actions,
                    'status' => $file->getStatus(),
                ];
            }
        }

        $ruleState['lastCheckAt'] = $now->format(DATE_ATOM);
        $payload['ruleState'] = $ruleState;
        $payload['metadata'] = $metadata;
        $file->setScanResultPayload($payload);

        if ($latestScan) {
            $latestScan->setUpdatedAt($now);
        }

        return new RuleEvaluationResult($triggered);
    }

    /**
     * @param array{email?: ?string, slackWebhook?: ?string} $metadata
     * @return array<string, bool>
     */
    private function notify(array $metadata, string $subject, string $body): array
    {
        $results = [
            'email' => $this->notificationService->sendEmail($metadata['email'] ?? null, $subject, $body),
            'slack' => $this->notificationService->sendSlack($metadata['slackWebhook'] ?? null, $subject . "\n" . $body),
        ];

        $triggeredActions = array_filter($results);
        $this->logger->info('Rule action evaluated', [
            'subject' => $subject,
            'results' => $results,
        ]);

        return $triggeredActions;
    }

    private function shouldNotifyUploadFailed(UploadedDependencyFile $file, array $ruleState): bool
    {
        if ($file->getStatus() !== 'error') {
            return false;
        }

        return !isset($ruleState['notifiedRules'][self::RULE_UPLOAD_FAILED]);
    }

    private function shouldNotifyHighVulnerability(UploadedDependencyFile $file, array $ruleState): bool
    {
        if ($file->getStatus() !== 'done') {
            return false;
        }

        $count = $file->getVulnerabilityCount();
        if ($count === null || $count <= $this->highVulnerabilityThreshold) {
            return false;
        }

        return !isset($ruleState['notifiedRules'][self::RULE_HIGH_VULNERABILITY]);
    }

    private function shouldNotifyStuckUpload(UploadedDependencyFile $file, array $ruleState): bool
    {
        if (!in_array($file->getStatus(), ['queued', 'uploading', 'processing'], true)) {
            return false;
        }

        $updatedAt = $file->getUpdatedAt();
        if (! $updatedAt) {
            return false;
        }

        $deadline = (new DateTimeImmutable())->sub($this->stuckInterval);
        if ($updatedAt > $deadline) {
            return false;
        }

        return !isset($ruleState['notifiedRules'][self::RULE_UPLOAD_STUCK]);
    }
}
