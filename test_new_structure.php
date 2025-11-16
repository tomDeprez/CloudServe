<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Service\ThumbnailService;
use App\Service\FileStorageService;
use App\Service\RawFileUploadService;

echo "=== TEST NOUVELLE STRUCTURE (public/uploads) ===\n\n";

$projectDir = __DIR__;

// Test 1: FileStorageService
echo "1. FileStorageService :\n";
$fileStorage = new FileStorageService($projectDir);
echo "   Upload directory: " . $fileStorage->getFilePath('test.txt') . "\n";
echo "   Doit contenir: public/uploads/test.txt\n\n";

// Test 2: ThumbnailService
echo "2. ThumbnailService :\n";
$thumbnailService = new ThumbnailService($projectDir);

$images = glob($projectDir . '/public/uploads/*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE);
if (!empty($images)) {
    $testImage = basename($images[0]);
    echo "   Image de test: $testImage\n";

    // Générer miniature
    $thumbnail = $thumbnailService->generateThumbnail($testImage);
    if ($thumbnail) {
        echo "   ✅ Miniature générée: $thumbnail\n";

        // Vérifier le chemin complet
        $fullPath = $thumbnailService->getThumbnailPath($thumbnail);
        echo "   Chemin complet: $fullPath\n";

        // Vérifier si accessible via URL
        $urlPath = '/uploads/' . $thumbnail;
        echo "   URL d'accès: $urlPath\n";

        if (file_exists($fullPath)) {
            echo "   ✅ Fichier existe\n";
        } else {
            echo "   ❌ Fichier n'existe pas\n";
        }
    } else {
        echo "   ❌ Échec génération miniature\n";
    }
} else {
    echo "   ⚠️  Aucune image trouvée dans public/uploads\n";
}

echo "\n3. Vérification structure :\n";
echo "   public/uploads existe : " . (is_dir($projectDir . '/public/uploads') ? '✅' : '❌') . "\n";
echo "   public/uploads/thumbnails existe : " . (is_dir($projectDir . '/public/uploads/thumbnails') ? '✅' : '❌') . "\n";
echo "   Fichiers dans public/uploads : " . count(glob($projectDir . '/public/uploads/*.*')) . "\n";
echo "   Thumbnails dans public/uploads/thumbnails : " . count(glob($projectDir . '/public/uploads/thumbnails/*.*')) . "\n";

echo "\n=== RÉSULTAT ===\n";
echo "Si tout est ✅, les miniatures devraient maintenant fonctionner !\n";
echo "Les fichiers sont directement accessibles via /uploads/...\n";
