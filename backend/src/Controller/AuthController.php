<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\RegisterUserDTO;
use App\Entity\RefreshToken;
use App\Entity\User;
use App\Service\EncryptionService;
use App\Service\TwoFactorService;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/auth')]
class AuthController extends AbstractController
{
    private const REFRESH_COOKIE = 'pryvora_refresh';

    private JWTTokenManagerInterface $jwtTokenManager;
    private EntityManagerInterface $entityManager;
    private ValidatorInterface $validator;
    private EncryptionService $encryptionService;

    public function __construct(
        JWTTokenManagerInterface $jwtTokenManager,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
        EncryptionService $encryptionService,
    ) {
        $this->jwtTokenManager = $jwtTokenManager;
        $this->entityManager = $entityManager;
        $this->validator = $validator;
        $this->encryptionService = $encryptionService;
    }

    #[Route('/register', name: 'app_register', methods: ['POST'])]
    public function register(Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $userPasswordHasher): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $dto = RegisterUserDTO::fromArray($data);

        $errors = $this->validator->validate($dto);

        if (\count($errors) > 0) {
            $messages = [];
            foreach ($errors as $error) {
                $messages[$error->getPropertyPath()] = $error->getMessage();
            }

            return new JsonResponse(['errors' => $messages], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($entityManager->getRepository(User::class)->findOneBy(['email' => $dto->email])) {
            return new JsonResponse(['error' => 'Email already exists'], Response::HTTP_CONFLICT);
        }

        $user = new User();
        $user->setFirstName($this->encryptionService->encrypt($dto->firstName));
        $user->setLastName($this->encryptionService->encrypt($dto->lastName));
        $user->setEmail($dto->email);
        $user->setPassword($userPasswordHasher->hashPassword($user, $dto->password));
        $entityManager->persist($user);
        $entityManager->flush();

        return new JsonResponse(['message' => 'User registered successfully'], Response::HTTP_CREATED);
    }

    #[Route('/login', name: 'app_login', methods: ['POST'])]
    public function login(Request $request, UserPasswordHasherInterface $userPasswordHasher): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        if (empty($data['email']) || empty($data['password'])) {
            return new JsonResponse(['error' => 'Email and password are required'], Response::HTTP_BAD_REQUEST);
        }

        /** @var User|null $user */
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $data['email']]);

        if (!$user || !$userPasswordHasher->isPasswordValid($user, $data['password'])) {
            return new JsonResponse(['error' => 'Invalid credentials'], Response::HTTP_UNAUTHORIZED);
        }

        if ($user->isTwoFactorEnabled()) {
            $request->getSession()->set('2fa_user_email', $user->getEmail());

            return new JsonResponse([
                'requires_2fa' => true,
                'message' => '2FA verification required',
            ], Response::HTTP_OK);
        }

        return $this->complete_login($user);
    }

    #[Route('/verify-2fa', name: 'app_verify_2fa', methods: ['POST'])]
    public function verify_2fa(Request $request, TwoFactorService $twoFactorService): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        if (empty($data['code'])) {
            return new JsonResponse(['error' => 'Verification code is required'], Response::HTTP_BAD_REQUEST);
        }

        $email = $request->getSession()->get('2fa_user_email');

        if (!$email) {
            return new JsonResponse(['error' => 'No pending 2FA verification'], Response::HTTP_BAD_REQUEST);
        }

        /** @var User|null $user */
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

        if (!$user) {
            return new JsonResponse(['error' => 'User not found'], Response::HTTP_UNAUTHORIZED);
        }

        $isRecoveryCode = !empty($data['is_recovery_code']);
        $isValid = false;

        if ($isRecoveryCode) {
            // Verify recovery code
            $isValid = $twoFactorService->verify_recovery_code($user, $data['code']);
        } else {
            // Verify TOTP code
            $isValid = $twoFactorService->verify_code($user, $data['code']);
        }

        if (!$isValid) {
            return new JsonResponse(['error' => 'Invalid verification code'], Response::HTTP_UNAUTHORIZED);
        }

        $request->getSession()->remove('2fa_user_email');

        return $this->complete_login($user);
    }

    private function complete_login(User $user): JsonResponse
    {
        $token = $this->jwtTokenManager->create($user);

        $refreshTokenValue = bin2hex(random_bytes(64));
        $validUntil = new \DateTime('+30 days');

        $rt = new RefreshToken();
        $rt->setRefreshToken($refreshTokenValue);
        $rt->setValid($validUntil);
        $rt->setUsername($user->getEmail());

        $this->entityManager->persist($rt);
        $this->entityManager->flush();

        $cookie = Cookie::create(self::REFRESH_COOKIE)
            ->withValue($refreshTokenValue)
            ->withExpires($validUntil)
            ->withPath('/')
            ->withHttpOnly(true)
            ->withSecure((bool) ($_ENV['COOKIE_SECURE'] ?? false))
            ->withSameSite('lax');

        $response = new JsonResponse([
            'message' => 'Login successful',
            'token' => $token,
            'expires_at' => $validUntil,
        ], Response::HTTP_OK);

        $response->headers->setCookie($cookie);

        return $response;
    }

    #[Route('/refresh', name: 'app_refresh', methods: ['POST'])]
    public function refresh(Request $request, JWTTokenManagerInterface $JWTTokenManager): JsonResponse
    {
        $refreshTokenValue = $request->cookies->get(self::REFRESH_COOKIE);

        if (empty($refreshTokenValue)) {
            return new JsonResponse(['error' => 'Refresh token not provided'], Response::HTTP_BAD_REQUEST);
        }

        /** @var RefreshToken|null $refreshToken */
        $refreshToken = $this->entityManager->getRepository(RefreshToken::class)->findOneBy(['refreshToken' => $refreshTokenValue]);

        if (!$refreshToken) {
            return new JsonResponse(['error' => 'Invalid refresh token'], Response::HTTP_UNAUTHORIZED);
        }

        if ($refreshToken->getValid() < new \DateTime()) {
            return new JsonResponse(['error' => 'Refresh token expired'], Response::HTTP_UNAUTHORIZED);
        }

        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $refreshToken->getUsername()]);

        if (!$user) {
            return new JsonResponse(['error' => 'User not found'], Response::HTTP_UNAUTHORIZED);
        }

        // Only rotate refresh token if it's older than 1 day
        $shouldRotate = $refreshToken->getValid()->getTimestamp() - time() < (29 * 24 * 60 * 60);

        $newRefreshTokenValue = $refreshTokenValue;
        $newValidUntil = $refreshToken->getValid();

        if ($shouldRotate) {
            $newRefreshTokenValue = bin2hex(random_bytes(64));
            $newValidUntil = new \DateTime('+30 days');

            $refreshToken->setRefreshToken($newRefreshTokenValue);
            $refreshToken->setValid($newValidUntil);
            $this->entityManager->flush();
        }

        $token = $JWTTokenManager->create($user);

        $response = new JsonResponse([
            'message' => 'Token refreshed',
            'token' => $token,
            'expires_at' => $newValidUntil,
        ], Response::HTTP_OK);

        // Only set cookie if we rotated the token
        if ($shouldRotate) {
            $cookie = Cookie::create(self::REFRESH_COOKIE)
                ->withValue($newRefreshTokenValue)
                ->withExpires($newValidUntil)
                ->withPath('/')
                ->withHttpOnly(true)
                ->withSecure((bool) ($_ENV['COOKIE_SECURE'] ?? false))
                ->withSameSite('lax');

            $response->headers->setCookie($cookie);
        }

        return $response;
    }

    #[Route('/logout', name: 'app_logout', methods: ['POST'])]
    public function logout(Request $request): JsonResponse
    {
        $refreshTokenValue = $request->cookies->get(self::REFRESH_COOKIE);

        if ($refreshTokenValue) {
            $refreshToken = $this->entityManager->getRepository(RefreshToken::class)->findOneBy(['refreshToken' => $refreshTokenValue]);
            if ($refreshToken) {
                $this->entityManager->remove($refreshToken);
                $this->entityManager->flush();
            }
        }

        // Clear cookie with path '/' (new path)
        $cookie = Cookie::create(self::REFRESH_COOKIE)
            ->withValue('')
            ->withExpires(new \DateTime('-1 day'))
            ->withPath('/')
            ->withHttpOnly(true)
            ->withSecure((bool) ($_ENV['COOKIE_SECURE'] ?? false))
            ->withSameSite('lax');

        // Also clear cookie with old path '/api/auth' for backwards compatibility
        $oldCookie = Cookie::create(self::REFRESH_COOKIE)
            ->withValue('')
            ->withExpires(new \DateTime('-1 day'))
            ->withPath('/api/auth')
            ->withHttpOnly(true)
            ->withSecure((bool) ($_ENV['COOKIE_SECURE'] ?? false))
            ->withSameSite('lax');

        $response = new JsonResponse(['message' => 'Logout successful'], Response::HTTP_OK);
        $response->headers->setCookie($cookie);
        $response->headers->setCookie($oldCookie);

        return $response;
    }
}
