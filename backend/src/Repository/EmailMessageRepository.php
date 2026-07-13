<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ConnectedAccount;
use App\Entity\EmailMessage;
use App\Entity\User;
use App\Enum\EmailCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EmailMessage>
 */
class EmailMessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EmailMessage::class);
    }

    /**
     * Keyset pagination on receivedAt. Offset pagination would drift as the
     * worker inserts newer mail underneath an open page.
     *
     * @param list<EmailCategory> $categories empty means every category
     *
     * @return EmailMessage[]
     */
    public function findForUser(
        User $user,
        array $categories = [],
        bool $include_dismissed = false,
        ?\DateTimeImmutable $before = null,
        int $limit = 50,
    ): array {
        $qb = $this->createQueryBuilder('m')
            ->andWhere('m.userOwner = :user')
            ->setParameter('user', $user)
            ->orderBy('m.receivedAt', 'DESC')
            ->addOrderBy('m.id', 'DESC')
            ->setMaxResults($limit);

        if ([] !== $categories) {
            $qb->andWhere('m.category IN (:categories)')
                ->setParameter('categories', $categories);
        }

        if (!$include_dismissed) {
            $qb->andWhere('m.dismissedAt IS NULL');
        }

        if ($before instanceof \DateTimeImmutable) {
            $qb->andWhere('m.receivedAt < :before')
                ->setParameter('before', $before);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return array<string, int> category value => count, dismissed excluded
     */
    public function countByCategory(User $user): array
    {
        $rows = $this->createQueryBuilder('m')
            ->select('m.category AS category, COUNT(m.id) AS total')
            ->andWhere('m.userOwner = :user')
            ->andWhere('m.dismissedAt IS NULL')
            ->setParameter('user', $user)
            ->groupBy('m.category')
            ->getQuery()
            ->getResult();

        $counts = [];

        foreach (EmailCategory::cases() as $case) {
            $counts[$case->value] = 0;
        }

        foreach ($rows as $row) {
            $category = $row['category'];
            $counts[$category instanceof EmailCategory ? $category->value : (string) $category] = (int) $row['total'];
        }

        return $counts;
    }

    public function findOneByAccountAndGmailId(ConnectedAccount $account, string $gmail_message_id): ?EmailMessage
    {
        return $this->findOneBy([
            'connectedAccount' => $account,
            'gmailMessageId' => $gmail_message_id,
        ]);
    }

    /**
     * Bulk load for the incremental sync path, which resolves a page of history
     * ids at once rather than one SELECT per message.
     *
     * @param list<string> $gmail_message_ids
     *
     * @return array<string, EmailMessage> keyed by gmail message id
     */
    public function findByAccountAndGmailIds(ConnectedAccount $account, array $gmail_message_ids): array
    {
        if ([] === $gmail_message_ids) {
            return [];
        }

        $messages = $this->createQueryBuilder('m')
            ->andWhere('m.connectedAccount = :account')
            ->andWhere('m.gmailMessageId IN (:ids)')
            ->setParameter('account', $account)
            ->setParameter('ids', $gmail_message_ids)
            ->getQuery()
            ->getResult();

        $by_id = [];

        foreach ($messages as $message) {
            $by_id[(string) $message->getGmailMessageId()] = $message;
        }

        return $by_id;
    }

    /**
     * @param list<string> $gmail_message_ids
     */
    public function deleteByAccountAndGmailIds(ConnectedAccount $account, array $gmail_message_ids): void
    {
        if ([] === $gmail_message_ids) {
            return;
        }

        $this->createQueryBuilder('m')
            ->delete()
            ->andWhere('m.connectedAccount = :account')
            ->andWhere('m.gmailMessageId IN (:ids)')
            ->setParameter('account', $account)
            ->setParameter('ids', $gmail_message_ids)
            ->getQuery()
            ->execute();
    }
}
