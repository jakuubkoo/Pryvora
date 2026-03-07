<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\EncryptionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api', methods: ['GET'])]
class TestController extends AbstractController
{

    private EncryptionService $encryptionService;

    public function __construct(EncryptionService $encryptionService)
    {
        $this->encryptionService = $encryptionService;
    }

    #[Route('/test')]
    public function test(): JsonResponse
    {
        $message = 'Welcome to your new controller!';
        $encrypted = $this->encryptionService->encrypt($message);
        $decrypted = $this->encryptionService->decrypt($encrypted);


        return $this->json([
            'message' => $message,
            'encrypted' => $encrypted,
            'decrypted' => $decrypted,
        ]);
    }
}
