<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/user')]
class UserController extends AbstractController
{
    #[Route('/me', name: 'app_user_index')]
    public function me(): Response
    {
        $user = $this->getUser();
        return $this->json([
            'id' => $user->getId(),
            'email' => $user->getUserIdentifier(),
        ]);
    }
}
