<?php

namespace App\Controller;

use App\Entity\File;
use App\Repository\FileRepository;
use App\Security\FileVoter;
use App\Service\FileStorageService;
use App\Service\RawFileUploadService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/files')]
class FileController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private FileStorageService $fileStorage,
        private RawFileUploadService $rawFileUpload,
        private FileRepository $fileRepository,
    ) {
    }

    #[Route('/upload', name: 'app_file_upload', methods: ['POST'])]
    public function upload(Request $request): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse([
                'error' => 'Not authenticated'
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (!$user->isActive()) {
            return new JsonResponse([
                'error' => 'Account suspended'
            ], Response::HTTP_FORBIDDEN);
        }

        // Utiliser directement $_FILES pour éviter les problèmes avec l'objet UploadedFile de Symfony
        if (!isset($_FILES['file'])) {
            return new JsonResponse([
                'error' => 'No file uploaded'
            ], Response::HTTP_BAD_REQUEST);
        }

        $fileData = $_FILES['file'];

        // Vérifier les erreurs d'upload
        $error = $fileData['error'] ?? UPLOAD_ERR_NO_FILE;

        if ($error !== UPLOAD_ERR_OK) {
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
                UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                UPLOAD_ERR_EXTENSION => 'Upload stopped by extension',
            ];

            return new JsonResponse([
                'error' => $errorMessages[$error] ?? 'Unknown upload error',
                'error_code' => $error
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $fileSize = $fileData['size'] ?? 0;

        if ($fileSize === 0) {
            return new JsonResponse([
                'error' => 'Invalid file size'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Vérifier le quota
        if (!$user->hasAvailableSpace($fileSize)) {
            return new JsonResponse([
                'error' => 'Quota exceeded',
                'quota' => $user->getQuota(),
                'usedSpace' => $user->getUsedSpace(),
                'fileSize' => $fileSize,
            ], Response::HTTP_INSUFFICIENT_STORAGE);
        }

        // Stocker le fichier
        try {
            $uploadResult = $this->rawFileUpload->store($fileData);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Failed to store file: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        // Créer l'entité File
        $file = new File();
        $file->setFilename($uploadResult['originalName']);
        $file->setStoredName($uploadResult['storedName']);
        $file->setMimeType($uploadResult['mimeType']);
        $file->setSize((string)$uploadResult['size']);
        $file->setUser($user);

        // Mettre à jour l'espace utilisé
        $user->setUsedSpace((string)((int)$user->getUsedSpace() + $fileSize));

        $this->entityManager->persist($file);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return new JsonResponse([
            'message' => 'File uploaded successfully',
            'file' => [
                'id' => $file->getId(),
                'filename' => $file->getFilename(),
                'size' => $file->getSize(),
                'mimeType' => $file->getMimeType(),
                'uploadedAt' => $file->getUploadedAt()->format('Y-m-d H:i:s'),
            ]
        ], Response::HTTP_CREATED);
    }

    #[Route('', name: 'app_file_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse([
                'error' => 'Not authenticated'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $files = $this->fileRepository->findByUser($user);

        $filesData = array_map(function (File $file) {
            return [
                'id' => $file->getId(),
                'filename' => $file->getFilename(),
                'size' => $file->getSize(),
                'mimeType' => $file->getMimeType(),
                'uploadedAt' => $file->getUploadedAt()->format('Y-m-d H:i:s'),
            ];
        }, $files);

        return new JsonResponse([
            'files' => $filesData,
            'count' => count($filesData),
        ]);
    }

    #[Route('/{id}/download', name: 'app_file_download', methods: ['GET'])]
    public function download(int $id): Response
    {
        $file = $this->fileRepository->find($id);

        if (!$file) {
            return new JsonResponse([
                'error' => 'File not found'
            ], Response::HTTP_NOT_FOUND);
        }

        // Vérifier l'accès
        $this->denyAccessUnlessGranted(FileVoter::DOWNLOAD, $file);

        $filePath = $this->fileStorage->getFilePath($file->getStoredName());

        if (!$this->fileStorage->exists($file->getStoredName())) {
            return new JsonResponse([
                'error' => 'File not found on disk'
            ], Response::HTTP_NOT_FOUND);
        }

        $response = new BinaryFileResponse($filePath);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $file->getFilename()
        );

        return $response;
    }

    #[Route('/{id}', name: 'app_file_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $file = $this->fileRepository->find($id);

        if (!$file) {
            return new JsonResponse([
                'error' => 'File not found'
            ], Response::HTTP_NOT_FOUND);
        }

        // Vérifier l'accès
        $this->denyAccessUnlessGranted(FileVoter::DELETE, $file);

        $user = $file->getUser();
        $fileSize = (int)$file->getSize();

        // Supprimer le fichier du disque
        $this->fileStorage->delete($file->getStoredName());

        // Mettre à jour l'espace utilisé
        $user->setUsedSpace((string)max(0, (int)$user->getUsedSpace() - $fileSize));

        $this->entityManager->remove($file);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return new JsonResponse([
            'message' => 'File deleted successfully'
        ]);
    }
}
