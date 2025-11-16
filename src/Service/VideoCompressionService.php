<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

class VideoCompressionService
{
    private string $ffmpegPath;

    // Paramètres de compression optimaux
    private const MAX_WIDTH = 1920;  // Full HD max
    private const MAX_HEIGHT = 1080;
    private const VIDEO_BITRATE = '2M';  // 2 Mbps - bon compromis qualité/taille
    private const AUDIO_BITRATE = '128k';  // 128 kbps pour l'audio
    private const CRF = '23';  // Constant Rate Factor (18-28, 23 = bon compromis)

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir
    ) {
        $this->ffmpegPath = $this->findFFmpeg();
    }

    /**
     * Trouver l'exécutable FFmpeg
     */
    private function findFFmpeg(): string
    {
        // Essayer différents chemins communs
        $paths = [
            'ffmpeg',  // Dans le PATH
            '/usr/bin/ffmpeg',
            '/usr/local/bin/ffmpeg',
            'C:\\ffmpeg\\bin\\ffmpeg.exe',  // Windows
        ];

        foreach ($paths as $path) {
            if ($this->commandExists($path)) {
                return $path;
            }
        }

        return 'ffmpeg';  // Par défaut, espérer qu'il est dans le PATH
    }

    /**
     * Vérifier si une commande existe
     */
    private function commandExists(string $command): bool
    {
        $test = shell_exec(sprintf('which %s 2>/dev/null || where %s 2>nul', escapeshellarg($command), escapeshellarg($command)));
        return !empty($test);
    }

    /**
     * Vérifier si FFmpeg est disponible
     */
    public function isAvailable(): bool
    {
        $output = shell_exec($this->ffmpegPath . ' -version 2>&1');
        return $output !== null && strpos($output, 'ffmpeg version') !== false;
    }

    /**
     * Vérifier si un fichier est une vidéo compressible
     */
    public function isCompressibleVideo(string $mimeType): bool
    {
        return in_array($mimeType, [
            'video/mp4',
            'video/mpeg',
            'video/quicktime',
            'video/x-msvideo',
            'video/x-matroska',
            'video/webm',
        ]);
    }

    /**
     * Compresser une vidéo
     *
     * @param string $sourcePath Chemin de la vidéo source
     * @param string $destinationPath Chemin de destination
     * @return bool True si la compression a réussi
     */
    public function compressVideo(string $sourcePath, string $destinationPath): bool
    {
        if (!$this->isAvailable()) {
            error_log('FFmpeg is not available. Video compression skipped.');
            return false;
        }

        if (!file_exists($sourcePath)) {
            error_log("Source video file not found: $sourcePath");
            return false;
        }

        try {
            // Obtenir les informations de la vidéo
            $info = $this->getVideoInfo($sourcePath);

            if (!$info) {
                error_log("Could not get video info for: $sourcePath");
                return false;
            }

            // Construire la commande FFmpeg
            $scale = '';
            if ($info['width'] > self::MAX_WIDTH || $info['height'] > self::MAX_HEIGHT) {
                $scale = sprintf('-vf "scale=%d:%d:force_original_aspect_ratio=decrease"',
                    self::MAX_WIDTH, self::MAX_HEIGHT);
            }

            // Utiliser H.264 avec CRF pour une compression optimale
            $command = sprintf(
                '%s -i %s -c:v libx264 -preset medium -crf %s %s -c:a aac -b:a %s -movflags +faststart -y %s 2>&1',
                $this->ffmpegPath,
                escapeshellarg($sourcePath),
                self::CRF,
                $scale,
                self::AUDIO_BITRATE,
                escapeshellarg($destinationPath)
            );

            exec($command, $output, $returnCode);

            if ($returnCode === 0 && file_exists($destinationPath)) {
                // Vérifier que la compression a réduit la taille
                $originalSize = filesize($sourcePath);
                $compressedSize = filesize($destinationPath);

                if ($compressedSize < $originalSize) {
                    $reduction = round((1 - $compressedSize / $originalSize) * 100, 1);
                    error_log("Video compressed: $sourcePath -> $destinationPath (Reduction: {$reduction}%)");
                    return true;
                } else {
                    // La compression n'a pas réduit la taille, garder l'original
                    error_log("Compressed video is larger, keeping original");
                    @unlink($destinationPath);
                    return false;
                }
            }

            error_log("FFmpeg compression failed. Return code: $returnCode");
            return false;

        } catch (\Exception $e) {
            error_log("Video compression error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtenir les informations d'une vidéo
     */
    private function getVideoInfo(string $path): ?array
    {
        $command = sprintf(
            '%s -i %s 2>&1',
            $this->ffmpegPath,
            escapeshellarg($path)
        );

        $output = shell_exec($command);

        if (!$output) {
            return null;
        }

        // Extraire la résolution
        if (preg_match('/(\d{2,5})x(\d{2,5})/', $output, $matches)) {
            return [
                'width' => (int)$matches[1],
                'height' => (int)$matches[2],
            ];
        }

        return null;
    }
}
