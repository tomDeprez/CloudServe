<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Service\ThumbnailService;

echo "=== TEST UPLOAD & MINIATURE ===\n\n";

// Simuler un fichier uploadé
$uploadsDir = __DIR__ . '/var/uploads';
$images = glob($uploadsDir . '/*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE);

if (empty($images)) {
    echo "❌ Aucune image trouvée dans uploads\n";
    exit(1);
}

$testImage = basename($images[0]);
echo "Image de test : $testImage\n";

// Obtenir le type de fichier comme le ferait l'entité File
$extension = strtolower(pathinfo($testImage, PATHINFO_EXTENSION));
$mimeType = mime_content_type($uploadsDir . '/' . $testImage);

echo "Extension : $extension\n";
echo "MIME Type : $mimeType\n";

// Vérifier si c'est détecté comme image
$imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp'];
$isImage = in_array($extension, $imageExtensions) || str_starts_with($mimeType, 'image/');

echo "Détecté comme image : " . ($isImage ? "✅ OUI" : "❌ NON") . "\n\n";

if (!$isImage) {
    echo "⚠️  Le fichier n'est pas détecté comme une image !\n";
    echo "C'est probablement le problème.\n";
    exit(1);
}

// Tester la génération de miniature
echo "Test de génération de miniature...\n";

try {
    $thumbnailService = new ThumbnailService(__DIR__);

    echo "1. Service créé ✅\n";

    $result = $thumbnailService->generateThumbnail($testImage);

    if ($result) {
        echo "2. Génération réussie ✅\n";
        echo "   Chemin retourné : $result\n";

        $fullPath = __DIR__ . '/var/uploads/' . $result;
        if (file_exists($fullPath)) {
            echo "3. Fichier existe ✅\n";
            echo "   Taille : " . round(filesize($fullPath)/1024, 1) . " KB\n";

            // Vérifier s'il est accessible via public
            $publicPath = __DIR__ . '/public/uploads/' . $result;
            if (file_exists($publicPath)) {
                echo "4. Accessible via public ✅\n";
            } else {
                echo "4. Accessible via public ❌\n";
                echo "   Le fichier n'est pas accessible via l'URL publique\n";
            }
        } else {
            echo "3. Fichier existe ❌\n";
            echo "   Chemin : $fullPath\n";
        }
    } else {
        echo "2. Génération échouée ❌\n";
        echo "   Le service a retourné null\n";
    }

} catch (\Exception $e) {
    echo "❌ Erreur : " . $e->getMessage() . "\n";
    echo "Stack trace :\n";
    echo $e->getTraceAsString() . "\n";
}

echo "\n=== CONCLUSION ===\n";
echo "Si tout est ✅, le problème vient d'ailleurs dans le code d'upload.\n";
echo "Uploadez une image et vérifiez que le fichier apparaît bien dans var/uploads.\n";
