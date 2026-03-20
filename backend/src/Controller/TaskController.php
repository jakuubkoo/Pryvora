<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\CreateTaskDTO;
use App\DTO\UpdateTaskDTO;
use App\Entity\Task;
use App\Entity\User;
use App\Enum\TaskPriority;
use App\Enum\TaskStatus;
use App\Repository\TaskRepository;
use App\Service\EncryptionService;
use App\Service\Search\SearchIndexer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/task', name: 'api_task_')]
class TaskController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private EncryptionService $encryptionService;
    private TaskRepository $taskRepository;
    private ValidatorInterface $validator;
    private SearchIndexer $searchIndexer;

    public function __construct(
        EntityManagerInterface $entityManager,
        EncryptionService $encryptionService,
        TaskRepository $taskRepository,
        ValidatorInterface $validator,
        SearchIndexer $searchIndexer,
    ) {
        $this->entityManager = $entityManager;
        $this->encryptionService = $encryptionService;
        $this->taskRepository = $taskRepository;
        $this->validator = $validator;
        $this->searchIndexer = $searchIndexer;
    }

    #[Route('/', name: 'get', methods: ['GET'])]
    public function getTasks(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $tasks = $this->taskRepository->findByUser($user);

        return new JsonResponse(array_map([$this, 'serializeTask'], $tasks), Response::HTTP_OK);
    }

    #[Route('/today', name: 'get_today', methods: ['GET'])]
    public function getTasksToday(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $tasks = $this->taskRepository->findTodayByUser($user);

        return new JsonResponse(array_map([$this, 'serializeTask'], $tasks), Response::HTTP_OK);
    }

    #[Route('/overdue', name: 'get_overdue', methods: ['GET'])]
    public function getOverdueTasks(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $tasks = $this->taskRepository->findOverdueByUser($user);

        return new JsonResponse(array_map([$this, 'serializeTask'], $tasks), Response::HTTP_OK);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function createTask(Request $request): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        $dto = CreateTaskDTO::fromArray($data);

        $errors = $this->validator->validate($dto);

        if (\count($errors) > 0) {
            $messages = [];
            foreach ($errors as $error) {
                $messages[$error->getPropertyPath()] = $error->getMessage();
            }

            return new JsonResponse(['errors' => $messages], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $task = new Task();
        $task->setTitle($dto->title);
        $task->setDescription($dto->description ? $this->encryptionService->encrypt($dto->description) : '');
        $task->setStatus($dto->status ?? TaskStatus::TODO);
        $task->setPriority($dto->priority ?? TaskPriority::LOW);
        $task->setDueDate($dto->dueDate);
        $task->setAuthor($user);

        $this->entityManager->persist($task);
        $this->entityManager->flush();

        $this->searchIndexer->indexTask($task);

        return new JsonResponse($this->serializeTask($task), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'get_one', methods: ['GET'])]
    public function getTask(int $id): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $task = $this->entityManager->getRepository(Task::class)->findOneBy(['id' => $id]);

        if (!$task) {
            return new JsonResponse(['error' => 'Task not found'], Response::HTTP_NOT_FOUND);
        }

        if ($task->getAuthor()?->getId() !== $user->getId()) {
            return new JsonResponse(['error' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        return new JsonResponse($this->serializeTask($task), Response::HTTP_OK);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function updateTask(Request $request, int $id): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $task = $this->entityManager->getRepository(Task::class)->findOneBy(['id' => $id]);

        if (!$task) {
            return new JsonResponse(['error' => 'Task not found'], Response::HTTP_NOT_FOUND);
        }

        if ($task->getAuthor()?->getId() !== $user->getId()) {
            return new JsonResponse(['error' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        $dto = UpdateTaskDTO::fromArray($data);

        $errors = $this->validator->validate($dto);

        if (\count($errors) > 0) {
            $messages = [];
            foreach ($errors as $error) {
                $messages[$error->getPropertyPath()] = $error->getMessage();
            }

            return new JsonResponse(['errors' => $messages], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($dto->title) {
            $task->setTitle($dto->title);
        }

        if ($dto->description) {
            $task->setDescription($this->encryptionService->encrypt($dto->description));
        }

        if ($dto->status) {
            $task->setStatus($dto->status);
        }

        if ($dto->priority) {
            $task->setPriority($dto->priority);
        }

        if ($dto->dueDate) {
            $task->setDueDate($dto->dueDate);
        }

        $this->entityManager->persist($task);
        $this->entityManager->flush();

        $this->searchIndexer->indexTask($task);

        return new JsonResponse($this->serializeTask($task), Response::HTTP_OK);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function deleteTask(int $id): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $task = $this->entityManager->getRepository(Task::class)->findOneBy(['id' => $id]);

        if (!$task) {
            return new JsonResponse(['error' => 'Task not found'], Response::HTTP_NOT_FOUND);
        }

        if ($task->getAuthor()?->getId() !== $user->getId()) {
            return new JsonResponse(['error' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        $this->searchIndexer->deleteTask($task);

        $this->entityManager->remove($task);
        $this->entityManager->flush();

        return new JsonResponse(['message' => 'Task deleted'], Response::HTTP_NO_CONTENT);
    }

    #[Route('/quickAdd', name: 'quick_add', methods: ['POST'])]
    public function quickAdd(Request $request): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        $dto = CreateTaskDTO::fromArray($data);

        $errors = $this->validator->validate($dto);

        if (\count($errors) > 0) {
            $messages = [];
            foreach ($errors as $error) {
                $messages[$error->getPropertyPath()] = $error->getMessage();
            }

            return new JsonResponse(['errors' => $messages], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $task = new Task();
        $task->setTitle($dto->title);
        $task->setStatus(TaskStatus::TODO);
        $task->setAuthor($user);
        $task->setPriority(TaskPriority::MEDIUM);
        $task->setDueDate(new \DateTimeImmutable('today'));

        $this->entityManager->persist($task);
        $this->entityManager->flush();

        $this->searchIndexer->indexTask($task);

        return new JsonResponse($this->serializeTask($task), Response::HTTP_CREATED);
    }

    /**
     * Serializes a Task entity into an array.
     *
     * @param Task $task the task entity to be serialized
     *
     * @return array<string, mixed> the serialized representation of the task, including its ID, title,
     *                              decrypted description, status, priority, and formatted date fields
     */
    private function serializeTask(Task $task): array
    {
        return [
            'id' => $task->getId(),
            'title' => $task->getTitle(),
            'description' => $task->getDescription() ? $this->encryptionService->decrypt($task->getDescription()) : '',
            'status' => $task->getStatus()->value,
            'priority' => $task->getPriority()->value,
            'due_date' => $task->getDueDate()?->format('Y-m-d H:i:s'),
            'created_at' => $task->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updated_at' => $task->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ];
    }
}
