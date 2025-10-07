<?php

namespace App\Controller;

use App\Entity\UploadedDependencyFile;
use App\Repository\UploadedDependencyFileRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\FileUploadService;
use Psr\Log\LoggerInterface;

final class UploadController extends AbstractController
{
    private FileUploadService $fileUploadService;
    private LoggerInterface $logger;

    public function __construct(
        FileUploadService $fileUploadService,
        LoggerInterface $logger
    ) {
        $this->fileUploadService = $fileUploadService;
        $this->logger = $logger;
    }

    #[Route('/api/uploads', name: 'upload_files', methods: ['POST'])]
    public function upload(Request $request): JsonResponse
    {
        try {
            $files = $request->files->all();
            $metadata = [
                'email' => $request->request->get('email'),
                'slack_webhook' => $request->request->get('slack_webhook'),
            ];
            $uploadedFiles = $this->fileUploadService->handleUploadedFiles($files, $metadata);

            $data = [];
            foreach ($uploadedFiles as $file) {
                $data[] = array_merge($file, [
                    'errorMessage' => $file['errorMessage'] ?? null,
                    'createdAt' => $file['createdAt'] ?? null,
                    'updatedAt' => $file['updatedAt'] ?? null,
                ]);
            }


            return $this->json([
                'data' => $data,
                'meta' => [
                    'status' => 'ok',
                    'count' => count($data),
                ],
            ], Response::HTTP_CREATED);

        } catch (\Throwable $e) {
            $this->logger->error('Upload API failed', [
                'exception' => $e,
            ]);

            return $this->json([
                'error' => 'File upload failed',
                'message' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }


    #[Route('/api/uploads/{id}', name: 'upload_status', methods: ['GET'])]
    public function status(int $id, UploadedDependencyFileRepository $repository): JsonResponse
    {
        $file = $repository->find($id);

        if (!$file) {
            return $this->json([
                'error' => 'Not Found',
                'message' => "File with ID {$id} not found.",
            ], Response::HTTP_NOT_FOUND);
        }

        $data = [
            'id' => $file->getId(),
            'filename' => $file->getOriginalFilename(),
            'status' => $file->getStatus(),
            'vulnerabilityCount' => $file->getVulnerabilityCount(),
            'errorMessage' => $file->getErrorMessage(),
            'createdAt' => $file->getCreatedAt()?->format(DATE_ATOM),
            'updatedAt' => $file->getUpdatedAt()?->format(DATE_ATOM),
            'scanResultPayload' => $file->getScanResultPayload(),
            'metadata' => $this->extractMetadata($file->getScanResultPayload()),
            'ruleState' => $this->extractRuleState($file->getScanResultPayload()),
        ];

        return $this->json($data, Response::HTTP_OK);
    }

    #[Route('/api/uploads', name: 'upload_list', methods: ['GET'])]
    public function list(Request $request, UploadedDependencyFileRepository $repository): JsonResponse
    {
        $page = max(1, (int)$request->query->get('page', 1));
        $limit = min(50, (int)$request->query->get('limit', 10));
        $statusFilter = $request->query->get('status');

        $qb = $repository->createQueryBuilder('u')
            ->orderBy('u.createdAt', 'DESC');

        if ($statusFilter) {
            $qb->andWhere('u.status = :status')
               ->setParameter('status', $statusFilter);
        }

        $total = (clone $qb)->select('COUNT(u.id)')->getQuery()->getSingleScalarResult();

        $files = $qb->setFirstResult(($page - 1) * $limit)
                    ->setMaxResults($limit)
                    ->getQuery()
                    ->getResult();

        $data = [];
        foreach ($files as $file) {
            /** @var UploadedDependencyFile $file */
            $data[] = [
                'id' => $file->getId(),
                'filename' => $file->getOriginalFilename(),
                'status' => $file->getStatus(),
                'errorMessage' => $file->getErrorMessage(), // 🔹 added here
                'createdAt' => $file->getCreatedAt()?->format(DATE_ATOM),
                'updatedAt' => $file->getUpdatedAt()?->format(DATE_ATOM),
                'vulnerabilityCount' => $file->getVulnerabilityCount(),
                'metadata' => $this->extractMetadata($file->getScanResultPayload()),
                'ruleState' => $this->extractRuleState($file->getScanResultPayload()),
            ];
        }

        return $this->json([
            'data' => $data,
            'meta' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit),
            ],
        ], Response::HTTP_OK);
    }
}

    /**
     * @param array|null $payload
     * @return array{email: ?string, slackWebhook: ?string}
     */
    private function extractMetadata(?array $payload): array
    {
        $metadata = $payload['metadata'] ?? [];

        return [
            'email' => $metadata['email'] ?? null,
            'slackWebhook' => $metadata['slackWebhook'] ?? null,
        ];
    }

    private function extractRuleState(?array $payload): array
    {
        return $payload['ruleState'] ?? [];
    }
}
