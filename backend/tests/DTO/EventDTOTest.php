<?php

declare(strict_types=1);

namespace App\Tests\DTO;

use App\DTO\CreateEventDTO;
use App\DTO\UpdateEventDTO;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

class EventDTOTest extends TestCase
{
    private \Symfony\Component\Validator\Validator\ValidatorInterface $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->getValidator();
    }

    public function test_create_event_dto_from_array_with_all_fields(): void
    {
        $data = [
            'title' => 'Test Event',
            'description' => 'Test Description',
            'location' => 'Test Location',
            'startsAt' => '2026-03-22T10:00:00+00:00',
            'endsAt' => '2026-03-22T11:00:00+00:00',
            'allDay' => false,
            'reminderAt' => '2026-03-22T09:00:00+00:00',
        ];

        $dto = CreateEventDTO::fromArray($data);

        $this->assertEquals('Test Event', $dto->title);
        $this->assertEquals('Test Description', $dto->description);
        $this->assertEquals('Test Location', $dto->location);
        $this->assertEquals('2026-03-22T10:00:00+00:00', $dto->startsAt);
        $this->assertEquals('2026-03-22T11:00:00+00:00', $dto->endsAt);
        $this->assertFalse($dto->allDay);
        $this->assertEquals('2026-03-22T09:00:00+00:00', $dto->reminderAt);
    }

    public function test_create_event_dto_from_array_with_optional_fields(): void
    {
        $data = [
            'title' => 'Test Event',
            'startsAt' => '2026-03-22T10:00:00+00:00',
            'endsAt' => '2026-03-22T11:00:00+00:00',
        ];

        $dto = CreateEventDTO::fromArray($data);

        $this->assertEquals('Test Event', $dto->title);
        $this->assertNull($dto->description);
        $this->assertNull($dto->location);
        $this->assertEquals('2026-03-22T10:00:00+00:00', $dto->startsAt);
        $this->assertEquals('2026-03-22T11:00:00+00:00', $dto->endsAt);
        $this->assertFalse($dto->allDay);
        $this->assertNull($dto->reminderAt);
    }

    public function test_create_event_dto_trims_title(): void
    {
        $data = [
            'title' => '  Test Event  ',
            'startsAt' => '2026-03-22T10:00:00+00:00',
            'endsAt' => '2026-03-22T11:00:00+00:00',
        ];

        $dto = CreateEventDTO::fromArray($data);

        $this->assertEquals('Test Event', $dto->title);
    }

    public function test_create_event_dto_validates_blank_title(): void
    {
        $data = [
            'title' => '',
            'startsAt' => '2026-03-22T10:00:00+00:00',
            'endsAt' => '2026-03-22T11:00:00+00:00',
        ];

        $dto = CreateEventDTO::fromArray($data);
        $errors = $this->validator->validate($dto);

        $this->assertGreaterThan(0, count($errors));
    }

    public function test_create_event_dto_validates_title_length(): void
    {
        $data = [
            'title' => 'AB',
            'startsAt' => '2026-03-22T10:00:00+00:00',
            'endsAt' => '2026-03-22T11:00:00+00:00',
        ];

        $dto = CreateEventDTO::fromArray($data);
        $errors = $this->validator->validate($dto);

        $this->assertCount(1, $errors);
        $this->assertEquals('Title must be at least 3 characters', $errors->get(0)->getMessage());
    }

    public function test_create_event_dto_validates_starts_at_format(): void
    {
        $data = [
            'title' => 'Test Event',
            'startsAt' => 'invalid-date',
            'endsAt' => '2026-03-22T11:00:00+00:00',
        ];

        $dto = CreateEventDTO::fromArray($data);
        $errors = $this->validator->validate($dto);

        $this->assertCount(1, $errors);
        $this->assertEquals('Starts at must be a valid datetime', $errors->get(0)->getMessage());
    }

    public function test_create_event_dto_validates_ends_at_format(): void
    {
        $data = [
            'title' => 'Test Event',
            'startsAt' => '2026-03-22T10:00:00+00:00',
            'endsAt' => 'invalid-date',
        ];

        $dto = CreateEventDTO::fromArray($data);
        $errors = $this->validator->validate($dto);

        $this->assertCount(1, $errors);
        $this->assertEquals('Ends at must be a valid datetime', $errors->get(0)->getMessage());
    }

    public function test_create_event_dto_validates_reminder_at_format(): void
    {
        $data = [
            'title' => 'Test Event',
            'startsAt' => '2026-03-22T10:00:00+00:00',
            'endsAt' => '2026-03-22T11:00:00+00:00',
            'reminderAt' => 'invalid-date',
        ];

        $dto = CreateEventDTO::fromArray($data);
        $errors = $this->validator->validate($dto);

        $this->assertCount(1, $errors);
        $this->assertEquals('Reminder at must be a valid datetime', $errors->get(0)->getMessage());
    }

    public function test_update_event_dto_from_array_with_all_fields(): void
    {
        $data = [
            'title' => 'Updated Event',
            'description' => 'Updated Description',
            'location' => 'Updated Location',
            'startsAt' => '2026-03-23T10:00:00+00:00',
            'endsAt' => '2026-03-23T11:00:00+00:00',
            'allDay' => true,
            'reminderAt' => '2026-03-23T09:00:00+00:00',
        ];

        $dto = UpdateEventDTO::fromArray($data);

        $this->assertEquals('Updated Event', $dto->title);
        $this->assertEquals('Updated Description', $dto->description);
        $this->assertEquals('Updated Location', $dto->location);
        $this->assertEquals('2026-03-23T10:00:00+00:00', $dto->startsAt);
        $this->assertEquals('2026-03-23T11:00:00+00:00', $dto->endsAt);
        $this->assertTrue($dto->allDay);
        $this->assertEquals('2026-03-23T09:00:00+00:00', $dto->reminderAt);
    }

    public function test_update_event_dto_from_array_with_partial_fields(): void
    {
        $data = [
            'title' => 'Updated Event',
        ];

        $dto = UpdateEventDTO::fromArray($data);

        $this->assertEquals('Updated Event', $dto->title);
        $this->assertNull($dto->description);
        $this->assertNull($dto->location);
        $this->assertNull($dto->startsAt);
        $this->assertNull($dto->endsAt);
        $this->assertNull($dto->allDay);
        $this->assertNull($dto->reminderAt);
    }

    public function test_update_event_dto_handles_null_values(): void
    {
        $data = [
            'title' => null,
            'description' => null,
            'location' => null,
            'startsAt' => null,
            'endsAt' => null,
            'allDay' => null,
            'reminderAt' => null,
        ];

        $dto = UpdateEventDTO::fromArray($data);

        $this->assertNull($dto->title);
        $this->assertNull($dto->description);
        $this->assertNull($dto->location);
        $this->assertNull($dto->startsAt);
        $this->assertNull($dto->endsAt);
        $this->assertNull($dto->reminderAt);
        // allDay is cast to bool, so null becomes false
        $this->assertFalse($dto->allDay);
    }

    public function test_update_event_dto_trims_title(): void
    {
        $data = [
            'title' => '  Updated Event  ',
        ];

        $dto = UpdateEventDTO::fromArray($data);

        $this->assertEquals('Updated Event', $dto->title);
    }

    public function test_update_event_dto_validates_blank_title(): void
    {
        $data = [
            'title' => '',
        ];

        $dto = UpdateEventDTO::fromArray($data);
        $errors = $this->validator->validate($dto);

        $this->assertGreaterThan(0, count($errors));
    }

    public function test_update_event_dto_validates_title_length(): void
    {
        $data = [
            'title' => 'AB',
        ];

        $dto = UpdateEventDTO::fromArray($data);
        $errors = $this->validator->validate($dto);

        $this->assertGreaterThan(0, count($errors));
    }

    public function test_update_event_dto_validates_starts_at_format(): void
    {
        $data = [
            'startsAt' => 'invalid-date',
        ];

        $dto = UpdateEventDTO::fromArray($data);
        $errors = $this->validator->validate($dto);

        $this->assertGreaterThan(0, count($errors));
    }

    public function test_update_event_dto_validates_ends_at_format(): void
    {
        $data = [
            'endsAt' => 'invalid-date',
        ];

        $dto = UpdateEventDTO::fromArray($data);
        $errors = $this->validator->validate($dto);

        $this->assertGreaterThan(0, count($errors));
    }

    public function test_update_event_dto_validates_reminder_at_format(): void
    {
        $data = [
            'reminderAt' => 'invalid-date',
        ];

        $dto = UpdateEventDTO::fromArray($data);
        $errors = $this->validator->validate($dto);

        $this->assertGreaterThan(0, count($errors));
    }

    public function test_update_event_dto_with_missing_fields_does_not_validate_as_null(): void
    {
        $data = [
            'title' => 'Updated Event',
        ];

        $dto = UpdateEventDTO::fromArray($data);
        $errors = $this->validator->validate($dto);

        $this->assertCount(0, $errors);
        $this->assertNull($dto->description);
        $this->assertNull($dto->location);
    }
}
