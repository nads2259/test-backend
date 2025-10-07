<?php

namespace App\Controller;

use App\Entity\UploadedDependencyFile;
use App\Repository\ScanResultRepository;
use App\Repository\UploadedDependencyFileRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api')]
final class RepositoryController extends AbstractController
{
    #[Route('/repositories', name: 'list_repositories', methods: ['GET'])]
    public function listRepositories(UploadedDependencyFileRepository $uploads): JsonResponse
    {
        $files = $uploads->findAll();

        $data = array_map(static function (UploadedDependencyFile $file) {
            return [
                'id' => $file->getId(),
                'filename' => $file->getOriginalFilename(),
                'status' => $file->getStatus(),
                'vulnerabilityCount' => $file->getVulnerabilityCount(),
                'createdAt' => $file->getCreatedAt()?->format(DATE_ATOM),
                'updatedAt' => $file->getUpdatedAt()?->format(DATE_ATOM),
            ];
        }, $files);

        return $this->json([
            'data' => $data,
            'meta' => [
                'status' => 'ok',
                'count' => count($data),
            ],
        ], Response::HTTP_OK);
    }

    #[Route('/repository/{id}/scans', name: 'repository_scans', methods: ['GET'])]
    public function repositoryScans(
        int $id,
        UploadedDependencyFileRepository $uploads,
        ScanResultRepository $scanRepo
    ): JsonResponse {
        $file = $uploads->find($id);

        if (! $file) {
            return $this->json([
                'error' => 'Not Found',
                'message' => "Upload with ID {$id} not found.",
            ], Response::HTTP_NOT_FOUND);
        }

        $scans = $scanRepo->findBy(['uploadedFile' => $file]);

        $data = array_map(static function ($scan) {
            return [
                'id' => $scan->getId(),
                'scanId' => $scan->getScanId(),
                'status' => $scan->getStatus(),
                'createdAt' => $scan->getCreatedAt()?->format(DATE_ATOM),
            ];
        }, $scans);

        return $this->json([
            'upload' => [
                'id' => $file->getId(),
                'filename' => $file->getOriginalFilename(),
                'status' => $file->getStatus(),
            ],
            'scans' => $data,
            'meta' => [
                'status' => 'ok',
                'count' => count($data),
            ],
        ], Response::HTTP_OK);
    }
}
