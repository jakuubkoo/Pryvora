<?php

declare(strict_types=1);

namespace App\Service\Jarvis;

use App\Entity\CalendarEvent;
use App\Entity\Task;
use App\Entity\User;
use App\Repository\CalendarEventRepository;
use App\Repository\TaskRepository;
use App\Service\EncryptionService;

/**
 * The read half of the Jarvis API.
 *
 * All the querying already exists on the repositories — DashboardController
 * assembles almost the same picture for the web UI. This exists so the
 * assistant gets one call instead of three, and so the serialised shape lives
 * next to the endpoint that returns it rather than inside the controller.
 */
final class JarvisContextService
{
    /**
     * How far ahead the briefing looks for calendar events. A day is too short
     * to be useful for "what's coming up"; a month is noise.
     */
    private const EVENT_HORIZON = '+7 days';

    public function __construct(
        private readonly TaskRepository $taskRepository,
        private readonly CalendarEventRepository $calendarEventRepository,
        private readonly EncryptionService $encryptionService,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function buildBriefing(User $user): array
    {
        $today = new \DateTimeImmutable('today');

        return [
            'date' => $today->format('Y-m-d'),
            'tasks' => [
                // Overdue is included deliberately: an assistant that only reads
                // out today's list silently buries everything already late.
                'today' => array_map([$this, 'serializeTask'], $this->taskRepository->findTodayByUser($user)),
                'overdue' => array_map([$this, 'serializeTask'], $this->taskRepository->findOverdueByUser($user)),
            ],
            'events' => array_map(
                [$this, 'serializeEvent'],
                $this->calendarEventRepository->findByUserBetweenDates($user, $today, $today->modify(self::EVENT_HORIZON)),
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeTask(Task $task): array
    {
        return [
            'id' => $task->getId(),
            'title' => $task->getTitle(),
            'description' => $task->getDescription() ? $this->encryptionService->decrypt($task->getDescription()) : '',
            'status' => $task->getStatus()->value,
            'priority' => $task->getPriority()->value,
            'due_date' => $task->getDueDate()?->format('Y-m-d'),
            'created_at' => $task->getCreatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeEvent(CalendarEvent $event): array
    {
        return [
            'id' => $event->getId(),
            'title' => $event->getTitle(),
            'location' => $event->getLocation(),
            'starts_at' => $event->getStartsAt()?->format(\DateTimeInterface::ATOM),
            'ends_at' => $event->getEndsAt()?->format(\DateTimeInterface::ATOM),
            'all_day' => $event->isAllDay(),
        ];
    }
}
