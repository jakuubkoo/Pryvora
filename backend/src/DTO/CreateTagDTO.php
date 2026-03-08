<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class CreateTagDTO
{
    public function __construct(
        #[Assert\NotBlank(message: 'Name is required')]
        #[Assert\Length(min: 3, max: 255, minMessage: 'Name must be at least 3 characters', maxMessage: 'Name cannot be longer than 255 characters')]
        public string $name,
        #[Assert\Length(min: 7, max: 7, minMessage: 'Color must be 7 characters', maxMessage: 'Color must be 7 characters')]
        #[Assert\Regex('/^#([A-Fa-f0-9]{6})$/', message: 'Color must be a valid hex color')]
        public ?string $color = null,
    ) {
    }

    /**
     * Creates a new instance of the class from the provided request data.
     *
     * @param array<string, mixed> $data associative array containing the necessary data to initialize the instance
     *
     * @return self a new instance of the class populated with the provided data
     */
    public static function fromRequest(array $data): self
    {
        return new self(
            name: $data['name'],
            color: $data['color'] ?? null,
        );
    }
}
