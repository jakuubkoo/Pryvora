<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\TaskRepository;
use App\Repository\UserRepository;
use App\Service\Jarvis\JarvisContextService;
use App\Service\Jarvis\JarvisToolHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * Internal API for the Jarvis assistant service.
 *
 * Unlike every other controller here there is no logged-in user: the caller is
 * a background service holding a shared token, so `security.yaml` gives
 * `^/api/jarvis` its own firewall (the JWT authenticator would reject an opaque
 * Bearer token before this code ran) and the token is checked below instead.
 *
 * Because the token identifies the service and not a person, the account acted
 * on is fixed by configuration rather than taken from the request — a caller
 * cannot name a user, so a leaked token cannot be pointed at someone else.
 */
#[Route('/api/jarvis', name: 'api_jarvis_')]
class JarvisController extends AbstractController
{
    public function __construct(
        private readonly JarvisContextService $contextService,
        private readonly JarvisToolHandler $toolHandler,
        private readonly UserRepository $userRepository,
        private readonly TaskRepository $taskRepository,
        private readonly string $jarvisApiToken,
        private readonly string $jarvisUserEmail,
    ) {
    }

    #[Route('/tasks', name: 'create_task', methods: ['POST'])]
    public function createTask(Request $request): JsonResponse
    {
        if ($denied = $this->authorize($request)) {
            return $denied;
        }

        $user = $this->jarvisUser();

        if (!$user instanceof User) {
            return $this->misconfigured();
        }

        $data = json_decode($request->getContent(), true);

        try {
            $task = $this->toolHandler->addTask($user, \is_array($data) ? $data : []);
        } catch (ValidationFailedException $exception) {
            return $this->validationFailed($exception);
        } catch (\InvalidArgumentException $exception) {
            return new JsonResponse(['error' => $exception->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return new JsonResponse($this->contextService->serializeTask($task), Response::HTTP_CREATED);
    }

    #[Route('/tasks', name: 'get_tasks', methods: ['GET'])]
    public function getTasks(Request $request): JsonResponse
    {
        if ($denied = $this->authorize($request)) {
            return $denied;
        }

        $user = $this->jarvisUser();

        if (!$user instanceof User) {
            return $this->misconfigured();
        }

        $tasks = match ($request->query->get('filter', 'all')) {
            'today' => $this->taskRepository->findTodayByUser($user),
            'upcoming' => $this->taskRepository->findUpcomingByUser($user),
            'all' => $this->taskRepository->findByUser($user),
            default => null,
        };

        if (null === $tasks) {
            return new JsonResponse(['error' => 'Filter must be one of: today, upcoming, all'], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse(array_map([$this->contextService, 'serializeTask'], $tasks), Response::HTTP_OK);
    }

    #[Route('/notes', name: 'create_note', methods: ['POST'])]
    public function createNote(Request $request): JsonResponse
    {
        if ($denied = $this->authorize($request)) {
            return $denied;
        }

        $user = $this->jarvisUser();

        if (!$user instanceof User) {
            return $this->misconfigured();
        }

        $data = json_decode($request->getContent(), true);

        try {
            $note = $this->toolHandler->addNote($user, \is_array($data) ? $data : []);
        } catch (ValidationFailedException $exception) {
            return $this->validationFailed($exception);
        }

        return new JsonResponse([
            'id' => $note->getId(),
            'title' => $note->getTitle(),
        ], Response::HTTP_CREATED);
    }

    #[Route('/briefing', name: 'briefing', methods: ['GET'])]
    public function briefing(Request $request): JsonResponse
    {
        if ($denied = $this->authorize($request)) {
            return $denied;
        }

        $user = $this->jarvisUser();

        if (!$user instanceof User) {
            return $this->misconfigured();
        }

        return new JsonResponse($this->contextService->buildBriefing($user), Response::HTTP_OK);
    }

    /**
     * Returns a 401 response when the caller is not Jarvis, null when it is.
     *
     * An unset token means the feature was never configured, which must fail
     * closed — otherwise an empty JARVIS_API_TOKEN would authorise an empty
     * Authorization header.
     */
    private function authorize(Request $request): ?JsonResponse
    {
        $header = (string) $request->headers->get('Authorization', '');
        $unauthorized = new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);

        if ('' === $this->jarvisApiToken || !str_starts_with($header, 'Bearer ')) {
            return $unauthorized;
        }

        // Constant-time: a plain === leaks the token prefix through timing.
        return hash_equals($this->jarvisApiToken, substr($header, 7)) ? null : $unauthorized;
    }

    private function jarvisUser(): ?User
    {
        return '' === $this->jarvisUserEmail
            ? null
            : $this->userRepository->findOneBy(['email' => $this->jarvisUserEmail]);
    }

    private function misconfigured(): JsonResponse
    {
        return new JsonResponse(
            ['error' => 'Jarvis user not configured'],
            Response::HTTP_SERVICE_UNAVAILABLE,
        );
    }

    private function validationFailed(ValidationFailedException $exception): JsonResponse
    {
        $messages = [];

        foreach ($exception->getViolations() as $violation) {
            $messages[$violation->getPropertyPath()] = (string) $violation->getMessage();
        }

        return new JsonResponse(['errors' => $messages], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
