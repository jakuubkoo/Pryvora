<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Note;
use App\Entity\Task;
use App\Entity\User;
use App\Repository\NoteRepository;
use App\Repository\TaskRepository;
use App\Service\EncryptionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/dashboard', name: 'api_dashboard_')]
class DashboardController extends AbstractController
{
    private TaskRepository $taskRepository;
    private NoteRepository $noteRepository;
    private EncryptionService $encryptionService;

    public function __construct(
        TaskRepository $taskRepository,
        NoteRepository $noteRepository,
        EncryptionService $encryptionService,
    ) {
        $this->taskRepository = $taskRepository;
        $this->noteRepository = $noteRepository;
        $this->encryptionService = $encryptionService;
    }

    #[Route('/')]
    public function getDashboardData(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $notes = $this->noteRepository->findLastFiveByUser($user);

        return new JsonResponse([
            'recent_notes' => array_map([$this, 'serializeNote'], $notes),
            'tasks' => [
                'overdue' => array_map([$this, 'serializeTask'], $this->taskRepository->findOverdueByUser($user)),
                'today' => array_map([$this, 'serializeTask'], $this->taskRepository->findTodayByUser($user)),
                'upcoming' => array_map([$this, 'serializeTask'], $this->taskRepository->findUpcomingByUser($user)),
            ],
        ], Response::HTTP_OK);
    }

    /**
     * Converts a Note object into an associative array representation.
     *
     * @param Note $note the note to be serialized
     *
     * @return array<string, mixed> the serialized note, including its ID, title, decrypted content (if present), and creation timestamp
     */
    private function serializeNote(Note $note): array
    {
        return [
            'id' => $note->getId(),
            'title' => $note->getTitle(),
            'content' => $note->getContent() ? $this->encryptionService->decrypt($note->getContent()) : '',
            'created_at' => $note->getCreatedAt()->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Serializes a Task object into an associative array format.
     *
     * @param Task $task the task instance to be serialized
     *
     * @return array<string, mixed> the serialized task containing its ID, title, status value, and creation timestamp
     */
    private function serializeTask(Task $task): array
    {
        return [
            'id' => $task->getId(),
            'title' => $task->getTitle(),
            'description' => $task->getDescription() ? $this->encryptionService->decrypt($task->getDescription()) : '',
            'status' => $task->getStatus()->value,
            'due_date' => $task->getDueDate()?->format('Y-m-d'),
            'created_at' => $task->getCreatedAt()?->format('Y-m-d H:i:s'),
        ];
    }
}
