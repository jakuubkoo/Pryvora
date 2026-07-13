<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SenderRule;
use App\Entity\User;
use App\Enum\SenderVerdict;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SenderRule>
 */
class SenderRuleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SenderRule::class);
    }

    /**
     * The user's entire rule set, keyed by lowercased sender address.
     *
     * Loaded whole rather than queried per message: a personal block list is tens
     * of rows, and a sync that classifies 100 messages would otherwise do 100
     * extra SELECTs to learn nothing.
     *
     * @return array<string, SenderVerdict>
     */
    public function findMapForUser(User $user): array
    {
        $rules = $this->createQueryBuilder('r')
            ->andWhere('r.userOwner = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();

        $map = [];

        foreach ($rules as $rule) {
            $map[(string) $rule->getSenderEmail()] = $rule->getVerdict();
        }

        return $map;
    }

    /**
     * @return SenderRule[]
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.userOwner = :user')
            ->setParameter('user', $user)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByUserAndSender(User $user, string $sender_email): ?SenderRule
    {
        return $this->findOneBy([
            'userOwner' => $user,
            'senderEmail' => mb_strtolower(trim($sender_email)),
        ]);
    }
}
