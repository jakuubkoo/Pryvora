<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class RegisterUserDTO
{
    public function __construct(
        #[Assert\NotBlank(message: 'First name is required')]
        #[Assert\Length(min: 2, max: 50, minMessage: 'First name must be at least 2 characters')]
        public readonly string $firstName = '',

        #[Assert\NotBlank(message: 'Last name is required')]
        #[Assert\Length(min: 2, max: 50, minMessage: 'Last name must be at least 2 characters')]
        public readonly string $lastName = '',

        #[Assert\NotBlank(message: 'Email is required')]
        #[Assert\Email(message: 'Invalid email format')]
        public readonly string $email = '',

        #[Assert\NotBlank(message: 'Password is required')]
        #[Assert\Length(min: 8, minMessage: 'Password must be at least 8 characters')]
        public readonly string $password = '',

        #[Assert\NotBlank(message: 'Please confirm your password')]
        public readonly string $confirmPassword = '',
    ) {
    }

    /**
     * Creates an instance of the class from the provided array of data.
     *
     * @param array<string, mixed> $data an associative array containing the necessary data to populate the object
     *
     * @return self returns an instance of the class populated with the given data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['firstName'] ?? '',
            $data['lastName'] ?? '',
            $data['email'] ?? '',
            $data['password'] ?? '',
            $data['confirmPassword'] ?? '',
        );
    }

    #[Assert\Callback]
    public function validatePasswordMatch(ExecutionContextInterface $context): void
    {
        if ($this->password !== $this->confirmPassword) {
            $context->buildViolation('Passwords do not match')
                ->atPath('confirmPassword')
                ->addViolation();
        }
    }
}
