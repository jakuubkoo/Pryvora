<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\SenderVerdict;
use App\Repository\SenderRuleRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * A standing decision about a sender: always show them, or never show them.
 *
 * Attached to the address rather than to a message on purpose. Marking a single
 * email would mean marking the same newsletter again every week; marking the
 * sender means next week's arrives already filed.
 *
 * Nothing here is written back to Gmail. A blocked sender is hidden from Pryvora's
 * view and their mail sits untouched in the real inbox.
 */
#[ORM\Entity(repositoryClass: SenderRuleRepository::class)]
#[ORM\Table(name: 'sender_rule')]
#[ORM\UniqueConstraint(name: 'uniq_sender_rule_user_sender', columns: ['user_owner_id', 'sender_email'])]
class SenderRule
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $userOwner = null;

    /**
     * Plaintext and lowercased, matching EmailMessage::fromEmail — the rule has to
     * be joinable against it, and that column is already plaintext.
     */
    #[ORM\Column(length: 320)]
    private ?string $senderEmail = null;

    #[ORM\Column(length: 32, enumType: SenderVerdict::class)]
    private SenderVerdict $verdict = SenderVerdict::BLOCKED;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

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

    public function getSenderEmail(): ?string
    {
        return $this->senderEmail;
    }

    public function setSenderEmail(string $senderEmail): static
    {
        $this->senderEmail = mb_strtolower(trim($senderEmail));

        return $this;
    }

    public function getVerdict(): SenderVerdict
    {
        return $this->verdict;
    }

    public function setVerdict(SenderVerdict $verdict): static
    {
        $this->verdict = $verdict;

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
}
