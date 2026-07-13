<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\EmailCategory;
use App\Repository\EmailMessageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A metadata-only mirror of a Gmail message. Bodies are never fetched (the
 * client asks for format=METADATA), so there is nowhere here to put one.
 *
 * Content fields — subject, snippet, fromName, unsubscribeUrl — are ciphertext.
 * GmailMessageMapper encrypts on write and EmailController decrypts on read,
 * matching how CalendarEvent::description is handled.
 */
#[ORM\Entity(repositoryClass: EmailMessageRepository::class)]
#[ORM\Table(name: 'email_message')]
#[ORM\UniqueConstraint(name: 'uniq_email_message_account_gmail_id', columns: ['connected_account_id', 'gmail_message_id'])]
#[ORM\Index(name: 'idx_email_message_user_received', columns: ['user_owner_id', 'received_at'])]
#[ORM\Index(name: 'idx_email_message_user_category_received', columns: ['user_owner_id', 'category', 'received_at'])]
#[ORM\Index(name: 'idx_email_message_user_sender', columns: ['user_owner_id', 'from_email'])]
class EmailMessage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $userOwner = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ConnectedAccount $connectedAccount = null;

    #[ORM\Column(length: 64)]
    private ?string $gmailMessageId = null;

    #[ORM\Column(length: 64)]
    private ?string $gmailThreadId = null;

    /** Ciphertext. */
    #[ORM\Column(type: Types::TEXT)]
    private ?string $subject = null;

    /** Ciphertext. */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $snippet = null;

    /** Ciphertext. */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $fromName = null;

    /**
     * Plaintext and indexed — the one queryable axis, needed for group-by-sender
     * and for readable rows when a misclassification is being debugged. Consistent
     * with Note::title and CalendarEvent::location, which are also plaintext.
     */
    #[ORM\Column(length: 320)]
    private ?string $fromEmail = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $receivedAt = null;

    #[ORM\Column(length: 32, enumType: EmailCategory::class)]
    private EmailCategory $category = EmailCategory::PRIORITY;

    /**
     * Rule ids and weights only — policy names, never matched content. That is
     * precisely why this column can be plaintext, and why the "Why?" popover
     * costs zero decrypts.
     *
     * If a rule is ever added that embeds matched content (a subject keyword,
     * say) into its reason, this invariant breaks and the column must be encrypted.
     *
     * @var list<array{rule: string, weight: int, terminal: bool}>|null
     */
    #[ORM\Column(nullable: true)]
    private ?array $categoryReason = null;

    #[ORM\Column]
    private int $score = 0;

    /** Mirror of Gmail's UNREAD label. Never written back. */
    #[ORM\Column]
    private bool $isUnread = false;

    /** Mirror of Gmail's STARRED label. Never written back. */
    #[ORM\Column]
    private bool $isStarred = false;

    /**
     * Gmail system labels only. User-created label names are user content
     * ("Divorce lawyer") and must never reach this plaintext column — the mapper
     * whitelists them.
     *
     * @var list<string>|null
     */
    #[ORM\Column(nullable: true)]
    private ?array $labelIds = null;

    #[ORM\Column]
    private bool $hasListUnsubscribe = false;

    /**
     * Ciphertext — an unsubscribe link usually embeds a per-user token, so it is
     * a secret, not a public URL.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $unsubscribeUrl = null;

    /**
     * Local-only triage state. Clears a row out of Pryvora's view while leaving
     * Gmail untouched — the read-only-compatible way to make noise go away.
     */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $dismissedAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserOwner(): ?User
    {
        return $this->userOwner;
    }

    public function setUserOwner(?User $userOwner): static
    {
        $this->userOwner = $userOwner;

        return $this;
    }

    public function getConnectedAccount(): ?ConnectedAccount
    {
        return $this->connectedAccount;
    }

    public function setConnectedAccount(?ConnectedAccount $connectedAccount): static
    {
        $this->connectedAccount = $connectedAccount;

        return $this;
    }

    public function getGmailMessageId(): ?string
    {
        return $this->gmailMessageId;
    }

    public function setGmailMessageId(string $gmailMessageId): static
    {
        $this->gmailMessageId = $gmailMessageId;

        return $this;
    }

    public function getGmailThreadId(): ?string
    {
        return $this->gmailThreadId;
    }

    public function setGmailThreadId(string $gmailThreadId): static
    {
        $this->gmailThreadId = $gmailThreadId;

        return $this;
    }

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): static
    {
        $this->subject = $subject;

        return $this;
    }

    public function getSnippet(): ?string
    {
        return $this->snippet;
    }

    public function setSnippet(?string $snippet): static
    {
        $this->snippet = $snippet;

        return $this;
    }

    public function getFromName(): ?string
    {
        return $this->fromName;
    }

    public function setFromName(?string $fromName): static
    {
        $this->fromName = $fromName;

        return $this;
    }

    public function getFromEmail(): ?string
    {
        return $this->fromEmail;
    }

    public function setFromEmail(string $fromEmail): static
    {
        $this->fromEmail = $fromEmail;

        return $this;
    }

    public function getReceivedAt(): ?\DateTimeImmutable
    {
        return $this->receivedAt;
    }

    public function setReceivedAt(\DateTimeImmutable $receivedAt): static
    {
        $this->receivedAt = $receivedAt;

        return $this;
    }

    public function getCategory(): EmailCategory
    {
        return $this->category;
    }

    public function setCategory(EmailCategory $category): static
    {
        $this->category = $category;

        return $this;
    }

    /**
     * @return list<array{rule: string, weight: int, terminal: bool}>|null
     */
    public function getCategoryReason(): ?array
    {
        return $this->categoryReason;
    }

    /**
     * @param list<array{rule: string, weight: int, terminal: bool}>|null $categoryReason
     */
    public function setCategoryReason(?array $categoryReason): static
    {
        $this->categoryReason = $categoryReason;

        return $this;
    }

    public function getScore(): int
    {
        return $this->score;
    }

    public function setScore(int $score): static
    {
        $this->score = $score;

        return $this;
    }

    public function isUnread(): bool
    {
        return $this->isUnread;
    }

    public function setIsUnread(bool $isUnread): static
    {
        $this->isUnread = $isUnread;

        return $this;
    }

    public function isStarred(): bool
    {
        return $this->isStarred;
    }

    public function setIsStarred(bool $isStarred): static
    {
        $this->isStarred = $isStarred;

        return $this;
    }

    /**
     * @return list<string>|null
     */
    public function getLabelIds(): ?array
    {
        return $this->labelIds;
    }

    /**
     * @param list<string>|null $labelIds
     */
    public function setLabelIds(?array $labelIds): static
    {
        $this->labelIds = $labelIds;

        return $this;
    }

    public function hasListUnsubscribe(): bool
    {
        return $this->hasListUnsubscribe;
    }

    public function setHasListUnsubscribe(bool $hasListUnsubscribe): static
    {
        $this->hasListUnsubscribe = $hasListUnsubscribe;

        return $this;
    }

    public function getUnsubscribeUrl(): ?string
    {
        return $this->unsubscribeUrl;
    }

    public function setUnsubscribeUrl(?string $unsubscribeUrl): static
    {
        $this->unsubscribeUrl = $unsubscribeUrl;

        return $this;
    }

    public function getDismissedAt(): ?\DateTimeImmutable
    {
        return $this->dismissedAt;
    }

    public function setDismissedAt(?\DateTimeImmutable $dismissedAt): static
    {
        $this->dismissedAt = $dismissedAt;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }
}
