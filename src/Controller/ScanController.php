<?php

namespace App\Controller;

use App\Repository\ScanResultRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class ScanController extends AbstractController
{
    #[Route('/api/scan/{scanId}', name: 'scan_details', methods: ['GET'])]
    public function scanDetails(int $scanId, ScanResultRepository $repository): JsonResponse
    {
        $scan = $repository->findOneBy(['scanId' => $scanId]);

        if (!$scan) {
            return $this->json([
                'error' => 'Not Found',
                'message' => "Scan with ID {$scanId} not found.",
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'scanId' => $scan->getScanId(),
            'status' => $scan->getStatus(),
            'createdAt' => $scan->getCreatedAt()?->format(DATE_ATOM),
            'rawResponse' => $scan->getRawResponse(),
        ], Response::HTTP_OK);
    }
}
