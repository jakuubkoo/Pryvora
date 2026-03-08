<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateNoteDTO
{
    public function __construct(
        #[Assert\NotBlank(message: 'Title is required')]
        #[Assert\Length(min: 3, max: 255, minMessage: 'Title must be at least 3 characters', maxMessage: 'Title cannot be longer than 255 characters')]
        public ?string $title = null,

        #[Assert\NotBlank(message: 'Content is required')]
        #[Assert\Length(min: 3, minMessage: 'Content must be at least 3 characters')]
        public ?string $content = null,
    ) {
    }

    /**
     * Creates an instance of the class from the given array of data.
     *
     * @param array<string, mixed> $data an associative array containing the properties to initialize the object with
     *
     * @return self the newly created instance of the class
     */
    public static function fromArray(array $data): self
    {
        $dto = new self();
        if (\array_key_exists('title', $data)) {
            $dto->title = $data['title'];
        }
        if (\array_key_exists('content', $data)) {
            $dto->content = $data['content'];
        }

        return $dto;
    }
}
