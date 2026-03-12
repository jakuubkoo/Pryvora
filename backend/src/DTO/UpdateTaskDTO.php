<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enum\TaskPriority;
use App\Enum\TaskStatus;
use Symfony\Component\Validator\Constraints as Assert;

class UpdateTaskDTO
{
    public function __construct(
        #[Assert\NotBlank(message: 'Title is required')]
        #[Assert\Length(min: 3, max: 255, minMessage: 'Title must be at least 3 characters', maxMessage: 'Title cannot be longer than 255 characters')]
        public ?string $title = null,

        #[Assert\Length(min: 3, minMessage: 'Description must be at least 3 characters')]
        public ?string $description = null,

        public ?TaskStatus $status = null,

        public ?TaskPriority $priority = null,

        public ?\DateTimeImmutable $dueDate = null,
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
        if (\array_key_exists('description', $data)) {
            $dto->description = $data['description'];
        }
        if (\array_key_exists('status', $data)) {
            $dto->status = TaskStatus::from($data['status']);
        }
        if (\array_key_exists('priority', $data)) {
            $dto->priority = TaskPriority::from($data['priority']);
        }
        if (\array_key_exists('dueDate', $data)) {
            $dto->dueDate = $data['dueDate'];
        }

        return $dto;
    }
}
