<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CalendarEvent;
use App\Entity\ConnectedAccount;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CalendarEvent>
 */
class CalendarEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CalendarEvent::class);
    }

    /**
     * Finds calendar events for a user.
     *
     * @param User $user the user for whom to find events
     *
     * @return CalendarEvent[] the calendar events for the user
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.userOwner = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();
    }

    /**
     * Finds calendar events for a user within a specific date range.
     *
     * @param User               $user  the user for whom to find events
     * @param \DateTimeInterface $start the start date of the range
     * @param \DateTimeInterface $end   the end date of the range
     *
     * @return CalendarEvent[] the calendar events for the user within the specified date range
     */
    public function findByUserBetweenDates(User $user, \DateTimeInterface $start, \DateTimeInterface $end): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.userOwner = :user')
            ->andWhere('e.startsAt BETWEEN :start AND :end')
            ->setParameter('user', $user)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getResult();
    }

    public function findOneByAccountAndExternalUid(ConnectedAccount $account, string $externalUid): ?CalendarEvent
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.connectedAccount = :account')
            ->andWhere('e.externalUid = :uid')
            ->setParameter('account', $account)
            ->setParameter('uid', $externalUid)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOneByAccountAndExternalHref(ConnectedAccount $account, string $externalHref): ?CalendarEvent
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.connectedAccount = :account')
            ->andWhere('e.externalHref = :href')
            ->setParameter('account', $account)
            ->setParameter('href', $externalHref)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
