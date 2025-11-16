<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Service\ImageCompressionService;
use App\Service\ThumbnailService;

echo "=== TEST COMPLET DU FLUX D'UPLOAD ===\n\n";

$uploadsDir = __DIR__ . '/var/uploads';
$images = glob($uploadsDir . '/*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE);

if (empty($images)) {
    echo "❌ Aucune image trouvée dans uploads\n";
    exit(1);
}

// Prendre la première image
$originalImage = $images[0];
$testImage = basename($originalImage);
echo "Image de test : $testImage\n";

// Créer une copie pour tester
$testCopy = $uploadsDir . '/test_' . time() . '_' . $testImage;
copy($originalImage, $testCopy);
echo "Copie créée : " . basename($testCopy) . "\n\n";

$storedName = basename($testCopy);
$mimeType = mime_content_type($testCopy);
$sizeBeforeCompression = filesize($testCopy);

echo "Taille avant compression : " . round($sizeBeforeCompression / 1024, 1) . " KB\n";
echo "MIME Type : $mimeType\n\n";

// === ÉTAPE 1 : COMPRESSION ===
echo "--- ÉTAPE 1 : Compression de l'image ---\n";

$imageCompressionService = new ImageCompressionService();

if ($imageCompressionService->isCompressibleImage($mimeType)) {
    echo "✅ Image compressible\n";

    $compressed = $imageCompressionService->compressImage(
        $testCopy,
        $testCopy,
        $mimeType
    );

    if ($compressed && file_exists($testCopy)) {
        $sizeAfterCompression = filesize($testCopy);
        $reduction = round((1 - $sizeAfterCompression / $sizeBeforeCompression) * 100, 1);
        echo "✅ Compression réussie\n";
        echo "   Taille après : " . round($sizeAfterCompression / 1024, 1) . " KB\n";
        echo "   Réduction : {$reduction}%\n";
    } else {
        echo "❌ Compression échouée\n";
    }
} else {
    echo "⚠️  Image non compressible (format non supporté)\n";
}

echo "\n";

// === ÉTAPE 2 : VÉRIFICATION DU FICHIER ===
echo "--- ÉTAPE 2 : Vérification du fichier après compression ---\n";

if (!file_exists($testCopy)) {
    echo "❌ Le fichier n'existe plus après compression !\n";
    exit(1);
}

$imageInfo = @getimagesize($testCopy);
if ($imageInfo === false) {
    echo "❌ Le fichier n'est plus une image valide après compression !\n";
    exit(1);
}

echo "✅ Fichier existe et est valide\n";
echo "   Dimensions : {$imageInfo[0]}x{$imageInfo[1]}\n";
echo "   Type : {$imageInfo['mime']}\n\n";

// === ÉTAPE 3 : GÉNÉRATION DE MINIATURE ===
echo "--- ÉTAPE 3 : Génération de miniature (comme dans FileController) ---\n";

try {
    $thumbnailService = new ThumbnailService(__DIR__);

    error_log("TEST: Generating thumbnail for: " . $storedName);
    $thumbnailPath = $thumbnailService->generateThumbnail($storedName);

    if ($thumbnailPath) {
        echo "✅ Miniature générée\n";
        echo "   Chemin : $thumbnailPath\n";
        error_log("TEST: Thumbnail generated: " . $thumbnailPath);

        $fullPath = __DIR__ . '/var/uploads/' . $thumbnailPath;
        if (file_exists($fullPath)) {
            echo "   Taille : " . round(filesize($fullPath) / 1024, 1) . " KB\n";

            // Vérifier l'accessibilité via public
            $publicPath = __DIR__ . '/public/uploads/' . $thumbnailPath;
            echo "   Accessible via public : " . (file_exists($publicPath) ? "✅" : "❌") . "\n";
        }
    } else {
        echo "❌ Génération de miniature a retourné null\n";
        error_log("TEST: Thumbnail generation returned null for: " . $storedName);
    }

} catch (\Exception $e) {
    echo "❌ Erreur lors de la génération : " . $e->getMessage() . "\n";
    error_log("TEST: Thumbnail generation error: " . $e->getMessage());
}

echo "\n";

// === NETTOYAGE ===
echo "--- NETTOYAGE ---\n";
if (file_exists($testCopy)) {
    unlink($testCopy);
    echo "✅ Fichier test supprimé\n";
}

if (isset($thumbnailPath) && $thumbnailPath) {
    $fullPath = __DIR__ . '/var/uploads/' . $thumbnailPath;
    if (file_exists($fullPath)) {
        unlink($fullPath);
        echo "✅ Miniature test supprimée\n";
    }
}

echo "\n=== CONCLUSION ===\n";
echo "Ce test reproduit exactement le flux d'upload :\n";
echo "1. Compression de l'image (réécriture du fichier)\n";
echo "2. Génération de miniature immédiatement après\n";
echo "\n";
echo "Si tout fonctionne ici mais pas dans l'application,\n";
echo "le problème vient probablement des permissions ou du contexte web.\n";
