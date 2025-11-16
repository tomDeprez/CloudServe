<?php

namespace App\Repository;

use App\Entity\File;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<File>
 */
class FileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, File::class);
    }

    /**
     * @return File[]
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.user = :user')
            ->setParameter('user', $user)
            ->orderBy('f.uploadedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function calculateUserUsedSpace(User $user): int
    {
        $result = $this->createQueryBuilder('f')
            ->select('SUM(f.size) as totalSize')
            ->andWhere('f.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) ($result ?? 0);
    }

    /**
     * Trouver les fichiers dupliqués par hash pour un utilisateur
     *
     * @return File[]
     */
    public function findDuplicatesByHash(User $user, string $hash): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.user = :user')
            ->andWhere('f.hash = :hash')
            ->andWhere('f.type = :type')
            ->setParameter('user', $user)
            ->setParameter('hash', $hash)
            ->setParameter('type', 'file')
            ->orderBy('f.uploadedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Obtenir le chemin complet d'un fichier (breadcrumb)
     */
    public function getFilePath(File $file): string
    {
        $path = [];
        $current = $file;

        while ($current !== null) {
            array_unshift($path, $current->getFilename());
            $current = $current->getParent();
        }

        return '/' . implode('/', $path);
    }
}
