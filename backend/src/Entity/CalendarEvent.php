<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CalendarEventRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CalendarEventRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_calendar_event_account_external_uid', columns: ['connected_account_id', 'external_uid'])]
class CalendarEvent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'calendarEvents')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $userOwner = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $location = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $startsAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $endsAt = null;

    #[ORM\Column]
    private ?bool $allDay = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $reminderAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?ConnectedAccount $connectedAccount = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $externalUid = null;

    #[ORM\Column(length: 512, nullable: true)]
    private ?string $externalHref = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $externalEtag = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastSyncedAt = null;

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

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): static
    {
        $this->location = $location;

        return $this;
    }

    public function getStartsAt(): ?\DateTimeImmutable
    {
        return $this->startsAt;
    }

    public function setStartsAt(\DateTimeImmutable $startsAt): static
    {
        $this->startsAt = $startsAt;

        return $this;
    }

    public function getEndsAt(): ?\DateTimeImmutable
    {
        return $this->endsAt;
    }

    public function setEndsAt(\DateTimeImmutable $endsAt): static
    {
        $this->endsAt = $endsAt;

        return $this;
    }

    public function isAllDay(): ?bool
    {
        return $this->allDay;
    }

    public function setAllDay(bool $allDay): static
    {
        $this->allDay = $allDay;

        return $this;
    }

    public function getReminderAt(): ?\DateTimeImmutable
    {
        return $this->reminderAt;
    }

    public function setReminderAt(?\DateTimeImmutable $reminderAt): static
    {
        $this->reminderAt = $reminderAt;

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

    public function getConnectedAccount(): ?ConnectedAccount
    {
        return $this->connectedAccount;
    }

    public function setConnectedAccount(?ConnectedAccount $connectedAccount): static
    {
        $this->connectedAccount = $connectedAccount;

        return $this;
    }

    public function getExternalUid(): ?string
    {
        return $this->externalUid;
    }

    public function setExternalUid(?string $externalUid): static
    {
        $this->externalUid = $externalUid;

        return $this;
    }

    public function getExternalHref(): ?string
    {
        return $this->externalHref;
    }

    public function setExternalHref(?string $externalHref): static
    {
        $this->externalHref = $externalHref;

        return $this;
    }

    public function getExternalEtag(): ?string
    {
        return $this->externalEtag;
    }

    public function setExternalEtag(?string $externalEtag): static
    {
        $this->externalEtag = $externalEtag;

        return $this;
    }

    public function getLastSyncedAt(): ?\DateTimeImmutable
    {
        return $this->lastSyncedAt;
    }

    public function setLastSyncedAt(?\DateTimeImmutable $lastSyncedAt): static
    {
        $this->lastSyncedAt = $lastSyncedAt;

        return $this;
    }
}
