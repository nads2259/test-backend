<?php

namespace App\Message;

/**
 * Message representing that a local uploaded file (UploadedDependencyFile) should be processed.
 */
final class UploadFileMessage
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
