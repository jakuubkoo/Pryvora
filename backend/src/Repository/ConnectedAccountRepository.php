<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ConnectedAccount;
use App\Entity\User;
use App\Enum\IntegrationStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ConnectedAccount>
 */
class ConnectedAccountRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ConnectedAccount::class);
    }

    /**
     * @return ConnectedAccount[]
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.userOwner = :user')
            ->setParameter('user', $user)
            ->orderBy('a.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByUserAndProvider(User $user, string $provider): ?ConnectedAccount
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.userOwner = :user')
            ->andWhere('a.provider = :provider')
            ->setParameter('user', $user)
            ->setParameter('provider', $provider)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return ConnectedAccount[]
     */
    public function findConnected(): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.status = :status')
            ->setParameter('status', IntegrationStatus::CONNECTED)
            ->getQuery()
            ->getResult();
    }
}
