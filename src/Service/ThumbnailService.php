<?php

namespace App\Service;

class ThumbnailService
{
    private string $uploadDirectory;
    private string $thumbnailDirectory;

    public function __construct(string $projectDir)
    {
        $this->uploadDirectory = $projectDir . '/var/uploads';
        $this->thumbnailDirectory = $projectDir . '/var/uploads/thumbnails';

        if (!is_dir($this->thumbnailDirectory)) {
            mkdir($this->thumbnailDirectory, 0777, true);
        }
    }

    /**
     * Génère une miniature pour une image
     *
     * @param string $storedName Nom du fichier stocké
     * @param int $width Largeur de la miniature
     * @param int $height Hauteur de la miniature
     * @return string|null Nom du fichier miniature ou null si échec
     */
    public function generateThumbnail(string $storedName, int $width = 200, int $height = 200): ?string
    {
        // Vérifier si GD est disponible
        if (!extension_loaded('gd')) {
            error_log('GD extension is not loaded. Thumbnails will not be generated.');
            return null;
        }

        $sourcePath = $this->uploadDirectory . '/' . $storedName;

        if (!file_exists($sourcePath)) {
            return null;
        }

        // Vérifier le type d'image
        $imageInfo = @getimagesize($sourcePath);
        if ($imageInfo === false) {
            return null; // Pas une image valide
        }

        $mimeType = $imageInfo['mime'];
        $sourceImage = null;

        // Charger l'image selon son type
        switch ($mimeType) {
            case 'image/jpeg':
                $sourceImage = @imagecreatefromjpeg($sourcePath);
                break;
            case 'image/png':
                $sourceImage = @imagecreatefrompng($sourcePath);
                break;
            case 'image/gif':
                $sourceImage = @imagecreatefromgif($sourcePath);
                break;
            case 'image/webp':
                $sourceImage = @imagecreatefromwebp($sourcePath);
                break;
            default:
                return null;
        }

        if ($sourceImage === false) {
            return null;
        }

        // Obtenir les dimensions originales
        $originalWidth = imagesx($sourceImage);
        $originalHeight = imagesy($sourceImage);

        // Calculer les nouvelles dimensions en préservant le ratio
        $ratio = min($width / $originalWidth, $height / $originalHeight);
        $newWidth = (int)($originalWidth * $ratio);
        $newHeight = (int)($originalHeight * $ratio);

        // Créer la miniature
        $thumbnail = imagecreatetruecolor($newWidth, $newHeight);

        // Préserver la transparence pour PNG et GIF
        if ($mimeType === 'image/png' || $mimeType === 'image/gif') {
            imagealphablending($thumbnail, false);
            imagesavealpha($thumbnail, true);
            $transparent = imagecolorallocatealpha($thumbnail, 255, 255, 255, 127);
            imagefilledrectangle($thumbnail, 0, 0, $newWidth, $newHeight, $transparent);
        }

        // Redimensionner
        imagecopyresampled(
            $thumbnail,
            $sourceImage,
            0, 0, 0, 0,
            $newWidth, $newHeight,
            $originalWidth, $originalHeight
        );

        // Générer le nom de la miniature (toujours en WebP pour optimisation)
        $thumbnailName = 'thumb_' . pathinfo($storedName, PATHINFO_FILENAME) . '.webp';
        $thumbnailPath = $this->thumbnailDirectory . '/' . $thumbnailName;

        // Sauvegarder la miniature en WebP avec qualité optimisée (80 = bon compromis taille/qualité)
        $success = imagewebp($thumbnail, $thumbnailPath, 80);

        // Libérer la mémoire
        imagedestroy($sourceImage);
        imagedestroy($thumbnail);

        return $success ? 'thumbnails/' . $thumbnailName : null;
    }

    /**
     * Supprimer une miniature
     */
    public function deleteThumbnail(string $thumbnailPath): void
    {
        $fullPath = $this->uploadDirectory . '/' . $thumbnailPath;
        if (file_exists($fullPath)) {
            @unlink($fullPath);
        }
    }

    /**
     * Obtenir le chemin complet d'une miniature
     */
    public function getThumbnailPath(string $thumbnailName): string
    {
        return $this->thumbnailDirectory . '/' . str_replace('thumbnails/', '', $thumbnailName);
    }

    /**
     * Vérifier si une miniature existe
     */
    public function thumbnailExists(string $thumbnailPath): bool
    {
        $fullPath = $this->uploadDirectory . '/' . $thumbnailPath;
        return file_exists($fullPath);
    }
}
