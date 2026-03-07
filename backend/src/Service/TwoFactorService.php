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
    private const RECOVERY_CODES_COUNT = 10;

    public function __construct(
        private readonly GoogleAuthenticatorInterface $googleAuthenticator,
        private readonly EntityManagerInterface $entityManager,
        private readonly EncryptionService $encryptionService,
    ) {
    }

    public function generate_secret(): string
    {
        return $this->googleAuthenticator->generateSecret();
    }

    public function get_qr_code_image(User $user): string
    {
        // Temporarily decrypt the secret for QR code generation
        $decryptedSecret = $this->get_decrypted_secret($user);
        if (!$decryptedSecret) {
            throw new \RuntimeException('No 2FA secret found');
        }

        // Create a temporary user object with decrypted secret for QR generation
        $tempUser = clone $user;
        $tempUser->setTwoFactorSecret($decryptedSecret);

        $qrCodeContent = $this->googleAuthenticator->getQRContent($tempUser);

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

        // Decrypt the secret for verification
        $decryptedSecret = $this->get_decrypted_secret($user);
        if (!$decryptedSecret) {
            return false;
        }

        // Create a temporary user object with decrypted secret for verification
        $tempUser = clone $user;
        $tempUser->setTwoFactorSecret($decryptedSecret);

        return $this->googleAuthenticator->checkCode($tempUser, $code);
    }

    /**
     * @return array{success: bool, recovery_codes?: array<string>}|bool
     */
    public function enable_two_factor(User $user, string $code): array|bool
    {
        if (!$user->getTwoFactorSecret()) {
            return false;
        }

        if (!$this->verify_code($user, $code)) {
            return false;
        }

        // Generate recovery codes
        $recoveryCodes = $this->generate_recovery_codes();
        $this->save_recovery_codes($user, $recoveryCodes);

        $user->setTwoFactorEnabled(true);
        $user->setTwoFactorConfirmedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        return [
            'success' => true,
            'recovery_codes' => $recoveryCodes,
        ];
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
        $user->setRecoveryCodes(null);
        $user->setRecoveryCodesGeneratedAt(null);
        $this->entityManager->flush();

        return true;
    }

    /**
     * @return array{secret: string, qr_code: string}
     */
    public function setup_two_factor(User $user): array
    {
        $secret = $this->generate_secret();

        // Encrypt the secret before storing
        $encryptedSecret = $this->encryptionService->encrypt($secret);
        $user->setTwoFactorSecret($encryptedSecret);
        $this->entityManager->flush();

        $qrCodeImage = $this->get_qr_code_image($user);

        return [
            'secret' => $secret,
            'qr_code' => $qrCodeImage,
        ];
    }

    /**
     * Get decrypted 2FA secret.
     */
    private function get_decrypted_secret(User $user): ?string
    {
        $encryptedSecret = $user->getTwoFactorSecret();
        if (!$encryptedSecret) {
            return null;
        }

        try {
            return $this->encryptionService->decrypt($encryptedSecret);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Generate recovery codes.
     *
     * @return array<string>
     */
    private function generate_recovery_codes(): array
    {
        $codes = [];
        for ($i = 0; $i < self::RECOVERY_CODES_COUNT; ++$i) {
            $codes[] = $this->generate_recovery_code();
        }

        return $codes;
    }

    /**
     * Generate a single recovery code.
     */
    private function generate_recovery_code(): string
    {
        $bytes = random_bytes(4);

        return strtoupper(bin2hex($bytes));
    }

    /**
     * Save recovery codes (encrypted).
     *
     * @param array<string> $codes
     */
    private function save_recovery_codes(User $user, array $codes): void
    {
        $codesJson = json_encode($codes);
        if (false === $codesJson) {
            throw new \RuntimeException('Failed to encode recovery codes');
        }

        $encryptedCodes = $this->encryptionService->encrypt($codesJson);
        $user->setRecoveryCodes($encryptedCodes);
        $user->setRecoveryCodesGeneratedAt(new \DateTimeImmutable());
    }

    /**
     * Verify recovery code.
     */
    public function verify_recovery_code(User $user, string $code): bool
    {
        $encryptedCodes = $user->getRecoveryCodes();
        if (!$encryptedCodes) {
            return false;
        }

        try {
            $codesJson = $this->encryptionService->decrypt($encryptedCodes);
            $codes = json_decode($codesJson, true);

            if (!\is_array($codes)) {
                return false;
            }

            // Check if code exists in the list
            $codeIndex = array_search(strtoupper($code), array_map('strtoupper', $codes), true);
            if (false === $codeIndex) {
                return false;
            }

            // Remove the used code
            unset($codes[$codeIndex]);
            $codes = array_values($codes); // Re-index array

            // Save updated codes
            $this->save_recovery_codes($user, $codes);
            $this->entityManager->flush();

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get remaining recovery codes count.
     */
    public function get_recovery_codes_count(User $user): int
    {
        $encryptedCodes = $user->getRecoveryCodes();
        if (!$encryptedCodes) {
            return 0;
        }

        try {
            $codesJson = $this->encryptionService->decrypt($encryptedCodes);
            $codes = json_decode($codesJson, true);

            return \is_array($codes) ? \count($codes) : 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Regenerate recovery codes.
     *
     * @return array<string>
     */
    public function regenerate_recovery_codes(User $user): array
    {
        $recoveryCodes = $this->generate_recovery_codes();
        $this->save_recovery_codes($user, $recoveryCodes);
        $this->entityManager->flush();

        return $recoveryCodes;
    }
}
