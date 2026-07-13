<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\EmailMessage;
use App\Entity\SenderRule;
use App\Entity\User;
use App\Enum\EmailCategory;
use App\Enum\SenderVerdict;
use App\Repository\EmailMessageRepository;
use App\Repository\SenderRuleRepository;
use App\Service\EncryptionService;
use App\Triage\EffectiveCategory;
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
        private readonly SenderRuleRepository $senderRuleRepository,
        private readonly EffectiveCategory $effectiveCategory,
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
        $buckets = ['needs_you' => 0, 'noise' => 0, 'blocked' => 0];

        foreach ($counts as $value => $count) {
            $buckets[EmailCategory::from($value)->bucket()] += $count;
        }

        return new JsonResponse([
            'by_category' => $counts,
            'needs_you' => $buckets['needs_you'],
            'noise' => $buckets['noise'],
            'blocked' => $buckets['blocked'],
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

    #[Route('/rules', name: 'rules', methods: ['GET'])]
    public function rules(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        return new JsonResponse(
            array_map([$this, 'serialize_rule'], $this->senderRuleRepository->findByUser($user)),
            Response::HTTP_OK,
        );
    }

    /**
     * Sets a standing decision about a sender, and applies it to the mail already
     * in the database.
     *
     * The retroactive sweep is the whole feature. Blocking a sender and still
     * seeing forty of their old emails in Noise would read as the button not
     * working — the user asked to never see this sender, not to never see them
     * from now on.
     */
    #[Route('/rules', name: 'rule_set', methods: ['PUT'])]
    public function set_rule(Request $request): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $payload = json_decode($request->getContent(), true);

        if (!\is_array($payload) || !isset($payload['sender_email'], $payload['verdict'])) {
            return new JsonResponse(['error' => 'A sender_email and verdict are required.'], Response::HTTP_BAD_REQUEST);
        }

        $sender = mb_strtolower(trim((string) $payload['sender_email']));

        if ('' === $sender) {
            return new JsonResponse(['error' => 'A sender_email is required.'], Response::HTTP_BAD_REQUEST);
        }

        $verdict = SenderVerdict::tryFrom((string) $payload['verdict']);

        if (!$verdict instanceof SenderVerdict) {
            return new JsonResponse(['error' => 'Unknown verdict.'], Response::HTTP_BAD_REQUEST);
        }

        $rule = $this->senderRuleRepository->findOneByUserAndSender($user, $sender) ?? new SenderRule();

        $rule->setUserOwner($user);
        $rule->setSenderEmail($sender);
        $rule->setVerdict($verdict);

        if (!$rule->getCreatedAt()) {
            $rule->setCreatedAt(new \DateTimeImmutable());
        }

        $this->entityManager->persist($rule);
        $this->entityManager->flush();

        $moved = $this->apply_rule_to_stored_mail($user, $sender, $verdict);

        return new JsonResponse(
            ['rule' => $this->serialize_rule($rule), 'messages_moved' => $moved],
            Response::HTTP_OK,
        );
    }

    /**
     * Drops the rule and puts the sender's mail back where the classifier had it —
     * a newsletter returns to Noise, not to Priority. That is what autoCategory is
     * for.
     */
    #[Route('/rules/{id}', name: 'rule_delete', methods: ['DELETE'])]
    public function delete_rule(int $id): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $rule = $this->senderRuleRepository->find($id);

        if (!$rule instanceof SenderRule || $rule->getUserOwner() !== $user) {
            return new JsonResponse(['error' => 'Not found'], Response::HTTP_NOT_FOUND);
        }

        $sender = (string) $rule->getSenderEmail();

        $this->entityManager->remove($rule);
        $this->entityManager->flush();

        $restored = $this->emailMessageRepository->restoreAutoCategory($user, $sender);

        return new JsonResponse(['messages_restored' => $restored], Response::HTTP_OK);
    }

    /**
     * BLOCKED is unconditional. ALWAYS_SHOW goes through EffectiveCategory, which
     * refuses to lift mail out of SPAM — see the note there about spoofed senders.
     */
    private function apply_rule_to_stored_mail(User $user, string $sender, SenderVerdict $verdict): int
    {
        if (SenderVerdict::BLOCKED === $verdict) {
            return $this->emailMessageRepository->setCategoryForSender($user, $sender, EmailCategory::BLOCKED);
        }

        $moved = 0;

        foreach ($this->emailMessageRepository->findBySender($user, $sender) as $message) {
            $effective = $this->effectiveCategory->resolve($message->getAutoCategory(), $verdict);

            if ($effective !== $message->getCategory()) {
                $message->setCategory($effective);
                ++$moved;
            }
        }

        $this->entityManager->flush();

        return $moved;
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize_rule(SenderRule $rule): array
    {
        return [
            'id' => $rule->getId(),
            'sender_email' => $rule->getSenderEmail(),
            'verdict' => $rule->getVerdict()->value,
            'created_at' => $rule->getCreatedAt()?->format(\DateTimeInterface::ATOM),
        ];
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
     * The three Inbox tabs, plus a bare category name for the sub-chips under Noise.
     *
     * 'noise' used to mean "everything except PRIORITY", which put a newsletter the
     * user reads in the same drawer as a sender they never want to hear from again.
     * It now means only the four soft categories; rejected senders live in 'blocked'.
     *
     * @return list<EmailCategory>
     */
    private function parse_categories(string $value): array
    {
        if ('' === $value) {
            return [];
        }

        if (\in_array($value, ['needs_you', 'noise', 'blocked'], true)) {
            return EmailCategory::in_bucket($value);
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
            // What the classifier thought before any rule of yours. When these two
            // differ, the UI says so rather than leaving the override mysterious.
            'auto_category' => $message->getAutoCategory()->value,
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
