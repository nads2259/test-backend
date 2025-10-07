<?php

namespace App\Command;

use App\Service\DebrickedClient;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class DebrickedTestCommand extends Command
{
    protected static $defaultName = 'app:debricked:test';

    private DebrickedClient $debricked;

    public function __construct(DebrickedClient $debricked)
    {
        parent::__construct();
        $this->debricked = $debricked;
    }

    protected function configure(): void
    {
        $this->setDescription('Test Debricked API upload + scan linking (using API token)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->section('Testing Debricked API with API token...');

        $filePath = __DIR__ . '/../../tests/fixtures/composer.json';
        $filename = 'composer.json';

        try {
            // Step 1: Upload dependency file
            $upload = $this->debricked->uploadDependencyFile($filePath, $filename);

            $identifier = $upload['ciUploadId'] ?? null;

            if (!$identifier) {
                $io->error('Upload response did not include ciUploadId: ' . json_encode($upload));
                return Command::FAILURE;
            }

            $io->success(sprintf(
                "Upload successful. ciUploadId: %s (fileId: %s)",
                $identifier,
                $upload['uploadProgramsFileId'] ?? 'n/a'
            ));

            // Step 2: Poll for linked scan
            $io->writeln('Searching for linked scan...');

            $scan = $this->debricked->findScanByUploadId($identifier);

            if ($scan) {
                $io->success('Linked scan found!');
                $io->writeln(json_encode($scan, JSON_PRETTY_PRINT));
            } else {
                $io->warning('No scan found yet for this upload. Try again later.');
            }
        } catch (\Throwable $e) {
            $io->error('Debricked API call failed: ' . $e->getMessage());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
