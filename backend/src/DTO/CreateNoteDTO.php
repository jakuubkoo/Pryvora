<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class CreateNoteDTO
{
    public function __construct(
        #[Assert\NotBlank(message: 'Title is required')]
        #[Assert\Length(min: 3, max: 255, minMessage: 'Title must be at least 3 characters', maxMessage: 'Title cannot be longer than 255 characters')]
        public readonly string $title,

        #[Assert\NotBlank(message: 'Content is required')]
        #[Assert\Length(min: 3, minMessage: 'Content must be at least 3 characters')]
        public readonly string $content,
    ) {
    }

    /**
     * Creates a new instance of the class from the given array of data.
     *
     * @param array<string, mixed> $data an associative array containing the required data
     *
     * @return self a new instance of the class
     */
    public static function fromArray(array $data): self
    {
        return new self(
            title: $data['title'],
            content: $data['content'],
        );
    }
}
