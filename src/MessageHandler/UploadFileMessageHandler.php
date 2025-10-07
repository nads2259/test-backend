<?php

namespace App\MessageHandler;

use App\Message\UploadFileMessage;
use App\Message\ProcessUploadMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use App\Entity\UploadedDependencyFile;

#[AsMessageHandler]
final class UploadFileMessageHandler
{
    private EntityManagerInterface $em;
    private MessageBusInterface $bus;

    public function __construct(EntityManagerInterface $em, MessageBusInterface $bus)
    {
        $this->em = $em;
        $this->bus = $bus;
    }

    public function __invoke(UploadFileMessage $message): void
    {
        $uploadedFileId = $message->getUploadedFileId();

        /** @var UploadedDependencyFile|null $uploaded */
        $uploaded = $this->em->getRepository(UploadedDependencyFile::class)->find($uploadedFileId);

        if (! $uploaded) {
            // nothing we can do
            return;
        }

        // Update status to 'queued' or 'ready_for_upload'
        $uploaded->setStatus('queued');
        $uploaded->setUpdatedAt(new \DateTimeImmutable());

        $this->em->persist($uploaded);
        $this->em->flush();

        // Dispatch processing message (this will upload to Debricked)
        $this->bus->dispatch(new ProcessUploadMessage($uploadedFileId));
    }
}
