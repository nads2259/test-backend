<?php

namespace App\Message;

/**
 * Message representing that an uploaded file must be sent to Debricked.
 */
final class ProcessUploadMessage
{
    private int $uploadedFileId;

    public function __construct(int $uploadedFileId)
    {
        $this->uploadedFileId = $uploadedFileId;
    }

    public function getUploadedFileId(): int
    {
        return $this->uploadedFileId;
    }
}
