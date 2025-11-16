<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\FileRepository;
use App\Repository\UserRepository;
use App\Service\ThumbnailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin')]
class AdminController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private FileRepository $fileRepository,
        private ThumbnailService $thumbnailService,
    ) {
    }

    #[Route('/login', name: 'app_admin_login', methods: ['POST'])]
    public function login(): JsonResponse
    {
        // Géré par le firewall security
        return new JsonResponse([]);
    }

    #[Route('/dashboard', name: 'app_admin_dashboard')]
    public function dashboard(): Response
    {
        // Pas de vérification d'authentification ici
        // L'authentification sera vérifiée côté JavaScript
        $users = $this->userRepository->findAll();

        $usersData = array_map(function (User $user) {
            return [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'createdAt' => $user->getCreatedAt(),
                'usedSpace' => $user->getUsedSpace(),
                'quota' => $user->getQuota(),
                'active' => $user->isActive(),
                'filesCount' => $user->getFiles()->count(),
                'roles' => $user->getRoles(),
            ];
        }, $users);

        return $this->render('admin/dashboard.html.twig', [
            'users' => $usersData,
        ]);
    }

    #[Route('/users', name: 'app_admin_users_list', methods: ['GET'])]
    public function listUsers(): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $users = $this->userRepository->findAll();

        $usersData = array_map(function (User $user) {
            return [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'createdAt' => $user->getCreatedAt()->format('Y-m-d H:i:s'),
                'usedSpace' => $user->getUsedSpace(),
                'quota' => $user->getQuota(),
                'active' => $user->isActive(),
                'filesCount' => $user->getFiles()->count(),
                'roles' => $user->getRoles(),
            ];
        }, $users);

        return new JsonResponse([
            'users' => $usersData,
            'count' => count($usersData),
        ]);
    }

    #[Route('/users/{id}/quota', name: 'app_admin_update_quota', methods: ['PATCH'])]
    public function updateQuota(int $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $user = $this->userRepository->find($id);

        if (!$user) {
            return new JsonResponse([
                'error' => 'User not found'
            ], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['quota'])) {
            return new JsonResponse([
                'error' => 'Quota is required'
            ], Response::HTTP_BAD_REQUEST);
        }

        $quotaString = $data['quota'];

        // Convertir le quota (ex: "3G", "500M", "1024K") en octets
        $quotaBytes = $this->parseQuotaString($quotaString);

        if ($quotaBytes === false) {
            return new JsonResponse([
                'error' => 'Invalid quota format. Use format like "2G", "500M", "1024K"'
            ], Response::HTTP_BAD_REQUEST);
        }

        $user->setQuota((string)$quotaBytes);
        $this->entityManager->flush();

        return new JsonResponse([
            'message' => 'Quota updated successfully',
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'quota' => $user->getQuota(),
            ]
        ]);
    }

    #[Route('/users/{id}/suspend', name: 'app_admin_suspend_user', methods: ['PATCH'])]
    public function suspendUser(int $id): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $user = $this->userRepository->find($id);

        if (!$user) {
            return new JsonResponse([
                'error' => 'User not found'
            ], Response::HTTP_NOT_FOUND);
        }

        // Ne pas suspendre un admin
        if (in_array('ROLE_ADMIN', $user->getRoles())) {
            return new JsonResponse([
                'error' => 'Cannot suspend an admin user'
            ], Response::HTTP_FORBIDDEN);
        }

        $user->setActive(false);
        $this->entityManager->flush();

        return new JsonResponse([
            'message' => 'User suspended successfully',
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'active' => $user->isActive(),
            ]
        ]);
    }

    #[Route('/users/{id}/activate', name: 'app_admin_activate_user', methods: ['PATCH'])]
    public function activateUser(int $id): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $user = $this->userRepository->find($id);

        if (!$user) {
            return new JsonResponse([
                'error' => 'User not found'
            ], Response::HTTP_NOT_FOUND);
        }

        $user->setActive(true);
        $this->entityManager->flush();

        return new JsonResponse([
            'message' => 'User activated successfully',
            'user' => [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'active' => $user->isActive(),
            ]
        ]);
    }

    private function parseQuotaString(string $quotaString): int|false
    {
        // Format accepté: "2G", "500M", "1024K", ou juste un nombre pour les octets
        if (is_numeric($quotaString)) {
            return (int)$quotaString;
        }

        $units = [
            'K' => 1024,
            'M' => 1024 * 1024,
            'G' => 1024 * 1024 * 1024,
            'T' => 1024 * 1024 * 1024 * 1024,
        ];

        $unit = strtoupper(substr($quotaString, -1));
        $value = substr($quotaString, 0, -1);

        if (!is_numeric($value) || !isset($units[$unit])) {
            return false;
        }

        return (int)((float)$value * $units[$unit]);
    }

    #[Route('/regenerate-thumbnails', name: 'app_admin_regenerate_thumbnails', methods: ['POST'])]
    public function regenerateThumbnails(): JsonResponse
    {
        $files = $this->fileRepository->findAll();
        $generated = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($files as $file) {
            // Ignorer les dossiers
            if ($file->isFolder()) {
                continue;
            }

            // Traiter uniquement les images
            if ($file->getFileType() !== 'image') {
                continue;
            }

            // Si la miniature existe déjà et que le fichier existe, on la garde
            if ($file->getThumbnail() && $this->thumbnailService->thumbnailExists($file->getThumbnail())) {
                $skipped++;
                continue;
            }

            // Générer la miniature
            $thumbnailPath = $this->thumbnailService->generateThumbnail($file->getStoredName());
            if ($thumbnailPath) {
                $file->setThumbnail($thumbnailPath);
                $this->entityManager->persist($file);
                $generated++;
            } else {
                $errors++;
            }
        }

        // Sauvegarder les modifications
        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'generated' => $generated,
            'skipped' => $skipped,
            'errors' => $errors,
            'message' => "✅ $generated miniature(s) générée(s), $skipped déjà existante(s), $errors erreur(s)"
        ]);
    }
}
