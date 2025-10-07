<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;


final class DebrickedClient
{
    private HttpClientInterface $http;
    private string $apiBase;
    private string $apiToken;
    private LoggerInterface $logger;

    public function __construct(HttpClientInterface $http, string $apiBaseUrl, string $apiToken, LoggerInterface $logger)
    {
        $this->http = $http;
        $this->apiBase = rtrim($apiBaseUrl, '/');
        $this->apiToken = $apiToken;
        $this->logger = $logger;
    }

    /**
     * Upload a dependency file to Debricked.
     * Returns array with at least statusCode + response.
     */
    public function uploadDependencyFile(string $filePath, ?string $originalFilename = null): array
    {
        if (!is_readable($filePath)) {
            $this->logger->error('File not readable for Debricked upload', ['path' => $filePath]);
            return ['error' => 'File not readable', 'path' => $filePath];
        }

        $url = $this->apiBase . '/api/1.0/open/uploads/dependencies/files';

        // Build multipart form data
        $formFields = [
            'fileData'       => DataPart::fromPath($filePath, $originalFilename ?? basename($filePath)),
            'repositoryName' => 'local-demo-repo',
            'commitName'     => 'local-scan-' . uniqid(),
            'branchName'     => 'main',
        ];
        $formData = new FormDataPart($formFields);

        // Send request
        $response = $this->http->request('POST', $url, [
            'headers' => array_merge([
                'Authorization' => 'Bearer ' . $this->apiToken,
            ], $formData->getPreparedHeaders()->toArray()),
            'body' => $formData->bodyToIterable(),
        ]);

        $statusCode = $response->getStatusCode();
        $content = $response->toArray(false);

        $this->logger->info('Debricked upload response', [
            'statusCode'   => $statusCode,
            'response'     => $content,
            'uploadedFile' => $filePath,
        ]);

        return $content;
    }

    /**
     * Check upload status on Debricked.
     * Returns decoded array.
     */
    public function checkUploadStatus(int $ciUploadId): array
    {
        $url = $this->apiBase . '/api/1.0/open/uploads/dependencies/files/' . $ciUploadId . '/status';

        $response = $this->http->request('GET', $url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiToken,
            ],
        ]);

        $content = $response->toArray(false);

        $this->logger->info('Debricked status response', [
            'ciUploadId' => $ciUploadId,
            'response'   => $content,
        ]);

        return $content;
    }
}
