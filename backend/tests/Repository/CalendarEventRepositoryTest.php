<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\CalendarEvent;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class CalendarEventRepositoryTest extends KernelTestCase
{
    private static \DateTimeImmutable $baseDate;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$baseDate = new \DateTimeImmutable('2026-03-21 10:00:00');
    }

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        // The schema is created once in tests/bootstrap.php, and DAMA rolls each
        // test back afterwards. Dropping and recreating the database here would
        // abort the transaction DAMA has already opened.
    }

    private function createUser(string $email): User
    {
        $user = new User();
        $user->setFirstName('Test');
        $user->setLastName('User');
        $user->setEmail($email);
        $user->setPassword('hashed_password');

        return $user;
    }

    private function createCalendarEvent(User $user, string $title, \DateTimeImmutable $startsAt, \DateTimeImmutable $endsAt): CalendarEvent
    {
        $event = new CalendarEvent();
        $event->setUserOwner($user);
        $event->setTitle($title);
        $event->setDescription('Test description');
        $event->setLocation('Test Location');
        $event->setStartsAt($startsAt);
        $event->setEndsAt($endsAt);
        $event->setAllDay(false);
        $event->setCreatedAt(new \DateTimeImmutable());

        return $event;
    }

    public function test_find_by_user(): void
    {
        $entityManager = self::getContainer()->get('doctrine')->getManager();

        // Create and persist user
        $user = $this->createUser('test_find_by_user_' . uniqid() . '@example.com');
        $entityManager->persist($user);

        // Create and persist events
        $event1 = $this->createCalendarEvent(
            $user,
            'Event 1',
            self::$baseDate,
            self::$baseDate->modify('+1 hour')
        );
        $event2 = $this->createCalendarEvent(
            $user,
            'Event 2',
            self::$baseDate->modify('+1 day'),
            self::$baseDate->modify('+1 day +1 hour')
        );

        $entityManager->persist($event1);
        $entityManager->persist($event2);
        $entityManager->flush();

        // Test repository method
        $repository = $entityManager->getRepository(CalendarEvent::class);
        $events = $repository->findByUser($user);

        $this->assertCount(2, $events);
        $this->assertContains($event1, $events);
        $this->assertContains($event2, $events);

        // Cleanup
        $entityManager->remove($event1);
        $entityManager->remove($event2);
        $entityManager->remove($user);
        $entityManager->flush();
    }

    public function test_find_by_user_between_dates(): void
    {
        $entityManager = self::getContainer()->get('doctrine')->getManager();

        // Create and persist user
        $user = $this->createUser('test_find_between_' . uniqid() . '@example.com');
        $entityManager->persist($user);

        // Create events in different date ranges
        $eventInRange1 = $this->createCalendarEvent(
            $user,
            'Event In Range 1',
            new \DateTimeImmutable('2026-03-22 10:00:00'),
            new \DateTimeImmutable('2026-03-22 11:00:00')
        );

        $eventInRange2 = $this->createCalendarEvent(
            $user,
            'Event In Range 2',
            new \DateTimeImmutable('2026-03-25 10:00:00'),
            new \DateTimeImmutable('2026-03-25 11:00:00')
        );

        $eventOutOfRange = $this->createCalendarEvent(
            $user,
            'Event Out Of Range',
            new \DateTimeImmutable('2026-04-01 10:00:00'),
            new \DateTimeImmutable('2026-04-01 11:00:00')
        );

        $entityManager->persist($eventInRange1);
        $entityManager->persist($eventInRange2);
        $entityManager->persist($eventOutOfRange);
        $entityManager->flush();

        // Test repository method with date range
        $repository = $entityManager->getRepository(CalendarEvent::class);
        $events = $repository->findByUserBetweenDates(
            $user,
            new \DateTimeImmutable('2026-03-20 00:00:00'),
            new \DateTimeImmutable('2026-03-31 23:59:59')
        );

        $this->assertCount(2, $events);
        $this->assertContains($eventInRange1, $events);
        $this->assertContains($eventInRange2, $events);
        $this->assertNotContains($eventOutOfRange, $events);

        // Cleanup
        $entityManager->remove($eventInRange1);
        $entityManager->remove($eventInRange2);
        $entityManager->remove($eventOutOfRange);
        $entityManager->remove($user);
        $entityManager->flush();
    }

    public function test_find_by_user_returns_empty_array_when_no_events(): void
    {
        $entityManager = self::getContainer()->get('doctrine')->getManager();

        // Create and persist user without events
        $user = $this->createUser('test_no_events_' . uniqid() . '@example.com');
        $entityManager->persist($user);
        $entityManager->flush();

        // Test repository method
        $repository = $entityManager->getRepository(CalendarEvent::class);
        $events = $repository->findByUser($user);

        $this->assertCount(0, $events);
        $this->assertIsArray($events);

        // Cleanup
        $entityManager->remove($user);
        $entityManager->flush();
    }

    public function test_find_by_user_between_dates_with_empty_result(): void
    {
        $entityManager = self::getContainer()->get('doctrine')->getManager();

        // Create and persist user
        $user = $this->createUser('test_empty_range_' . uniqid() . '@example.com');
        $entityManager->persist($user);

        // Create event outside the query range
        $event = $this->createCalendarEvent(
            $user,
            'Future Event',
            new \DateTimeImmutable('2026-05-01 10:00:00'),
            new \DateTimeImmutable('2026-05-01 11:00:00')
        );

        $entityManager->persist($event);
        $entityManager->flush();

        // Test repository method with non-overlapping date range
        $repository = $entityManager->getRepository(CalendarEvent::class);
        $events = $repository->findByUserBetweenDates(
            $user,
            new \DateTimeImmutable('2026-03-01 00:00:00'),
            new \DateTimeImmutable('2026-03-31 23:59:59')
        );

        $this->assertCount(0, $events);

        // Cleanup
        $entityManager->remove($event);
        $entityManager->remove($user);
        $entityManager->flush();
    }

    public function test_find_by_user_excludes_other_users_events(): void
    {
        $entityManager = self::getContainer()->get('doctrine')->getManager();

        // Create two users
        $user1 = $this->createUser('test_user1_' . uniqid() . '@example.com');
        $user2 = $this->createUser('test_user2_' . uniqid() . '@example.com');

        $entityManager->persist($user1);
        $entityManager->persist($user2);

        // Create events for each user
        $user1Event = $this->createCalendarEvent(
            $user1,
            'User 1 Event',
            self::$baseDate,
            self::$baseDate->modify('+1 hour')
        );

        $user2Event = $this->createCalendarEvent(
            $user2,
            'User 2 Event',
            self::$baseDate,
            self::$baseDate->modify('+1 hour')
        );

        $entityManager->persist($user1Event);
        $entityManager->persist($user2Event);
        $entityManager->flush();

        // Test that findByUser only returns events for the specified user
        $repository = $entityManager->getRepository(CalendarEvent::class);
        $user1Events = $repository->findByUser($user1);
        $user2Events = $repository->findByUser($user2);

        $this->assertCount(1, $user1Events);
        $this->assertCount(1, $user2Events);
        $this->assertContains($user1Event, $user1Events);
        $this->assertContains($user2Event, $user2Events);
        $this->assertNotContains($user2Event, $user1Events);
        $this->assertNotContains($user1Event, $user2Events);

        // Cleanup
        $entityManager->remove($user1Event);
        $entityManager->remove($user2Event);
        $entityManager->remove($user1);
        $entityManager->remove($user2);
        $entityManager->flush();
    }
}
