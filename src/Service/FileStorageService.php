<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;

class FileStorageService
{
    private string $uploadDirectory;

    public function __construct(string $projectDir)
    {
        $this->uploadDirectory = $projectDir . '/var/uploads';
        if (!is_dir($this->uploadDirectory)) {
            mkdir($this->uploadDirectory, 0777, true);
        }
    }

    public function store(UploadedFile $file): string
    {
        // Obtenir l'extension depuis le nom original du fichier
        $originalName = $file->getClientOriginalName();
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);

        if (empty($extension)) {
            // Si pas d'extension, essayer de deviner
            $extension = $file->guessExtension() ?? 'bin';
        }

        $storedName = uniqid() . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
        $destination = $this->uploadDirectory . '/' . $storedName;

        // Vérifier que le fichier source existe avant toute opération
        $sourcePath = $file->getPathname();

        if (!file_exists($sourcePath)) {
            throw new \RuntimeException(sprintf(
                'Source file does not exist: %s',
                $sourcePath
            ));
        }

        if (!is_readable($sourcePath)) {
            throw new \RuntimeException(sprintf(
                'Source file is not readable: %s',
                $sourcePath
            ));
        }

        // Méthode 1 : Essayer move_uploaded_file (plus sûr pour les fichiers uploadés)
        if (is_uploaded_file($sourcePath)) {
            if (move_uploaded_file($sourcePath, $destination)) {
                return $storedName;
            }
        }

        // Méthode 2 : Essayer la méthode move() de Symfony
        try {
            $file->move($this->uploadDirectory, $storedName);
            if (file_exists($destination)) {
                return $storedName;
            }
        } catch (\Exception $e) {
            // Continue to next method
        }

        // Méthode 3 : Copier le fichier
        if (copy($sourcePath, $destination)) {
            @unlink($sourcePath); // Supprimer le fichier temporaire
            return $storedName;
        }

        // Méthode 4 : Lire et écrire directement
        $content = file_get_contents($sourcePath);
        if ($content !== false) {
            if (file_put_contents($destination, $content) !== false) {
                @unlink($sourcePath); // Supprimer le fichier temporaire
                return $storedName;
            }
        }

        // Si tout échoue
        throw new \RuntimeException(sprintf(
            'Unable to store uploaded file. Source: %s (%s), Destination: %s',
            $sourcePath,
            file_exists($sourcePath) ? 'exists' : 'missing',
            $destination
        ));
    }

    public function delete(string $storedName): void
    {
        $filePath = $this->uploadDirectory . '/' . $storedName;
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }

    public function getFilePath(string $storedName): string
    {
        return $this->uploadDirectory . '/' . $storedName;
    }

    public function exists(string $storedName): bool
    {
        return file_exists($this->getFilePath($storedName));
    }
}
