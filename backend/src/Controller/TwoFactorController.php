<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Service\TwoFactorService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/2fa')]
class TwoFactorController extends AbstractController
{
    public function __construct(
        private readonly TwoFactorService $twoFactorService,
    ) {
    }

    #[Route('/setup', name: 'app_2fa_setup', methods: ['POST'])]
    public function setup(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        if ($user->isTwoFactorEnabled()) {
            return new JsonResponse(['error' => '2FA is already enabled'], Response::HTTP_BAD_REQUEST);
        }

        $data = $this->twoFactorService->setup_two_factor($user);

        return new JsonResponse([
            'secret' => $data['secret'],
            'qr_code' => $data['qr_code'],
        ], Response::HTTP_OK);
    }

    #[Route('/enable', name: 'app_2fa_enable', methods: ['POST'])]
    public function enable(Request $request): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        if ($user->isTwoFactorEnabled()) {
            return new JsonResponse(['error' => '2FA is already enabled'], Response::HTTP_BAD_REQUEST);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        if (empty($data['code'])) {
            return new JsonResponse(['error' => 'Verification code is required'], Response::HTTP_BAD_REQUEST);
        }

        $result = $this->twoFactorService->enable_two_factor($user, $data['code']);

        if (!$result || !\is_array($result)) {
            return new JsonResponse(['error' => 'Invalid verification code'], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse([
            'message' => '2FA enabled successfully',
            'recovery_codes' => $result['recovery_codes'] ?? [],
        ], Response::HTTP_OK);
    }

    #[Route('/disable', name: 'app_2fa_disable', methods: ['POST'])]
    public function disable(Request $request): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        if (!$user->isTwoFactorEnabled()) {
            return new JsonResponse(['error' => '2FA is not enabled'], Response::HTTP_BAD_REQUEST);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        if (empty($data['code'])) {
            return new JsonResponse(['error' => 'Verification code is required'], Response::HTTP_BAD_REQUEST);
        }

        $success = $this->twoFactorService->disable_two_factor($user, $data['code']);

        if (!$success) {
            return new JsonResponse(['error' => 'Invalid verification code'], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse(['message' => '2FA disabled successfully'], Response::HTTP_OK);
    }

    #[Route('/status', name: 'app_2fa_status', methods: ['GET'])]
    public function status(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        return new JsonResponse([
            'enabled' => $user->isTwoFactorEnabled(),
            'recovery_codes_count' => $this->twoFactorService->get_recovery_codes_count($user),
        ], Response::HTTP_OK);
    }

    #[Route('/recovery-codes/regenerate', name: 'app_2fa_regenerate_recovery_codes', methods: ['POST'])]
    public function regenerate_recovery_codes(Request $request): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        if (!$user->isTwoFactorEnabled()) {
            return new JsonResponse(['error' => '2FA is not enabled'], Response::HTTP_BAD_REQUEST);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        if (empty($data['code'])) {
            return new JsonResponse(['error' => 'Verification code is required'], Response::HTTP_BAD_REQUEST);
        }

        // Verify the 2FA code before regenerating
        if (!$this->twoFactorService->verify_code($user, $data['code'])) {
            return new JsonResponse(['error' => 'Invalid verification code'], Response::HTTP_BAD_REQUEST);
        }

        // Generate new recovery codes
        $recoveryCodes = $this->twoFactorService->regenerate_recovery_codes($user);

        return new JsonResponse([
            'message' => 'Recovery codes regenerated successfully',
            'recovery_codes' => $recoveryCodes,
        ], Response::HTTP_OK);
    }
}
