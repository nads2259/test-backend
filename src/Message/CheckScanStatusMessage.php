<?php

namespace App\Message;

/**
 * Message to check the status of an upload/scan on Debricked.
 *
 * Contains:
 *  - uploadedFileId: local UploadedDependencyFile id in your DB
 *  - ciUploadId: the Debricked upload id (ciUploadId) returned after upload
 */
final class CheckScanStatusMessage
{
    private int $uploadedFileId;
    private int $ciUploadId;

    public function __construct(int $uploadedFileId, int $ciUploadId)
    {
        $this->uploadedFileId = $uploadedFileId;
        $this->ciUploadId = $ciUploadId;
    }

    public function getUploadedFileId(): int
    {
        return $this->uploadedFileId;
    }

    public function getCiUploadId(): int
    {
        return $this->ciUploadId;
    }
}
