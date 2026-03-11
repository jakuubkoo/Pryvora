<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/user')]
class UserController extends AbstractController
{
    public function __construct(
        private readonly UserPasswordHasherInterface $userPasswordHasher,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/me', name: 'app_user_index')]
    public function me(): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'two_factor_enabled' => $user->isTwoFactorEnabled(),
        ]);
    }

    #[Route('/change-password', name: 'app_user_change_password', methods: ['POST'])]
    public function change_password(Request $request): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        if (empty($data['current_password']) || empty($data['new_password'])) {
            return $this->json(['error' => 'Current and new password are required'], Response::HTTP_BAD_REQUEST);
        }

        if (!$this->userPasswordHasher->isPasswordValid($user, $data['current_password'])) {
            return $this->json(['error' => 'Invalid current password'], Response::HTTP_UNAUTHORIZED);
        }

        $user->setPassword($this->userPasswordHasher->hashPassword($user, $data['new_password']));
        $this->entityManager->flush();

        return $this->json(['message' => 'Password changed successfully'], Response::HTTP_OK);
    }
}
