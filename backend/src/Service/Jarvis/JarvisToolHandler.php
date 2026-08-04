<?php

declare(strict_types=1);

namespace App\Service\Jarvis;

use App\DTO\CreateNoteDTO;
use App\DTO\CreateTaskDTO;
use App\Entity\Note;
use App\Entity\Task;
use App\Entity\User;
use App\Enum\TaskPriority;
use App\Enum\TaskStatus;
use App\Service\EncryptionService;
use App\Service\Search\SearchIndexer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * The write half of the Jarvis API: turns a loose, assistant-shaped payload into
 * the same entities TaskController and NotesController produce.
 *
 * The normalising is the point. CreateTaskDTO::fromArray calls
 * TaskPriority::from() and new \DateTimeImmutable() on raw input, both of which
 * throw on anything unexpected, and CreateNoteDTO::fromArray reads $data['title']
 * unguarded — fine behind a form, not fine behind a language model.
 */
final class JarvisToolHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
        private readonly EncryptionService $encryptionService,
        private readonly SearchIndexer $searchIndexer,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @throws \InvalidArgumentException on input the DTO cannot even be built from
     * @throws ValidationFailedException on input the DTO rejects
     */
    public function addTask(User $user, array $payload): Task
    {
        $dto = new CreateTaskDTO(
            title: trim((string) ($payload['title'] ?? '')),
            description: isset($payload['description']) ? trim((string) $payload['description']) : null,
            status: TaskStatus::TODO,
            priority: $this->priority($payload['priority'] ?? null),
            dueDate: $this->dueDate($payload['due_date'] ?? null),
        );

        $this->validate($dto);

        $task = new Task();
        $task->setTitle($dto->title);
        $task->setDescription($dto->description ? $this->encryptionService->encrypt($dto->description) : '');
        $task->setStatus($dto->status);
        $task->setPriority($dto->priority);
        $task->setDueDate($dto->dueDate);
        $task->setAuthor($user);

        $this->entityManager->persist($task);
        $this->entityManager->flush();

        $this->searchIndexer->indexTask($task);

        return $task;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @throws ValidationFailedException
     */
    public function addNote(User $user, array $payload): Note
    {
        $content = trim((string) ($payload['content'] ?? ''));

        $dto = new CreateNoteDTO(
            title: $this->noteTitle($payload['title'] ?? null, $content),
            content: $content,
        );

        $this->validate($dto);

        $note = new Note();
        $note->setTitle($dto->title);
        $note->setContent($this->encryptionService->encrypt($dto->content));
        $note->setUser($user);

        $this->entityManager->persist($note);
        $this->entityManager->flush();

        $this->searchIndexer->indexNote($note);

        return $note;
    }

    /**
     * Unknown priorities are rejected rather than quietly downgraded — an
     * assistant that files a "urgent" task as low priority is worse than one
     * that says it could not.
     */
    private function priority(mixed $value): TaskPriority
    {
        if (null === $value || '' === $value) {
            return TaskPriority::MEDIUM;
        }

        $priority = \is_string($value) ? TaskPriority::tryFrom($value) : null;

        if (null === $priority) {
            throw new \InvalidArgumentException('Priority must be one of: low, medium, high');
        }

        return $priority;
    }

    /**
     * Task::$dueDate is a DATE_IMMUTABLE column, so the input is a plain date and
     * the "!" anchors the rest of the fields to midnight instead of "now".
     */
    private function dueDate(mixed $value): ?\DateTimeImmutable
    {
        if (null === $value || '' === $value) {
            return null;
        }

        $due_date = \is_string($value) ? \DateTimeImmutable::createFromFormat('!Y-m-d', $value) : false;

        if (false === $due_date) {
            throw new \InvalidArgumentException('Due date must be a date in YYYY-MM-DD format');
        }

        return $due_date;
    }

    /**
     * Notes arrive dictated, so a title is optional on the wire — but the column
     * is NOT NULL and the DTO asserts a minimum length, so one gets derived from
     * the opening line the way a person would title it.
     */
    private function noteTitle(mixed $title, string $content): string
    {
        if (\is_string($title) && '' !== trim($title)) {
            return mb_substr(trim($title), 0, 255);
        }

        $first_line = trim(strtok($content, "\n") ?: '');

        return '' === $first_line ? 'Note from Jarvis' : mb_substr($first_line, 0, 255);
    }

    private function validate(object $dto): void
    {
        $errors = $this->validator->validate($dto);

        if (\count($errors) > 0) {
            throw new ValidationFailedException($dto, $errors);
        }
    }
}
