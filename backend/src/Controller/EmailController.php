<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\EmailMessage;
use App\Entity\User;
use App\Enum\EmailCategory;
use App\Repository\EmailMessageRepository;
use App\Service\EncryptionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The triaged inbox. Read-only towards Gmail by construction: there is no write
 * path to Google anywhere in this controller, and dismissing only ever touches a
 * local column.
 */
#[Route('/api/email', name: 'api_email_')]
class EmailController extends AbstractController
{
    private const MAX_LIMIT = 100;

    public function __construct(
        private readonly EmailMessageRepository $emailMessageRepository,
        private readonly EncryptionService $encryptionService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $categories = $this->parse_categories($request->query->getString('category'));
        } catch (\ValueError) {
            return new JsonResponse(['error' => 'Unknown category.'], Response::HTTP_BAD_REQUEST);
        }

        $limit = max(1, min(self::MAX_LIMIT, $request->query->getInt('limit', 50)));
        $before = $this->parse_cursor($request->query->getString('cursor'));

        $messages = $this->emailMessageRepository->findForUser(
            $user,
            $categories,
            $request->query->getBoolean('include_dismissed'),
            $before,
            $limit,
        );

        $items = array_map([$this, 'serialize_message'], $messages);
        $last = end($messages);

        return new JsonResponse([
            'items' => $items,
            // Keyset cursor. Offset pagination would drift as the worker inserts
            // newer mail above an open page.
            'next_cursor' => \count($messages) === $limit && $last instanceof EmailMessage
                ? $last->getReceivedAt()?->format(\DateTimeInterface::ATOM)
                : null,
        ], Response::HTTP_OK);
    }

    #[Route('/stats', name: 'stats', methods: ['GET'])]
    public function stats(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $counts = $this->emailMessageRepository->countByCategory($user);
        $noise = 0;

        foreach ($counts as $value => $count) {
            if (EmailCategory::from($value)->is_noise()) {
                $noise += $count;
            }
        }

        return new JsonResponse([
            'by_category' => $counts,
            'needs_you' => $counts[EmailCategory::PRIORITY->value] ?? 0,
            'noise' => $noise,
        ], Response::HTTP_OK);
    }

    /**
     * Local-only. Clears the row out of Pryvora's view; Gmail never hears about it.
     */
    #[Route('/{id}/dismiss', name: 'dismiss', methods: ['POST'])]
    public function dismiss(int $id): JsonResponse
    {
        $message = $this->find_owned_message($id);

        if (!$message instanceof EmailMessage) {
            return new JsonResponse(['error' => 'Not found'], Response::HTTP_NOT_FOUND);
        }

        $message->setDismissedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        return new JsonResponse($this->serialize_message($message), Response::HTTP_OK);
    }

    #[Route('/{id}/dismiss', name: 'undismiss', methods: ['DELETE'])]
    public function undismiss(int $id): JsonResponse
    {
        $message = $this->find_owned_message($id);

        if (!$message instanceof EmailMessage) {
            return new JsonResponse(['error' => 'Not found'], Response::HTTP_NOT_FOUND);
        }

        $message->setDismissedAt(null);
        $this->entityManager->flush();

        return new JsonResponse($this->serialize_message($message), Response::HTTP_OK);
    }

    private function find_owned_message(int $id): ?EmailMessage
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return null;
        }

        $message = $this->emailMessageRepository->find($id);

        if (!$message || $message->getUserOwner() !== $user) {
            return null;
        }

        return $message;
    }

    /**
     * 'noise' is every category except PRIORITY — the split the Inbox tabs are
     * built on. A single category name filters to just that one.
     *
     * @return list<EmailCategory>
     */
    private function parse_categories(string $value): array
    {
        if ('' === $value) {
            return [];
        }

        if ('noise' === $value) {
            return array_values(array_filter(
                EmailCategory::cases(),
                static fn (EmailCategory $case): bool => $case->is_noise(),
            ));
        }

        return [EmailCategory::from($value)];
    }

    private function parse_cursor(string $value): ?\DateTimeImmutable
    {
        if ('' === $value) {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize_message(EmailMessage $message): array
    {
        return [
            'id' => $message->getId(),
            'gmail_message_id' => $message->getGmailMessageId(),
            'subject' => $this->decrypt($message->getSubject()),
            'snippet' => $this->decrypt($message->getSnippet()),
            'from_name' => $this->decrypt($message->getFromName()),
            'from_email' => $message->getFromEmail(),
            'received_at' => $message->getReceivedAt()?->format(\DateTimeInterface::ATOM),
            'category' => $message->getCategory()->value,
            'category_reason' => $message->getCategoryReason() ?? [],
            'score' => $message->getScore(),
            'is_unread' => $message->isUnread(),
            'is_starred' => $message->isStarred(),
            'has_list_unsubscribe' => $message->hasListUnsubscribe(),
            'unsubscribe_url' => $this->decrypt($message->getUnsubscribeUrl()),
            'dismissed_at' => $message->getDismissedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    private function decrypt(?string $value): ?string
    {
        return null !== $value && '' !== $value ? $this->encryptionService->decrypt($value) : null;
    }
}
