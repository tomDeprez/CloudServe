<?php

namespace App\Service;

class RawFileUploadService
{
    public function __construct(
        private string $projectDir
    ) {
    }

    /**
     * Store an uploaded file using raw $_FILES data
     *
     * @param array $fileData Data from $_FILES['key']
     * @return array ['storedName' => string, 'originalName' => string, 'mimeType' => string, 'size' => int]
     */
    public function store(array $fileData): array
    {
        if (!isset($fileData['tmp_name']) || !isset($fileData['name'])) {
            throw new \InvalidArgumentException('Invalid file data');
        }

        $tmpName = $fileData['tmp_name'];
        $originalName = $fileData['name'];
        $size = $fileData['size'] ?? 0;
        $mimeType = $fileData['type'] ?? 'application/octet-stream';

        // Vérifier que le fichier a bien été uploadé
        if (!is_uploaded_file($tmpName)) {
            throw new \RuntimeException('File was not uploaded via HTTP POST');
        }

        // Vérifier que le fichier existe et est lisible
        if (!file_exists($tmpName) || !is_readable($tmpName)) {
            throw new \RuntimeException(sprintf(
                'Uploaded file is not accessible: %s',
                $tmpName
            ));
        }

        // Créer le dossier uploads s'il n'existe pas
        $uploadDir = $this->projectDir . '/var/uploads';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        // Générer un nom unique
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        if (empty($extension)) {
            $extension = 'bin';
        }

        $storedName = uniqid() . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
        $destination = $uploadDir . '/' . $storedName;

        // Déplacer le fichier
        if (!move_uploaded_file($tmpName, $destination)) {
            throw new \RuntimeException(sprintf(
                'Failed to move uploaded file from %s to %s',
                $tmpName,
                $destination
            ));
        }

        return [
            'storedName' => $storedName,
            'originalName' => $originalName,
            'mimeType' => $mimeType,
            'size' => $size,
        ];
    }

    public function delete(string $storedName): void
    {
        $uploadDir = $this->projectDir . '/var/uploads';
        $filePath = $uploadDir . '/' . $storedName;

        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }

    public function getFilePath(string $storedName): string
    {
        $uploadDir = $this->projectDir . '/var/uploads';
        return $uploadDir . '/' . $storedName;
    }

    public function exists(string $storedName): bool
    {
        return file_exists($this->getFilePath($storedName));
    }
}
