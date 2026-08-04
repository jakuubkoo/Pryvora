<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\CreateEventDTO;
use App\DTO\UpdateEventDTO;
use App\Entity\CalendarEvent;
use App\Entity\User;
use App\Integration\CalendarWriteBack;
use App\Repository\CalendarEventRepository;
use App\Service\EncryptionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/event', name: 'api_event_')]
class EventController extends AbstractController
{
    public function __construct(
        private readonly CalendarEventRepository $calendarEventRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
        private readonly EncryptionService $encryptionService,
        private readonly CalendarWriteBack $calendarWriteBack,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $from = $request->query->get('from');
        $to = $request->query->get('to');

        if ($from && $to) {
            $fromDt = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $from);
            $toDt = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $to);

            if (!$fromDt || !$toDt) {
                return new JsonResponse(['error' => 'Invalid date format'], Response::HTTP_BAD_REQUEST);
            }

            $events = $this->calendarEventRepository->findByUserBetweenDates($user, $fromDt, $toDt);
        } else {
            $events = $this->calendarEventRepository->findByUser($user);
        }

        return new JsonResponse(array_map([$this, 'serializeEvent'], $events), Response::HTTP_OK);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function createEvent(Request $request): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        $dto = CreateEventDTO::fromArray($data);

        $errors = $this->validator->validate($dto);

        if (\count($errors) > 0) {
            $messages = [];
            foreach ($errors as $error) {
                $messages[$error->getPropertyPath()] = $error->getMessage();
            }

            return new JsonResponse(['errors' => $messages], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $startsAt = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $dto->startsAt);
        $endsAt = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $dto->endsAt);

        if (!$startsAt || !$endsAt) {
            return new JsonResponse(['error' => 'Invalid date format'], Response::HTTP_BAD_REQUEST);
        }

        if ($startsAt >= $endsAt) {
            return new JsonResponse(['error' => 'Starts at must be before ends at'], Response::HTTP_BAD_REQUEST);
        }

        $event = new CalendarEvent();
        $event->setUserOwner($user);
        $event->setTitle($dto->title);
        $event->setDescription($dto->description ? $this->encryptionService->encrypt($dto->description) : '');
        $event->setLocation($dto->location);
        $event->setStartsAt($startsAt);
        $event->setEndsAt($endsAt);
        $event->setAllDay($dto->allDay ?? false);
        $event->setCreatedAt(new \DateTimeImmutable());

        if ($dto->reminderAt) {
            $reminderAt = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $dto->reminderAt);
            if (!$reminderAt) {
                return new JsonResponse(['error' => 'Invalid reminder_at date format'], Response::HTTP_BAD_REQUEST);
            }
            $event->setReminderAt($reminderAt);
        }

        $this->calendarWriteBack->link_new_event($event);

        $this->entityManager->persist($event);
        $this->entityManager->flush();

        $this->calendarWriteBack->queue_upsert($event);

        return new JsonResponse($this->serializeEvent($event), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'update', methods: ['PATCH'])]
    public function updateEvent(int $id, Request $request): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $event = $this->calendarEventRepository->findOneBy(['id' => $id]);

        if (!$event) {
            return new JsonResponse(['error' => 'Event not found'], Response::HTTP_NOT_FOUND);
        }

        if ($event->getUserOwner()?->getId() !== $user->getId()) {
            return new JsonResponse(['error' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        $dto = UpdateEventDTO::fromArray($data);

        $errors = $this->validator->validate($dto);

        if (\count($errors) > 0) {
            $messages = [];
            foreach ($errors as $error) {
                $messages[$error->getPropertyPath()] = $error->getMessage();
            }

            return new JsonResponse(['errors' => $messages], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($dto->title) {
            $event->setTitle($dto->title);
        }

        if (\array_key_exists('description', $data)) {
            $event->setDescription($dto->description ? $this->encryptionService->encrypt($dto->description) : '');
        }

        if (\array_key_exists('location', $data)) {
            $event->setLocation($dto->location);
        }

        $startsAt = $dto->startsAt ? \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $dto->startsAt) : null;
        $endsAt = $dto->endsAt ? \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $dto->endsAt) : null;

        if ($startsAt >= $endsAt) {
            return new JsonResponse(['error' => 'Starts at must be before ends at'], Response::HTTP_BAD_REQUEST);
        }

        if ($startsAt) {
            $event->setStartsAt($startsAt);
        }

        if ($endsAt) {
            $event->setEndsAt($endsAt);
        }

        if (\array_key_exists('allDay', $data)) {
            $event->setAllDay($dto->allDay ?? false);
        }

        if (\array_key_exists('reminderAt', $data)) {
            if ($dto->reminderAt) {
                $reminderAt = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $dto->reminderAt);
                if (!$reminderAt) {
                    return new JsonResponse(['error' => 'Invalid reminder_at date format'], Response::HTTP_BAD_REQUEST);
                }
                $event->setReminderAt($reminderAt);
            } else {
                $event->setReminderAt(null);
            }
        }

        $this->entityManager->persist($event);
        $this->entityManager->flush();

        $this->calendarWriteBack->queue_upsert($event);

        return new JsonResponse($this->serializeEvent($event), Response::HTTP_OK);
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function deleteEvent(int $id): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $event = $this->calendarEventRepository->findOneBy(['id' => $id]);

        if (!$event) {
            return new JsonResponse(['error' => 'Event not found'], Response::HTTP_NOT_FOUND);
        }

        if ($event->getUserOwner()?->getId() !== $user->getId()) {
            return new JsonResponse(['error' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        // Captured before the row goes, since the handler needs the href and
        // etag that are about to be deleted.
        $remoteDelete = $this->calendarWriteBack->plan_delete($event);

        $this->entityManager->remove($event);
        $this->entityManager->flush();

        $this->calendarWriteBack->dispatch_delete($remoteDelete);

        return new JsonResponse(['message' => 'Event deleted'], Response::HTTP_NO_CONTENT);
    }

    /**
     * Serializes an event entity into an array representation.
     *
     * @param CalendarEvent $event the event entity to serialize
     *
     * @return array<string, mixed> the serialized representation of the event
     */
    private function serializeEvent(CalendarEvent $event): array
    {
        return [
            'id' => $event->getId(),
            'title' => $event->getTitle(),
            'description' => $event->getDescription() ? $this->encryptionService->decrypt($event->getDescription()) : '',
            'location' => $event->getLocation(),
            'starts_at' => $event->getStartsAt()?->format(\DateTimeInterface::ATOM),
            'ends_at' => $event->getEndsAt()?->format(\DateTimeInterface::ATOM),
            'all_day' => $event->isAllDay(),
            'reminder_at' => $event->getReminderAt()?->format(\DateTimeInterface::ATOM),
            'created_at' => $event->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            'updated_at' => $event->getUpdatedAt()?->format(\DateTimeInterface::ATOM),
            // Which calendar this came from. Null provider means the event was
            // created here and lives nowhere else. The account id is exposed so
            // the UI can tell two accounts of the same provider apart.
            'source' => [
                'provider' => $event->getConnectedAccount()?->getProvider(),
                'account_id' => $event->getConnectedAccount()?->getId(),
            ],
        ];
    }
}
