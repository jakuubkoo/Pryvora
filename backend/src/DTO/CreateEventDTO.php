<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class CreateEventDTO
{
    #[Assert\NotBlank(message: 'Title is required')]
    #[Assert\Length(min: 3, max: 255, minMessage: 'Title must be at least 3 characters', maxMessage: 'Title cannot be longer than 255 characters')]
    public readonly string $title;

    public readonly ?string $description;

    public readonly ?string $location;

    #[Assert\NotBlank(message: 'Starts at is required')]
    #[Assert\DateTime(format: \DateTimeImmutable::ATOM, message: 'Starts at must be a valid datetime')]
    public readonly string $startsAt;

    #[Assert\NotBlank(message: 'Ends at is required')]
    #[Assert\DateTime(format: \DateTimeImmutable::ATOM, message: 'Ends at must be a valid datetime')]
    public readonly string $endsAt;

    public readonly ?bool $allDay;

    #[Assert\DateTime(format: \DateTimeImmutable::ATOM, message: 'Reminder at must be a valid datetime')]
    public readonly ?string $reminderAt;

    /**
     * Initializes the class with event data.
     *
     * @param array<string, mixed> $data the array containing event details, such as title, description, location, start and end times, all-day status, and reminder time
     */
    public function __construct(array $data)
    {
        $this->title = trim($data['title'] ?? '');
        $this->description = isset($data['description']) ? trim($data['description']) : null;
        $this->location = isset($data['location']) ? trim($data['location']) : null;
        $this->startsAt = $data['startsAt'] ?? '';
        $this->endsAt = $data['endsAt'] ?? '';
        $this->allDay = (bool) ($data['allDay'] ?? false);
        $this->reminderAt = $data['reminderAt'] ?? null;
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
        return new self($data);
    }
}
