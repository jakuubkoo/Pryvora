<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\CreateNoteDTO;
use App\DTO\UpdateNoteDTO;
use App\Entity\Note;
use App\Entity\User;
use App\Service\EncryptionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/notes', name: 'api_notes_')]
class NotesController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private ValidatorInterface $validator;
    private EncryptionService $encryptionService;

    public function __construct(
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
        EncryptionService $encryptionService,
    ) {
        $this->entityManager = $entityManager;
        $this->validator = $validator;
        $this->encryptionService = $encryptionService;
    }

    #[Route('/', name: 'get', methods: ['GET'])]
    public function getNotes(): JsonResponse
    {
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $this->getUser()?->getUserIdentifier()]);

        if (!$user) {
            return new JsonResponse([
                'error' => 'User not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $notes = $user->getNotes();

        return new JsonResponse([
            'notes' => array_values($notes->map(function (Note $note) {
                return [
                    'id' => $note->getId(),
                    'title' => $note->getTitle(),
                    'content' => $this->encryptionService->decrypt($note->getContent() ?? ''),
                    'created_at' => $note->getCreatedAt()->format('Y-m-d H:i:s'),
                    'updated_at' => $note->getUpdatedAt()?->format('Y-m-d H:i:s'),
                ];
            })->toArray()),
        ], Response::HTTP_OK);
    }

    #[Route('/{id}', name: 'get_one', methods: ['GET'])]
    public function getNote(int $id): JsonResponse
    {
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $this->getUser()?->getUserIdentifier()]);

        if (!$user) {
            return new JsonResponse([
                'error' => 'User not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $note = $this->entityManager->getRepository(Note::class)->findOneBy(['id' => $id]);

        if (!$note) {
            return new JsonResponse([
                'error' => 'Note not found',
            ], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse([
            'id' => $note->getId(),
            'title' => $note->getTitle(),
            'content' => $this->encryptionService->decrypt($note->getContent() ?? ''),
            'created_at' => $note->getCreatedAt()->format('Y-m-d H:i:s'),
            'updated_at' => $note->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ], Response::HTTP_OK);
    }

    #[Route('/', name: 'create', methods: ['POST'])]
    public function createNote(Request $request): JsonResponse
    {
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $this->getUser()?->getUserIdentifier()]);

        if (!$user) {
            return new JsonResponse([
                'error' => 'User not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        $dto = CreateNoteDTO::fromArray($data);

        $errors = $this->validator->validate($dto);

        if (\count($errors) > 0) {
            $messages = [];
            foreach ($errors as $error) {
                $messages[$error->getPropertyPath()] = $error->getMessage();
            }

            return new JsonResponse(['errors' => $messages], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($this->entityManager->getRepository(Note::class)->findOneBy(['title' => $dto->title])) {
            return new JsonResponse([
                'error' => 'Note with this title already exists',
            ], Response::HTTP_CONFLICT);
        }

        $note = new Note();
        $note->setTitle($dto->title);
        $note->setContent($this->encryptionService->encrypt($dto->content));
        $note->setUser($user);

        $this->entityManager->persist($note);
        $this->entityManager->flush();

        return new JsonResponse([
            'message' => 'Note created',
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'update', methods: ['PUT'])]
    public function updateNote(Request $request, int $id): JsonResponse
    {
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $this->getUser()?->getUserIdentifier()]);

        if (!$user) {
            return new JsonResponse([
                'error' => 'User not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $note = $this->entityManager->getRepository(Note::class)->findOneBy(['id' => $id]);

        if (!$note) {
            return new JsonResponse([
                'error' => 'Note not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        $dto = UpdateNoteDTO::fromArray($data);

        $errors = $this->validator->validate($dto);

        if (\count($errors) > 0) {
            $messages = [];
            foreach ($errors as $error) {
                $messages[$error->getPropertyPath()] = $error->getMessage();
            }

            return new JsonResponse(['errors' => $messages], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($dto->title) {
            $note->setTitle($dto->title);
        }
        if ($dto->content) {
            $note->setContent($this->encryptionService->encrypt($dto->content));
        }

        $this->entityManager->persist($note);
        $this->entityManager->flush();

        return new JsonResponse([
            'message' => 'Note updated',
        ], Response::HTTP_OK);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function deleteNote(int $id): JsonResponse
    {
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $this->getUser()?->getUserIdentifier()]);

        if (!$user) {
            return new JsonResponse([
                'error' => 'User not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $note = $this->entityManager->getRepository(Note::class)->findOneBy(['id' => $id]);

        if (!$note) {
            return new JsonResponse([
                'error' => 'Note not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($note);
        $this->entityManager->flush();

        return new JsonResponse([
            'message' => 'Note deleted',
        ], Response::HTTP_OK);
    }
}
