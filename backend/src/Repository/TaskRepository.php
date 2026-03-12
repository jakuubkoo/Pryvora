<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Task;
use App\Entity\User;
use App\Enum\TaskStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Task>
 */
class TaskRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Task::class);
    }

    /**
     * Retrieves tasks assigned to the specified user.
     *
     * This method queries the database to find all tasks associated with the provided
     * user and returns them in descending order of their creation date.
     *
     * @param User $user the user for whom to retrieve tasks
     *
     * @return array<Task> an array of tasks sorted by their creation date in descending order
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.author = :user')
            ->setParameter('user', $user)
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Retrieves tasks assigned to the specified user that are due today.
     *
     * This method fetches tasks from the database that are associated with the given
     * user, have a due date within the current day, and are not marked as completed.
     * Tasks are sorted by their priority in descending order.
     *
     * @param User $user the user for whom to retrieve today's tasks
     *
     * @return array<Task> an array of tasks due today, sorted by their priority in descending order
     */
    public function findTodayByUser(User $user): array
    {
        $today = new \DateTime('today');
        $tomorrow = new \DateTime('tomorrow');

        return $this->createQueryBuilder('t')
            ->where('t.author = :user')
            ->andWhere('t.dueDate >= :today')
            ->andWhere('t.dueDate < :tomorrow')
            ->andWhere('t.status != :done')
            ->setParameter('user', $user)
            ->setParameter('today', $today)
            ->setParameter('tomorrow', $tomorrow)
            ->setParameter('done', TaskStatus::DONE)
            ->orderBy('t.priority', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retrieves overdue tasks assigned to the specified user.
     *
     * This method queries the database to find all tasks associated with the provided
     * user that have a due date earlier than today and are not marked as completed.
     *
     * @param User $user the user for whom to retrieve overdue tasks
     *
     * @return array<Task> an array of overdue tasks sorted by their due date in ascending order
     */
    public function findOverdueByUser(User $user): array
    {
        $today = new \DateTimeImmutable('today');

        return $this->createQueryBuilder('t')
            ->where('t.author = :user')
            ->andWhere('t.dueDate < :today')
            ->andWhere('t.status != :done')
            ->setParameter('user', $user)
            ->setParameter('today', $today)
            ->setParameter('done', TaskStatus::DONE)
            ->orderBy('t.dueDate', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
