<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Google\GoogleAuthenticatorInterface;

class TwoFactorService
{
    public function __construct(
        private readonly GoogleAuthenticatorInterface $googleAuthenticator,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function generate_secret(): string
    {
        return $this->googleAuthenticator->generateSecret();
    }

    public function get_qr_code_image(User $user): string
    {
        $qrCodeContent = $this->googleAuthenticator->getQRContent($user);

        $qrCode = new QrCode(
            data: $qrCodeContent,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 300,
            margin: 10,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
            foregroundColor: new Color(0, 0, 0),
            backgroundColor: new Color(255, 255, 255)
        );

        $writer = new PngWriter();
        $result = $writer->write($qrCode);

        return $result->getDataUri();
    }

    public function verify_code(User $user, string $code): bool
    {
        if (!$user->getTwoFactorSecret()) {
            return false;
        }

        return $this->googleAuthenticator->checkCode($user, $code);
    }

    public function enable_two_factor(User $user, string $code): bool
    {
        if (!$user->getTwoFactorSecret()) {
            return false;
        }

        if (!$this->verify_code($user, $code)) {
            return false;
        }

        $user->setTwoFactorEnabled(true);
        $user->setTwoFactorConfirmedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        return true;
    }

    public function disable_two_factor(User $user, string $code): bool
    {
        if (!$user->isTwoFactorEnabled()) {
            return false;
        }

        if (!$this->verify_code($user, $code)) {
            return false;
        }

        $user->setTwoFactorEnabled(false);
        $user->setTwoFactorSecret(null);
        $user->setTwoFactorConfirmedAt(null);
        $this->entityManager->flush();

        return true;
    }

    /**
     * @return array{secret: string, qr_code: string}
     */
    public function setup_two_factor(User $user): array
    {
        $secret = $this->generate_secret();
        $user->setTwoFactorSecret($secret);
        $this->entityManager->flush();

        $qrCodeImage = $this->get_qr_code_image($user);

        return [
            'secret' => $secret,
            'qr_code' => $qrCodeImage,
        ];
    }
}
