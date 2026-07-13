<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\IntegrationStatus;
use App\Repository\ConnectedAccountRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ConnectedAccountRepository::class)]
#[ORM\Table(name: 'connected_account')]
#[ORM\UniqueConstraint(name: 'uniq_connected_account_user_provider_external', columns: ['user_owner_id', 'provider', 'external_account_id'])]
class ConnectedAccount
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $userOwner = null;

    #[ORM\Column(length: 64)]
    private ?string $provider = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $externalAccountId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $displayName = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $credentials = null;

    /** @var list<string>|null */
    #[ORM\Column(nullable: true)]
    private ?array $scopes = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column(length: 32, enumType: IntegrationStatus::class)]
    private IntegrationStatus $status = IntegrationStatus::CONNECTED;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $lastError = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(nullable: true)]
    private ?array $syncState = null;

    /**
     * Which remote calendar new Pryvora events are written to. A real column
     * rather than a key inside syncState, which sync() rewrites wholesale and
     * would clobber a choice made while a sync was running.
     */
    #[ORM\Column(length: 512, nullable: true)]
    private ?string $targetCalendarHref = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastSyncedAt = null;

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

    public function getProvider(): ?string
    {
        return $this->provider;
    }

    public function setProvider(string $provider): static
    {
        $this->provider = $provider;

        return $this;
    }

    public function getExternalAccountId(): ?string
    {
        return $this->externalAccountId;
    }

    public function setExternalAccountId(?string $externalAccountId): static
    {
        $this->externalAccountId = $externalAccountId;

        return $this;
    }

    public function getDisplayName(): ?string
    {
        return $this->displayName;
    }

    public function setDisplayName(?string $displayName): static
    {
        $this->displayName = $displayName;

        return $this;
    }

    public function getCredentials(): ?string
    {
        return $this->credentials;
    }

    public function setCredentials(string $credentials): static
    {
        $this->credentials = $credentials;

        return $this;
    }

    /**
     * @return list<string>|null
     */
    public function getScopes(): ?array
    {
        return $this->scopes;
    }

    /**
     * @param list<string>|null $scopes
     */
    public function setScopes(?array $scopes): static
    {
        $this->scopes = $scopes;

        return $this;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTimeImmutable $expiresAt): static
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    public function getStatus(): IntegrationStatus
    {
        return $this->status;
    }

    public function setStatus(IntegrationStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function setLastError(?string $lastError): static
    {
        $this->lastError = $lastError;

        return $this;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getSyncState(): ?array
    {
        return $this->syncState;
    }

    /**
     * @param array<string, mixed>|null $syncState
     */
    public function setSyncState(?array $syncState): static
    {
        $this->syncState = $syncState;

        return $this;
    }

    public function getTargetCalendarHref(): ?string
    {
        return $this->targetCalendarHref;
    }

    public function setTargetCalendarHref(?string $targetCalendarHref): static
    {
        $this->targetCalendarHref = $targetCalendarHref;

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
