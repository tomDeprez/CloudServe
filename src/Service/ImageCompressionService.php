<?php

namespace App\Service;

class ImageCompressionService
{
    private const MAX_WIDTH = 4000;
    private const MAX_HEIGHT = 4000;
    private const JPEG_QUALITY = 85;
    private const PNG_COMPRESSION = 8; // 0-9, 9 est la compression maximale
    private const WEBP_QUALITY = 85;

    /**
     * Compresse une image sans perte visible de qualité
     *
     * @param string $sourcePath Chemin du fichier source
     * @param string $destinationPath Chemin du fichier de destination (peut être le même que source)
     * @param string|null $mimeType Type MIME (détecté automatiquement si null)
     * @return bool True si la compression a réussi
     */
    public function compressImage(string $sourcePath, string $destinationPath, ?string $mimeType = null): bool
    {
        if (!extension_loaded('gd')) {
            error_log('GD extension is not loaded. Image compression skipped.');
            return false;
        }

        if (!file_exists($sourcePath)) {
            return false;
        }

        // Détecter le type d'image
        $imageInfo = @getimagesize($sourcePath);
        if ($imageInfo === false) {
            return false;
        }

        $mimeType = $mimeType ?? $imageInfo['mime'];
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
                // GIF ne sera pas compressé pour préserver l'animation
                return false;
            case 'image/webp':
                $sourceImage = @imagecreatefromwebp($sourcePath);
                break;
            default:
                return false;
        }

        if ($sourceImage === false) {
            return false;
        }

        $originalWidth = imagesx($sourceImage);
        $originalHeight = imagesy($sourceImage);

        // Vérifier si redimensionnement nécessaire
        $needsResize = $originalWidth > self::MAX_WIDTH || $originalHeight > self::MAX_HEIGHT;

        $newWidth = $originalWidth;
        $newHeight = $originalHeight;

        if ($needsResize) {
            // Calculer nouvelles dimensions en préservant le ratio
            $ratio = min(
                self::MAX_WIDTH / $originalWidth,
                self::MAX_HEIGHT / $originalHeight
            );
            $newWidth = (int)($originalWidth * $ratio);
            $newHeight = (int)($originalHeight * $ratio);
        }

        // Créer l'image de destination
        if ($needsResize) {
            $destImage = imagecreatetruecolor($newWidth, $newHeight);

            // Préserver la transparence pour PNG et WEBP
            if ($mimeType === 'image/png' || $mimeType === 'image/webp') {
                imagealphablending($destImage, false);
                imagesavealpha($destImage, true);
                $transparent = imagecolorallocatealpha($destImage, 255, 255, 255, 127);
                imagefilledrectangle($destImage, 0, 0, $newWidth, $newHeight, $transparent);
            }

            // Redimensionner avec haute qualité
            imagecopyresampled(
                $destImage,
                $sourceImage,
                0, 0, 0, 0,
                $newWidth, $newHeight,
                $originalWidth, $originalHeight
            );
        } else {
            // Pas de redimensionnement, juste recompression
            $destImage = $sourceImage;

            // Pour PNG, s'assurer que la transparence est préservée
            if ($mimeType === 'image/png') {
                imagealphablending($destImage, false);
                imagesavealpha($destImage, true);
            }
        }

        // Sauvegarder avec compression optimale
        $success = false;
        switch ($mimeType) {
            case 'image/jpeg':
                $success = imagejpeg($destImage, $destinationPath, self::JPEG_QUALITY);
                break;
            case 'image/png':
                $success = imagepng($destImage, $destinationPath, self::PNG_COMPRESSION);
                break;
            case 'image/webp':
                $success = imagewebp($destImage, $destinationPath, self::WEBP_QUALITY);
                break;
        }

        // Libérer la mémoire
        imagedestroy($sourceImage);
        if ($needsResize) {
            imagedestroy($destImage);
        }

        return $success;
    }

    /**
     * Vérifie si un fichier est une image compressible
     */
    public function isCompressibleImage(string $mimeType): bool
    {
        return in_array($mimeType, [
            'image/jpeg',
            'image/png',
            'image/webp'
        ]);
    }

    /**
     * Retourne les informations sur la compression effectuée
     */
    public function getCompressionInfo(string $originalPath, string $compressedPath): array
    {
        $originalSize = filesize($originalPath);
        $compressedSize = filesize($compressedPath);
        $savedBytes = $originalSize - $compressedSize;
        $savedPercent = $originalSize > 0 ? ($savedBytes / $originalSize) * 100 : 0;

        return [
            'original_size' => $originalSize,
            'compressed_size' => $compressedSize,
            'saved_bytes' => $savedBytes,
            'saved_percent' => round($savedPercent, 2),
        ];
    }
}
