<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\RefreshToken;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/auth')]
class AuthController extends AbstractController
{

    private const REFRESH_COOKIE = 'pryvora_refresh';

    #[Route('/register', name: 'app_register', methods: ['POST'])]
    public function register(Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $userPasswordHasher): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        if (empty($data['email']) || empty($data['password'])) {
            return new JsonResponse(['error' => 'Email and password are required'], Response::HTTP_BAD_REQUEST);
        }

        if($entityManager->getRepository(User::class)->findOneBy(['email' => $data['email']])) {
            return new JsonResponse(['error' => 'Email already exists'], Response::HTTP_CONFLICT);
        }

        $user = new User();
        $user->setEmail($data['email']);
        $user->setPassword($userPasswordHasher->hashPassword($user, $data['password']));
        $entityManager->persist($user);
        $entityManager->flush();

        return new JsonResponse(['message' => 'User registered successfully'], Response::HTTP_CREATED);
    }

    #[Route('/login', name: 'app_login', methods: ['POST'])]
    public function login(Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $userPasswordHasher, JWTTokenManagerInterface $JWTTokenManager): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        if (empty($data['email']) || empty($data['password'])) {
            return new JsonResponse(['error' => 'Email and password are required'], Response::HTTP_BAD_REQUEST);
        }

        /** @var User|null $user */
        $user = $entityManager->getRepository(User::class)->findOneBy(['email' => $data['email']]);

        if (!$user || !$userPasswordHasher->isPasswordValid($user, $data['password'])) {
            return new JsonResponse(['error' => 'Invalid credentials'], Response::HTTP_UNAUTHORIZED);
        }

        $token = $JWTTokenManager->create($user);

        $refreshTokenValue = bin2hex(random_bytes(64));
        $validUntil = new \DateTime('+30 days');

        $rt = new RefreshToken();
        $rt->setRefreshToken($refreshTokenValue);
        $rt->setValid($validUntil);
        $rt->setUsername($user->getEmail());

        $entityManager->persist($rt);
        $entityManager->flush();

        $cookie = Cookie::create(self::REFRESH_COOKIE)
            ->withValue($refreshTokenValue)
            ->withExpires($validUntil)
            ->withPath('/api/auth')
            ->withHttpOnly(true)
            ->withSecure((bool)($_ENV['COOKIE_SECURE'] ?? false))
            ->withSameSite('lax');


        $response = new JsonResponse([
            'message' => 'Login successful',
            'token' => $token,
            'expires_at' => $validUntil
        ], Response::HTTP_OK);

        $response->headers->setCookie($cookie);

        return $response;
    }

    #[Route('/refresh', name: 'app_refresh', methods: ['POST'])]
    public function refresh(Request $request, EntityManagerInterface $entityManager, JWTTokenManagerInterface $JWTTokenManager): JsonResponse
    {
        $refreshTokenValue = $request->cookies->get(self::REFRESH_COOKIE);

        if (empty($refreshTokenValue)) {
            return new JsonResponse(['error' => 'Refresh token not provided'], Response::HTTP_BAD_REQUEST);
        }

        /** @var RefreshToken|null $refreshToken */
        $refreshToken = $entityManager->getRepository(RefreshToken::class)->findOneBy(['refreshToken' => $refreshTokenValue]);

        if (!$refreshToken) {
            return new JsonResponse(['error' => 'Invalid refresh token'], Response::HTTP_UNAUTHORIZED);
        }

        if ($refreshToken->getValid() < new \DateTime()) {
            return new JsonResponse(['error' => 'Refresh token expired'], Response::HTTP_UNAUTHORIZED);
        }

        $user = $entityManager->getRepository(User::class)->findOneBy(['email' => $refreshToken->getUsername()]);

        if (!$user) {
            return new JsonResponse(['error' => 'User not found'], Response::HTTP_UNAUTHORIZED);
        }

        $newRefreshTokenValue = bin2hex(random_bytes(64));
        $newValidUntil = new \DateTime('+30 days');

        $refreshToken->setRefreshToken($newRefreshTokenValue);
        $refreshToken->setValid($newValidUntil);
        $entityManager->flush();

        $cookie = Cookie::create(self::REFRESH_COOKIE)
            ->withValue($newRefreshTokenValue)
            ->withExpires($newValidUntil)
            ->withPath('/api/auth')
            ->withHttpOnly(true)
            ->withSecure((bool)($_ENV['COOKIE_SECURE'] ?? false))
            ->withSameSite('lax');

        $token = $JWTTokenManager->create($user);

        $response = new JsonResponse([
            'message' => 'Token refreshed',
            'token' => $token,
            'expires_at' => $newValidUntil
        ], Response::HTTP_OK);

        $response->headers->setCookie($cookie);

        return $response;
    }

    #[Route('/logout', name: 'app_logout', methods: ['POST'])]
    public function logout(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $refreshTokenValue = $request->cookies->get(self::REFRESH_COOKIE);

        if($refreshTokenValue){
            $refreshToken = $entityManager->getRepository(RefreshToken::class)->findOneBy(['refreshToken' => $refreshTokenValue]);
            if ($refreshToken) {
                $entityManager->remove($refreshToken);
                $entityManager->flush();
            }
        }

        $cookie = Cookie::create(self::REFRESH_COOKIE)
            ->withValue('')
            ->withExpires(new \DateTime('-1 day'))
            ->withPath('/api/auth')
            ->withHttpOnly(true)
            ->withSecure((bool)($_ENV['COOKIE_SECURE'] ?? false))
            ->withSameSite('lax');

        $response = new JsonResponse(['message' => 'Logout successful'], Response::HTTP_OK);
        $response->headers->setCookie($cookie);

        return $response;
    }

}
