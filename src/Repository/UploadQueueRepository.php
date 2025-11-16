<?php

namespace App\Repository;

use App\Entity\UploadQueue;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UploadQueue>
 */
class UploadQueueRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UploadQueue::class);
    }

    /**
     * Get pending uploads for a specific user
     *
     * @return UploadQueue[]
     */
    public function findPendingByUser(User $user): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.user = :user')
            ->andWhere('u.status IN (:statuses)')
            ->setParameter('user', $user)
            ->setParameter('statuses', ['pending', 'processing'])
            ->orderBy('u.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get next pending upload to process
     */
    public function findNextPending(): ?UploadQueue
    {
        return $this->createQueryBuilder('u')
            ->where('u.status = :status')
            ->setParameter('status', 'pending')
            ->orderBy('u.createdAt', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Get all pending uploads (for batch processing)
     *
     * @return UploadQueue[]
     */
    public function findAllPending(int $limit = 10): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.status = :status')
            ->setParameter('status', 'pending')
            ->orderBy('u.createdAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Clean up old completed/failed uploads
     */
    public function cleanupOld(\DateTimeImmutable $before): int
    {
        return $this->createQueryBuilder('u')
            ->delete()
            ->where('u.status IN (:statuses)')
            ->andWhere('u.processedAt < :before')
            ->setParameter('statuses', ['completed', 'failed'])
            ->setParameter('before', $before)
            ->getQuery()
            ->execute();
    }

    /**
     * Get upload statistics for a user
     */
    public function getStatsByUser(User $user): array
    {
        $qb = $this->createQueryBuilder('u')
            ->select('u.status, COUNT(u.id) as count')
            ->where('u.user = :user')
            ->setParameter('user', $user)
            ->groupBy('u.status');

        $results = $qb->getQuery()->getResult();

        $stats = [
            'pending' => 0,
            'processing' => 0,
            'completed' => 0,
            'failed' => 0,
        ];

        foreach ($results as $result) {
            $stats[$result['status']] = (int) $result['count'];
        }

        return $stats;
    }
}
