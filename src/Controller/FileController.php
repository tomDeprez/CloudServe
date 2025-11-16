<?php

namespace App\Controller;

use App\Entity\File;
use App\Entity\UploadQueue;
use App\Repository\FileRepository;
use App\Repository\UploadQueueRepository;
use App\Security\FileVoter;
use App\Service\FileStorageService;
use App\Service\RawFileUploadService;
use App\Service\ThumbnailService;
use App\Service\ImageCompressionService;
use App\Service\VideoCompressionService;
use App\Service\AudioCompressionService;
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
        private ThumbnailService $thumbnailService,
        private ImageCompressionService $imageCompressionService,
        private VideoCompressionService $videoCompressionService,
        private AudioCompressionService $audioCompressionService,
        private FileRepository $fileRepository,
        private UploadQueueRepository $uploadQueueRepository,
        private string $projectDir,
    ) {
    }

    #[Route('/upload-multiple', name: 'app_file_upload_multiple', methods: ['POST'])]
    public function uploadMultiple(Request $request): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse(['error' => 'Not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        if (!$user->isActive()) {
            return new JsonResponse(['error' => 'Account suspended'], Response::HTTP_FORBIDDEN);
        }

        // Récupérer tous les fichiers uploadés
        if (empty($_FILES['files'])) {
            return new JsonResponse(['error' => 'No files uploaded'], Response::HTTP_BAD_REQUEST);
        }

        $parentId = $request->request->get('parent_id');
        $parent = null;

        if ($parentId) {
            $parent = $this->fileRepository->find($parentId);
            if (!$parent || !$parent->isFolder() || $parent->getUser()->getId() !== $user->getId()) {
                return new JsonResponse(['error' => 'Invalid parent folder'], Response::HTTP_BAD_REQUEST);
            }
        }

        $uploadedFiles = [];
        $errors = [];
        $totalSizeBeforeCompression = 0;

        // Normaliser $_FILES pour gérer les uploads multiples
        $filesData = $this->normalizeFilesArray($_FILES['files']);

        foreach ($filesData as $index => $fileData) {
            if ($fileData['error'] !== UPLOAD_ERR_OK) {
                $errors[] = [
                    'filename' => $fileData['name'],
                    'error' => 'Upload error code: ' . $fileData['error']
                ];
                continue;
            }

            $totalSizeBeforeCompression += $fileData['size'];
        }

        // Vérifier le quota pour tous les fichiers (avec taille avant compression pour être sûr)
        if (!$user->hasAvailableSpace($totalSizeBeforeCompression)) {
            return new JsonResponse([
                'error' => 'Quota exceeded',
                'quota' => $user->getQuota(),
                'usedSpace' => $user->getUsedSpace(),
                'requiredSpace' => $totalSizeBeforeCompression,
            ], Response::HTTP_INSUFFICIENT_STORAGE);
        }

        // Uploader chaque fichier
        foreach ($filesData as $fileData) {
            if ($fileData['error'] !== UPLOAD_ERR_OK) {
                continue;
            }

            try {
                $uploadResult = $this->rawFileUpload->store($fileData);

                $uploadPath = $this->getParameter('kernel.project_dir') . '/public/uploads/' . $uploadResult['storedName'];
                $fileSize = $uploadResult['size'];

                // Compresser SEULEMENT les images (rapide)
                // Vidéos et audio : compression désactivée pour éviter les timeouts
                if ($this->imageCompressionService->isCompressibleImage($uploadResult['mimeType'])) {
                    // Compresser seulement si < 50MB pour éviter les timeouts
                    if ($fileSize < 50 * 1024 * 1024) {
                        $compressed = $this->imageCompressionService->compressImage(
                            $uploadPath,
                            $uploadPath, // Écraser le fichier original
                            $uploadResult['mimeType']
                        );

                        // Mettre à jour la taille si compression réussie
                        if ($compressed && file_exists($uploadPath)) {
                            $uploadResult['size'] = filesize($uploadPath);
                        }
                    }
                }

                // DÉSACTIVÉ : Compression vidéo/audio (trop lent, bloque les requêtes)
                // Si besoin, utiliser une queue async avec un worker PHP

                // PROTECTION : Vérifier si un fichier avec le même hash existe déjà
                $existingFile = $this->fileRepository->findOneBy([
                    'user' => $user,
                    'hash' => $uploadResult['hash']
                ]);

                if ($existingFile) {
                    // Fichier déjà existant, ne pas le dupliquer
                    // Supprimer le fichier temporaire uploadé
                    if (file_exists($uploadPath)) {
                        unlink($uploadPath);
                    }

                    $errors[] = [
                        'filename' => $uploadResult['originalName'],
                        'error' => 'Ce fichier existe déjà',
                        'existing_file' => $existingFile->getFilename()
                    ];
                    continue;
                }

                $file = new File();
                $file->setFilename($uploadResult['originalName']);
                $file->setStoredName($uploadResult['storedName']);
                $file->setMimeType($uploadResult['mimeType']);
                $file->setSize((string)$uploadResult['size']);
                $file->setHash($uploadResult['hash']);
                $file->setUser($user);
                $file->setType('file');
                if ($parent) {
                    $file->setParent($parent);
                }

                // Marquer comme en cours de traitement
                $file->setProcessing(true);

                // Persister immédiatement pour que le fichier apparaisse dans la liste
                $this->entityManager->persist($file);
                $this->entityManager->flush();

                // Générer miniature pour les images
                if ($file->getFileType() === 'image') {
                    try {
                        $thumbnailPath = $this->thumbnailService->generateThumbnail($uploadResult['storedName']);
                        if ($thumbnailPath) {
                            $file->setThumbnail($thumbnailPath);
                        }
                    } catch (\Exception $e) {
                        // Ignorer les erreurs de génération de miniature
                    }
                }

                // Marquer le traitement comme terminé
                $file->setProcessing(false);
                $this->entityManager->persist($file);

                $uploadedFiles[] = [
                    'id' => null, // Sera défini après flush
                    'filename' => $file->getFilename(),
                    'size' => $file->getSize(),
                    'type' => $file->getFileType(),
                ];

            } catch (\Exception $e) {
                $errors[] = [
                    'filename' => $fileData['name'],
                    'error' => $e->getMessage()
                ];
            }
        }

        // Calculer la taille réelle utilisée APRÈS compression
        $actualSizeUsed = 0;
        foreach ($uploadedFiles as $uploadedFile) {
            $actualSizeUsed += (int)$uploadedFile['size'];
        }

        // Mettre à jour l'espace utilisé avec la taille RÉELLE (après compression)
        $user->setUsedSpace((string)((int)$user->getUsedSpace() + $actualSizeUsed));
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return new JsonResponse([
            'message' => count($uploadedFiles) . ' file(s) uploaded successfully',
            'uploaded' => $uploadedFiles,
            'errors' => $errors,
            'total' => count($filesData),
            'success' => count($uploadedFiles),
            'failed' => count($errors),
        ], Response::HTTP_CREATED);
    }

    private function normalizeFilesArray(array $files): array
    {
        $normalized = [];

        if (isset($files['name']) && is_array($files['name'])) {
            // Format multiple : files[0], files[1], etc.
            foreach ($files['name'] as $index => $name) {
                $normalized[] = [
                    'name' => $files['name'][$index],
                    'type' => $files['type'][$index],
                    'tmp_name' => $files['tmp_name'][$index],
                    'error' => $files['error'][$index],
                    'size' => $files['size'][$index],
                ];
            }
        } else {
            // Format simple : un seul fichier
            $normalized[] = $files;
        }

        return $normalized;
    }

    #[Route('/check-duplicates', name: 'app_file_check_duplicates', methods: ['POST'])]
    public function checkDuplicates(Request $request): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse(['error' => 'Not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);
        $hash = $data['hash'] ?? null;

        if (!$hash) {
            return new JsonResponse(['error' => 'Hash required'], Response::HTTP_BAD_REQUEST);
        }

        // Trouver les fichiers avec le même hash
        $duplicates = $this->fileRepository->findDuplicatesByHash($user, $hash);

        $duplicatesData = array_map(function (File $file) {
            return [
                'id' => $file->getId(),
                'filename' => $file->getFilename(),
                'size' => $file->getSize(),
                'path' => $this->fileRepository->getFilePath($file),
                'uploadedAt' => $file->getUploadedAt()->format('Y-m-d H:i:s'),
            ];
        }, $duplicates);

        return new JsonResponse([
            'hasDuplicates' => count($duplicates) > 0,
            'duplicates' => $duplicatesData,
        ]);
    }

    #[Route('/all-with-paths', name: 'app_file_all_with_paths', methods: ['GET'])]
    public function allWithPaths(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse(['error' => 'Not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        // Récupérer tous les fichiers de l'utilisateur
        $files = $this->fileRepository->findByUser($user);

        $filesData = array_map(function (File $file) {
            return [
                'id' => $file->getId(),
                'filename' => $file->getFilename(),
                'size' => $file->getSize(),
                'type' => $file->getFileType(),
                'isFolder' => $file->isFolder(),
                'path' => $this->fileRepository->getFilePath($file),
                'uploadedAt' => $file->getUploadedAt()->format('Y-m-d H:i:s'),
                'mimeType' => $file->getMimeType(),
            ];
        }, $files);

        return new JsonResponse([
            'files' => $filesData,
            'total' => count($filesData),
        ]);
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

        $uploadPath = $this->getParameter('kernel.project_dir') . '/var/uploads/' . $uploadResult['storedName'];

        // Compresser l'image si applicable
        if ($this->imageCompressionService->isCompressibleImage($uploadResult['mimeType'])) {
            $compressed = $this->imageCompressionService->compressImage(
                $uploadPath,
                $uploadPath, // Écraser le fichier original
                $uploadResult['mimeType']
            );

            // Mettre à jour la taille si compression réussie
            if ($compressed && file_exists($uploadPath)) {
                $uploadResult['size'] = filesize($uploadPath);
            }
        }

        // Compresser la vidéo si applicable
        if ($this->videoCompressionService->isCompressibleVideo($uploadResult['mimeType'])) {
            $tempPath = $uploadPath . '.temp.mp4';
            $compressed = $this->videoCompressionService->compressVideo($uploadPath, $tempPath);

            if ($compressed && file_exists($tempPath)) {
                unlink($uploadPath);
                rename($tempPath, $uploadPath);
                $uploadResult['size'] = filesize($uploadPath);
            }
        }

        // Compresser l'audio si applicable
        if ($this->audioCompressionService->isCompressibleAudio($uploadResult['mimeType'])) {
            $tempPath = $uploadPath . '.temp.m4a';
            $compressed = $this->audioCompressionService->compressAudio($uploadPath, $tempPath);

            if ($compressed && file_exists($tempPath)) {
                unlink($uploadPath);
                rename($tempPath, $uploadPath);
                $uploadResult['size'] = filesize($uploadPath);
            }
        }

        // Récupérer le dossier parent si spécifié
        $parentId = $request->request->get('parent_id');
        $parent = null;

        if ($parentId) {
            $parent = $this->fileRepository->find($parentId);
            if (!$parent || !$parent->isFolder() || $parent->getUser()->getId() !== $user->getId()) {
                return new JsonResponse([
                    'error' => 'Invalid parent folder'
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        // Créer l'entité File
        $file = new File();
        $file->setFilename($uploadResult['originalName']);
        $file->setStoredName($uploadResult['storedName']);
        $file->setMimeType($uploadResult['mimeType']);
        $file->setSize((string)$uploadResult['size']);
        $file->setHash($uploadResult['hash']);
        $file->setUser($user);
        $file->setType('file');
        if ($parent) {
            $file->setParent($parent);
        }

        // Générer une miniature si c'est une image
        if ($file->getFileType() === 'image') {
            try {
                $thumbnailPath = $this->thumbnailService->generateThumbnail($uploadResult['storedName']);
                if ($thumbnailPath) {
                    $file->setThumbnail($thumbnailPath);
                }
            } catch (\Exception $e) {
                // Ignorer les erreurs de génération de miniature
            }
        }

        // Mettre à jour l'espace utilisé (utiliser la taille après compression)
        $user->setUsedSpace((string)((int)$user->getUsedSpace() + (int)$uploadResult['size']));

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
                'type' => $file->getFileType(),
                'thumbnail' => $file->getThumbnail(),
                'parent_id' => $parent?->getId(),
                'uploadedAt' => $file->getUploadedAt()->format('Y-m-d H:i:s'),
            ]
        ], Response::HTTP_CREATED);
    }

    #[Route('', name: 'app_file_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse([
                'error' => 'Not authenticated'
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Récupérer le dossier parent pour la navigation
        $parentId = $request->query->get('parent_id');
        $parent = null;

        if ($parentId) {
            $parent = $this->fileRepository->find($parentId);
            if (!$parent || $parent->getUser()->getId() !== $user->getId()) {
                return new JsonResponse([
                    'error' => 'Invalid parent folder'
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        // Récupérer les fichiers du dossier actuel
        $files = $parentId
            ? $this->fileRepository->findBy(['user' => $user, 'parent' => $parent])
            : $this->fileRepository->findBy(['user' => $user, 'parent' => null]);

        $filesData = array_map(function (File $file) {
            // Calculer la taille réelle pour les dossiers
            $size = $file->isFolder() ? $this->calculateFolderSize($file) : $file->getSize();

            return [
                'id' => $file->getId(),
                'filename' => $file->getFilename(),
                'size' => $size,
                'mimeType' => $file->getMimeType(),
                'type' => $file->getFileType(),
                'isFolder' => $file->isFolder(),
                'isEditable' => $file->isEditable(),
                'thumbnail' => $file->getThumbnail(),
                'parent_id' => $file->getParent()?->getId(),
                'uploadedAt' => $file->getUploadedAt()->format('Y-m-d H:i:s'),
                'processing' => $file->isProcessing(),
            ];
        }, $files);

        return new JsonResponse([
            'files' => $filesData,
            'count' => count($filesData),
            'current_folder' => $parent ? [
                'id' => $parent->getId(),
                'name' => $parent->getFilename(),
                'parent_id' => $parent->getParent()?->getId(),
            ] : null,
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
        $totalSize = $this->deleteFileRecursive($file);

        // Mettre à jour l'espace utilisé
        $user->setUsedSpace((string)max(0, (int)$user->getUsedSpace() - $totalSize));
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return new JsonResponse([
            'message' => 'File deleted successfully'
        ]);
    }

    /**
     * Supprime un fichier ou dossier de manière récursive
     * @return int Taille totale supprimée
     */
    private function deleteFileRecursive(\App\Entity\File $file): int
    {
        $totalSize = 0;

        // Si c'est un dossier, supprimer tous les fichiers enfants
        if ($file->isFolder()) {
            $children = $this->fileRepository->findBy(['parent' => $file]);
            foreach ($children as $child) {
                $totalSize += $this->deleteFileRecursive($child);
            }
            // Flush après avoir supprimé tous les enfants pour éviter les conflits de contraintes
            $this->entityManager->flush();
        } else {
            // C'est un fichier, supprimer du disque
            if ($file->getStoredName()) {
                $this->fileStorage->delete($file->getStoredName());
            }
            // Supprimer la miniature si elle existe
            if ($file->getThumbnail()) {
                $this->thumbnailService->deleteThumbnail($file->getThumbnail());
            }
            $totalSize = (int)$file->getSize();
        }

        // Supprimer l'entité de la base de données
        $this->entityManager->remove($file);

        return $totalSize;
    }

    /**
     * Calcule la taille totale d'un dossier (récursivement)
     * @return int Taille totale en octets
     */
    private function calculateFolderSize(\App\Entity\File $folder): int
    {
        $totalSize = 0;

        // Récupérer tous les fichiers enfants
        $children = $this->fileRepository->findBy(['parent' => $folder]);

        foreach ($children as $child) {
            if ($child->isFolder()) {
                // Récursion pour les sous-dossiers
                $totalSize += $this->calculateFolderSize($child);
            } else {
                // Ajouter la taille du fichier
                $totalSize += (int)$child->getSize();
            }
        }

        return $totalSize;
    }

    #[Route('/folder', name: 'app_folder_create', methods: ['POST'])]
    public function createFolder(Request $request): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse(['error' => 'Not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);
        $folderName = $data['name'] ?? null;
        $parentId = $data['parent_id'] ?? null;

        if (!$folderName || trim($folderName) === '') {
            return new JsonResponse(['error' => 'Folder name is required'], Response::HTTP_BAD_REQUEST);
        }

        $parent = null;
        if ($parentId) {
            $parent = $this->fileRepository->find($parentId);
            if (!$parent || !$parent->isFolder() || $parent->getUser()->getId() !== $user->getId()) {
                return new JsonResponse(['error' => 'Invalid parent folder'], Response::HTTP_BAD_REQUEST);
            }
        }

        // PROTECTION : Vérifier qu'un dossier avec le même nom n'existe pas déjà au même endroit
        $existingFolder = $this->fileRepository->findOneBy([
            'user' => $user,
            'parent' => $parent,
            'filename' => trim($folderName),
            'type' => 'folder'
        ]);

        if ($existingFolder) {
            return new JsonResponse([
                'error' => 'Un dossier avec ce nom existe déjà à cet emplacement',
                'existing_folder' => [
                    'id' => $existingFolder->getId(),
                    'filename' => $existingFolder->getFilename()
                ]
            ], Response::HTTP_CONFLICT);
        }

        $folder = new File();
        $folder->setFilename($folderName);
        $folder->setType('folder');
        $folder->setSize('0');
        $folder->setMimeType('inode/directory');
        $folder->setUser($user);
        $folder->setStoredName(''); // Pas de fichier physique pour un dossier
        if ($parent) {
            $folder->setParent($parent);
        }

        $this->entityManager->persist($folder);
        $this->entityManager->flush();

        return new JsonResponse([
            'message' => 'Folder created successfully',
            'folder' => [
                'id' => $folder->getId(),
                'filename' => $folder->getFilename(),
                'type' => 'folder',
                'parent_id' => $parent?->getId(),
            ]
        ], Response::HTTP_CREATED);
    }

    #[Route('/text', name: 'app_text_file_create', methods: ['POST'])]
    public function createTextFile(Request $request): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse(['error' => 'Not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);
        $filename = $data['filename'] ?? 'untitled.txt';
        $content = $data['content'] ?? '';
        $parentId = $data['parent_id'] ?? null;

        $parent = null;
        if ($parentId) {
            $parent = $this->fileRepository->find($parentId);
            if (!$parent || !$parent->isFolder() || $parent->getUser()->getId() !== $user->getId()) {
                return new JsonResponse(['error' => 'Invalid parent folder'], Response::HTTP_BAD_REQUEST);
            }
        }

        $textFile = new File();
        $textFile->setFilename($filename);
        $textFile->setType('file');
        $textFile->setMimeType('text/plain');
        $textFile->setContent($content);
        $textFile->setSize((string)strlen($content));
        $textFile->setUser($user);
        $textFile->setStoredName(''); // Contenu stocké en BDD pour les fichiers texte
        if ($parent) {
            $textFile->setParent($parent);
        }

        $this->entityManager->persist($textFile);
        $this->entityManager->flush();

        return new JsonResponse([
            'message' => 'Text file created successfully',
            'file' => [
                'id' => $textFile->getId(),
                'filename' => $textFile->getFilename(),
                'type' => 'text',
                'size' => $textFile->getSize(),
                'parent_id' => $parent?->getId(),
            ]
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}/content', name: 'app_file_content_get', methods: ['GET'])]
    public function getFileContent(int $id): JsonResponse
    {
        $file = $this->fileRepository->find($id);

        if (!$file) {
            return new JsonResponse(['error' => 'File not found'], Response::HTTP_NOT_FOUND);
        }

        $this->denyAccessUnlessGranted(FileVoter::VIEW, $file);

        if (!$file->isEditable()) {
            return new JsonResponse(['error' => 'File is not editable'], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse([
            'id' => $file->getId(),
            'filename' => $file->getFilename(),
            'content' => $file->getContent() ?? '',
        ]);
    }

    #[Route('/{id}/content', name: 'app_file_content_update', methods: ['PUT'])]
    public function updateFileContent(int $id, Request $request): JsonResponse
    {
        $file = $this->fileRepository->find($id);

        if (!$file) {
            return new JsonResponse(['error' => 'File not found'], Response::HTTP_NOT_FOUND);
        }

        $this->denyAccessUnlessGranted(FileVoter::DELETE, $file); // Utiliser DELETE comme permission pour éditer

        if (!$file->isEditable()) {
            return new JsonResponse(['error' => 'File is not editable'], Response::HTTP_BAD_REQUEST);
        }

        $data = json_decode($request->getContent(), true);
        $newContent = $data['content'] ?? '';

        $file->setContent($newContent);
        $file->setSize((string)strlen($newContent));

        $this->entityManager->flush();

        return new JsonResponse([
            'message' => 'File content updated successfully',
            'file' => [
                'id' => $file->getId(),
                'filename' => $file->getFilename(),
                'size' => $file->getSize(),
            ]
        ]);
    }

    #[Route('/{id}/move', name: 'app_file_move', methods: ['PATCH'])]
    public function moveFile(int $id, Request $request): JsonResponse
    {
        $file = $this->fileRepository->find($id);

        if (!$file) {
            return new JsonResponse(['error' => 'File not found'], Response::HTTP_NOT_FOUND);
        }

        $this->denyAccessUnlessGranted(FileVoter::DELETE, $file);

        $data = json_decode($request->getContent(), true);
        $newParentId = $data['parent_id'] ?? null;

        $newParent = null;
        if ($newParentId) {
            $newParent = $this->fileRepository->find($newParentId);
            if (!$newParent || !$newParent->isFolder() || $newParent->getUser()->getId() !== $file->getUser()->getId()) {
                return new JsonResponse(['error' => 'Invalid parent folder'], Response::HTTP_BAD_REQUEST);
            }

            // Empêcher de déplacer un dossier dans lui-même ou ses sous-dossiers
            if ($file->isFolder()) {
                $current = $newParent;
                while ($current) {
                    if ($current->getId() === $file->getId()) {
                        return new JsonResponse(['error' => 'Cannot move folder into itself'], Response::HTTP_BAD_REQUEST);
                    }
                    $current = $current->getParent();
                }
            }
        }

        $file->setParent($newParent);
        $this->entityManager->flush();

        return new JsonResponse([
            'message' => 'File moved successfully',
            'file' => [
                'id' => $file->getId(),
                'filename' => $file->getFilename(),
                'parent_id' => $newParent?->getId(),
            ]
        ]);
    }

    #[Route('/{id}/thumbnail', name: 'app_file_thumbnail', methods: ['GET'])]
    public function getThumbnail(int $id): Response
    {
        $file = $this->fileRepository->find($id);

        if (!$file) {
            return new JsonResponse(['error' => 'File not found'], Response::HTTP_NOT_FOUND);
        }

        $this->denyAccessUnlessGranted(FileVoter::VIEW, $file);

        $thumbnailPath = $file->getThumbnail();

        if (!$thumbnailPath || !$this->thumbnailService->thumbnailExists($thumbnailPath)) {
            // Générer la miniature si elle n'existe pas et que c'est une image
            if ($file->getFileType() === 'image' && $file->getStoredName()) {
                $thumbnailPath = $this->thumbnailService->generateThumbnail($file->getStoredName());
                if ($thumbnailPath) {
                    $file->setThumbnail($thumbnailPath);
                    $this->entityManager->flush();
                }
            }
        }

        if (!$thumbnailPath || !$this->thumbnailService->thumbnailExists($thumbnailPath)) {
            return new JsonResponse(['error' => 'Thumbnail not available'], Response::HTTP_NOT_FOUND);
        }

        $fullPath = $this->thumbnailService->getThumbnailPath($thumbnailPath);
        return new BinaryFileResponse($fullPath);
    }

    #[Route('/{id}/view', name: 'app_file_view', methods: ['GET'])]
    public function viewFile(int $id): Response
    {
        $file = $this->fileRepository->find($id);

        if (!$file) {
            return new JsonResponse(['error' => 'File not found'], Response::HTTP_NOT_FOUND);
        }

        $this->denyAccessUnlessGranted(FileVoter::VIEW, $file);

        // Vérifier si le fichier est en cours de traitement
        if ($file->isProcessing()) {
            return new JsonResponse([
                'processing' => true,
                'message' => 'Fichier en cours de traitement...',
                'filename' => $file->getFilename(),
            ], Response::HTTP_ACCEPTED);
        }

        // Pour les fichiers texte éditables, retourner le contenu JSON
        if ($file->isEditable()) {
            return new JsonResponse([
                'id' => $file->getId(),
                'filename' => $file->getFilename(),
                'content' => $file->getContent() ?? '',
                'type' => 'text',
            ]);
        }

        // Pour les autres fichiers, retourner le fichier pour affichage inline
        if (!$file->getStoredName() || !$this->fileStorage->exists($file->getStoredName())) {
            return new JsonResponse(['error' => 'File not found on disk'], Response::HTTP_NOT_FOUND);
        }

        $filePath = $this->fileStorage->getFilePath($file->getStoredName());
        $response = new BinaryFileResponse($filePath);

        // Définir le bon Content-Type et disposition inline pour visualisation
        $response->headers->set('Content-Type', $file->getMimeType());
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_INLINE,
            $file->getFilename()
        );

        return $response;
    }

    #[Route('/queue-upload', name: 'app_file_queue_upload', methods: ['POST'])]
    public function queueUpload(Request $request): JsonResponse
    {
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse(['error' => 'Not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        if (!$user->isActive()) {
            return new JsonResponse(['error' => 'Account suspended'], Response::HTTP_FORBIDDEN);
        }

        if (empty($_FILES['files'])) {
            return new JsonResponse(['error' => 'No files uploaded'], Response::HTTP_BAD_REQUEST);
        }

        $parentId = $request->request->get('parent_id');
        $parent = null;

        if ($parentId) {
            $parent = $this->fileRepository->find($parentId);
            if (!$parent || !$parent->isFolder() || $parent->getUser()->getId() !== $user->getId()) {
                return new JsonResponse(['error' => 'Invalid parent folder'], Response::HTTP_BAD_REQUEST);
            }
        }

        $filesData = $this->normalizeFilesArray($_FILES['files']);
        $queuedItems = [];
        $errors = [];
        $totalSize = 0;

        // Calculer la taille totale
        foreach ($filesData as $fileData) {
            if ($fileData['error'] === UPLOAD_ERR_OK) {
                $totalSize += $fileData['size'];
            }
        }

        // Vérifier le quota
        if (!$user->hasAvailableSpace($totalSize)) {
            return new JsonResponse([
                'error' => 'Quota exceeded',
                'quota' => $user->getQuota(),
                'usedSpace' => $user->getUsedSpace(),
                'requiredSpace' => $totalSize,
            ], Response::HTTP_INSUFFICIENT_STORAGE);
        }

        $tempDir = $this->projectDir . '/var/tmp/uploads';
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0777, true);
        }

        // Ajouter chaque fichier à la queue
        foreach ($filesData as $fileData) {
            if ($fileData['error'] !== UPLOAD_ERR_OK) {
                $errors[] = [
                    'filename' => $fileData['name'],
                    'error' => 'Upload error code: ' . $fileData['error']
                ];
                continue;
            }

            try {
                // Déplacer vers un fichier temporaire
                $tempFileName = uniqid('upload_', true) . '_' . basename($fileData['name']);
                $tempPath = $tempDir . '/' . $tempFileName;

                if (!move_uploaded_file($fileData['tmp_name'], $tempPath)) {
                    throw new \RuntimeException('Failed to move uploaded file');
                }

                // Calculer le hash
                $hash = hash_file('sha256', $tempPath);

                // Créer l'entrée dans la queue
                $queueItem = new UploadQueue();
                $queueItem->setUser($user);
                $queueItem->setFilename($fileData['name']);
                $queueItem->setTempPath($tempPath);
                $queueItem->setMimeType($fileData['type']);
                $queueItem->setSize((string) $fileData['size']);
                $queueItem->setHash($hash);
                $queueItem->setParentFolder($parent);
                $queueItem->setStatus('pending');

                $this->entityManager->persist($queueItem);
                $this->entityManager->flush();

                $queuedItems[] = [
                    'id' => $queueItem->getId(),
                    'filename' => $queueItem->getFilename(),
                    'size' => $queueItem->getSize(),
                    'status' => 'queued',
                ];

            } catch (\Exception $e) {
                $errors[] = [
                    'filename' => $fileData['name'],
                    'error' => $e->getMessage()
                ];
            }
        }

        return new JsonResponse([
            'success' => true,
            'queued' => count($queuedItems),
            'items' => $queuedItems,
            'errors' => $errors,
        ]);
    }

    #[Route('/upload-status', name: 'app_file_upload_status', methods: ['GET'])]
    public function uploadStatus(Request $request): Response
    {
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse(['error' => 'Not authenticated'], Response::HTTP_UNAUTHORIZED);
        }

        // Configuration SSE
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no'); // Nginx buffering fix

        // Désactiver le buffering PHP
        if (ob_get_level()) {
            ob_end_clean();
        }

        set_time_limit(0);
        ignore_user_abort(true);

        $lastCheck = new \DateTimeImmutable('-1 hour');
        $maxDuration = 300; // 5 minutes max
        $startTime = time();

        while (time() - $startTime < $maxDuration) {
            // Récupérer les uploads de l'utilisateur
            $uploads = $this->uploadQueueRepository->findPendingByUser($user);

            $data = [
                'uploads' => [],
                'stats' => $this->uploadQueueRepository->getStatsByUser($user),
                'timestamp' => time(),
            ];

            foreach ($uploads as $upload) {
                $data['uploads'][] = [
                    'id' => $upload->getId(),
                    'filename' => $upload->getFilename(),
                    'size' => $upload->getSize(),
                    'status' => $upload->getStatus(),
                    'progress' => $upload->getProgress(),
                    'error' => $upload->getErrorMessage(),
                    'fileId' => $upload->getResultFile()?->getId(),
                ];
            }

            // Envoyer les données
            echo "data: " . json_encode($data) . "\n\n";

            if (ob_get_level()) {
                ob_flush();
            }
            flush();

            // Si plus aucun upload en cours, arrêter
            if (empty($uploads)) {
                break;
            }

            // Attendre avant la prochaine vérification
            sleep(1);

            // Recharger l'entity manager pour éviter les problèmes de cache
            $this->entityManager->clear();
        }

        return new Response();
    }
}
