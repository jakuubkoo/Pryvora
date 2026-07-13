<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateEventDTO
{
    #[Assert\Length(min: 3, max: 255, minMessage: 'Title must be at least 3 characters', maxMessage: 'Title cannot be longer than 255 characters')]
    #[Assert\NotBlank(message: 'Title is required')]
    public readonly ?string $title;

    public readonly ?string $description;

    public readonly ?string $location;

    #[Assert\DateTime(format: \DateTimeImmutable::ATOM, message: 'Starts at must be a valid datetime')]
    public readonly ?string $startsAt;

    #[Assert\DateTime(format: \DateTimeImmutable::ATOM, message: 'Ends at must be a valid datetime')]
    public readonly ?string $endsAt;

    public readonly ?bool $allDay;

    #[Assert\DateTime(format: \DateTimeImmutable::ATOM, message: 'Reminder at must be a valid datetime')]
    public readonly ?string $reminderAt;

    /**
     * Creates a new instance of the class from the provided array of data.
     *
     * @param array<string, mixed> $data an associative array containing the necessary data to populate the object
     */
    public function __construct(array $data)
    {
        $this->title = \array_key_exists('title', $data) && null !== $data['title'] ? trim($data['title']) : null;
        $this->description = \array_key_exists('description', $data) && null !== $data['description'] ? trim($data['description']) : null;
        $this->location = \array_key_exists('location', $data) && null !== $data['location'] ? trim($data['location']) : null;
        $this->startsAt = \array_key_exists('startsAt', $data) ? $data['startsAt'] : null;
        $this->endsAt = \array_key_exists('endsAt', $data) ? $data['endsAt'] : null;
        $this->allDay = \array_key_exists('allDay', $data) ? (bool) $data['allDay'] : null;
        $this->reminderAt = \array_key_exists('reminderAt', $data) ? $data['reminderAt'] : null;
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
