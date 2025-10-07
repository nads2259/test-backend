<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

final class HomeController
{
    #[Route(path: '/', name: 'home', methods: ['GET'])]
    public function index(): JsonResponse
    {
        return new JsonResponse([
            'service' => 'Dependency File Scanner & Rule Engine',
            'status' => 'running',
            'version' => '1.0.0'
        ]);
    }
}
