<?php

namespace App\Command;

use App\Entity\UploadedDependencyFile;
use App\RuleEngine\RuleEngine;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class RunRuleEngineCommand extends Command
{
    protected static $defaultName = 'app:rules:run';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RuleEngine $ruleEngine
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Evaluate rule engine against uploads and dispatch notifications.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $uploads = $this->entityManager->createQueryBuilder()
            ->select('u')
            ->from(UploadedDependencyFile::class, 'u')
            ->orderBy('u.updatedAt', 'ASC')
            ->getQuery()
            ->getResult();

        if (! $uploads) {
            $io->success('No uploads found.');
            return Command::SUCCESS;
        }

        $evaluated = 0;
        foreach ($uploads as $upload) {
            \assert($upload instanceof UploadedDependencyFile);

            if (! $this->shouldEvaluate($upload)) {
                continue;
            }

            $latestScan = $this->getLatestScan($upload);
            $result = $this->ruleEngine->evaluate($upload, $latestScan);

            $this->entityManager->persist($upload);
            if ($latestScan) {
                $this->entityManager->persist($latestScan);
            }

            if ($result->hasTriggered()) {
                $io->writeln(sprintf(
                    'Upload #%d triggered rules: %s',
                    $upload->getId(),
                    implode(', ', array_keys($result->getTriggeredRules()))
                ));
            }

            ++$evaluated;
        }

        $this->entityManager->flush();

        $io->success(sprintf('Rule evaluation completed for %d uploads.', $evaluated));

        return Command::SUCCESS;
    }

    private function shouldEvaluate(UploadedDependencyFile $file): bool
    {
        return in_array($file->getStatus(), ['error', 'done', 'processing', 'uploading', 'queued'], true);
    }

    private function getLatestScan(UploadedDependencyFile $file): ?\App\Entity\ScanResult
    {
        $latest = null;
        foreach ($file->getScanResults() as $scan) {
            if ($latest === null || $scan->getCreatedAt() > $latest->getCreatedAt()) {
                $latest = $scan;
            }
        }

        return $latest;
    }
}
