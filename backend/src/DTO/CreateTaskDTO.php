<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enum\TaskPriority;
use App\Enum\TaskStatus;
use Symfony\Component\Validator\Constraints as Assert;

class CreateTaskDTO
{
    public function __construct(
        #[Assert\NotBlank(message: 'Title is required')]
        #[Assert\Length(min: 3, max: 255, minMessage: 'Title must be at least 3 characters', maxMessage: 'Title cannot be longer than 255 characters')]
        public readonly string $title,

        public readonly ?string $description = null,

        public readonly TaskStatus $status = TaskStatus::TODO,

        public readonly TaskPriority $priority = TaskPriority::LOW,

        public readonly ?\DateTimeImmutable $dueDate = null,
    ) {
    }

    /**
     * Creates a new instance of the class from the provided array of data.
     *
     * @param array<string, mixed> $data an associative array containing the necessary data to populate the object
     *
     * @return self returns an instance of the class populated with the given data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['title'] ?? '',
            $data['description'] ?? null,
            isset($data['status']) ? TaskStatus::from($data['status']) : TaskStatus::TODO,
            isset($data['priority']) ? TaskPriority::from($data['priority']) : TaskPriority::LOW,
            $data['dueDate'] ?? null,
        );
    }
}
