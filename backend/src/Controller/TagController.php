<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\CreateTagDTO;
use App\DTO\UpdateTagDTO;
use App\Entity\Tag;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/tag', name: 'api_tag_')]
class TagController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private ValidatorInterface $validator;

    public function __construct(
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
    ) {
        $this->entityManager = $entityManager;
        $this->validator = $validator;
    }

    #[Route('/', name: 'list', methods: ['GET'])]
    public function getTags(): JsonResponse
    {
        $tags = $this->entityManager->getRepository(Tag::class)->findAll();

        return new JsonResponse($tags, Response::HTTP_OK);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function getTag(Tag $tag): JsonResponse
    {
        return new JsonResponse($tag, Response::HTTP_OK);
    }

    #[Route('/{id}/notes', name: 'get_notes', methods: ['GET'])]
    public function getTagNotes(Tag $tag): JsonResponse
    {
        return new JsonResponse($tag->getNotes(), Response::HTTP_OK);
    }

    #[Route('/', name: 'create', methods: ['POST'])]
    public function createTag(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $dto = CreateTagDTO::fromRequest($data);

        $errors = $this->validator->validate($dto);

        if (\count($errors) > 0) {
            $messages = [];
            foreach ($errors as $error) {
                $messages[$error->getPropertyPath()] = $error->getMessage();
            }

            return new JsonResponse(['errors' => $messages], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($this->entityManager->getRepository(Tag::class)->findOneBy(['name' => $dto->name, 'user' => $user])) {
            return new JsonResponse(['error' => 'Tag already exists'], Response::HTTP_CONFLICT);
        }

        $tag = new Tag();
        $tag->setName($data['name']);
        $tag->setColor($data['color']);
        $tag->setUser($this->entityManager->getRepository(User::class)->findOneBy(['email' => $user->getUserIdentifier()]));

        $this->entityManager->persist($tag);
        $this->entityManager->flush();

        return new JsonResponse($tag, Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'update', methods: ['PATCH'])]
    public function updateTag(Request $request, Tag $tag): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        if ($tag->getUser() !== $user) {
            return new JsonResponse(['error' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        $dto = UpdateTagDTO::fromArray($data);

        $errors = $this->validator->validate($dto);

        if (\count($errors) > 0) {
            $messages = [];
            foreach ($errors as $error) {
                $messages[$error->getPropertyPath()] = $error->getMessage();
            }

            return new JsonResponse(['errors' => $messages], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($dto->name && $tag->getName() !== $dto->name) {
            $tag->setName($dto->name);
        }

        if ($dto->color && $tag->getColor() !== $dto->color) {
            $tag->setColor($dto->color);
        }

        $this->entityManager->persist($tag);
        $this->entityManager->flush();

        return new JsonResponse($tag, Response::HTTP_OK);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function deleteTag(Tag $tag): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        if ($tag->getUser() !== $user) {
            return new JsonResponse(['error' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        $this->entityManager->remove($tag);
        $this->entityManager->flush();

        return new JsonResponse(['message' => 'Tag deleted'], Response::HTTP_NO_CONTENT);
    }
}
