<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateTagDTO
{
    public function __construct(
        #[Assert\NotBlank(message: 'Name is required')]
        #[Assert\Length(min: 3, max: 255, minMessage: 'Name must be at least 3 characters long', maxMessage: 'Name cannot be longer than 255 characters')]
        public ?string $name = null,
        #[Assert\NotBlank(message: 'Color is required')]
        #[Assert\Length(min: 7, max: 7, minMessage: 'Color must be 7 characters long', maxMessage: 'Color must be 7 characters long')]
        #[Assert\Regex('/^#([A-Fa-f0-9]{6})$/', message: 'Color must be a valid hex color')]
        public ?string $color = null,
    ) {
    }

    /**
     * Creates an instance of the class from an associative array.
     *
     * @param array<string, mixed> $data the input data array containing key-value pairs
     *
     * @return self returns an instance of the class with properties populated from the array
     */
    public static function fromArray(array $data): self
    {
        $dto = new self();
        if (\array_key_exists('name', $data)) {
            $dto->name = $data['name'];
        }
        if (\array_key_exists('color', $data)) {
            $dto->color = $data['color'];
        }

        return $dto;
    }
}
