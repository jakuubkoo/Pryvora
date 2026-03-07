<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\NoteDTO;
use App\DTO\RegisterUserDTO;
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
    )
    {
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
            'notes' => $notes,
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

        $dto = NoteDTO::fromArray($data);

        $errors = $this->validator->validate($dto);

        if (\count($errors) > 0) {
            $messages = [];
            foreach ($errors as $error) {
                $messages[$error->getPropertyPath()] = $error->getMessage();
            }

            return new JsonResponse(['errors' => $messages], Response::HTTP_UNPROCESSABLE_ENTITY);
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
}
