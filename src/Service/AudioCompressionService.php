<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

class AudioCompressionService
{
    private string $ffmpegPath;

    // Paramètres de compression optimaux
    private const AUDIO_BITRATE = '192k';  // 192 kbps - excellente qualité
    private const SAMPLE_RATE = '44100';    // 44.1 kHz - qualité CD

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
            'ffmpeg',
            '/usr/bin/ffmpeg',
            '/usr/local/bin/ffmpeg',
            'C:\\ffmpeg\\bin\\ffmpeg.exe',
        ];

        foreach ($paths as $path) {
            if ($this->commandExists($path)) {
                return $path;
            }
        }

        return 'ffmpeg';
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
     * Vérifier si un fichier est un audio compressible
     */
    public function isCompressibleAudio(string $mimeType): bool
    {
        return in_array($mimeType, [
            'audio/mpeg',
            'audio/wav',
            'audio/x-wav',
            'audio/wave',
            'audio/aac',
            'audio/flac',
            'audio/ogg',
            'audio/webm',
            'audio/x-m4a',
        ]);
    }

    /**
     * Compresser un fichier audio
     *
     * @param string $sourcePath Chemin de l'audio source
     * @param string $destinationPath Chemin de destination
     * @return bool True si la compression a réussi
     */
    public function compressAudio(string $sourcePath, string $destinationPath): bool
    {
        if (!$this->isAvailable()) {
            error_log('FFmpeg is not available. Audio compression skipped.');
            return false;
        }

        if (!file_exists($sourcePath)) {
            error_log("Source audio file not found: $sourcePath");
            return false;
        }

        try {
            // Convertir en AAC avec bitrate optimal
            $command = sprintf(
                '%s -i %s -c:a aac -b:a %s -ar %s -y %s 2>&1',
                $this->ffmpegPath,
                escapeshellarg($sourcePath),
                self::AUDIO_BITRATE,
                self::SAMPLE_RATE,
                escapeshellarg($destinationPath)
            );

            exec($command, $output, $returnCode);

            if ($returnCode === 0 && file_exists($destinationPath)) {
                // Vérifier que la compression a réduit la taille
                $originalSize = filesize($sourcePath);
                $compressedSize = filesize($destinationPath);

                if ($compressedSize < $originalSize) {
                    $reduction = round((1 - $compressedSize / $originalSize) * 100, 1);
                    error_log("Audio compressed: $sourcePath -> $destinationPath (Reduction: {$reduction}%)");
                    return true;
                } else {
                    // La compression n'a pas réduit la taille, garder l'original
                    error_log("Compressed audio is larger, keeping original");
                    @unlink($destinationPath);
                    return false;
                }
            }

            error_log("FFmpeg audio compression failed. Return code: $returnCode");
            return false;

        } catch (\Exception $e) {
            error_log("Audio compression error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Compresser en utilisant Opus (meilleure compression)
     * Nécessite libopus dans FFmpeg
     */
    public function compressAudioOpus(string $sourcePath, string $destinationPath): bool
    {
        if (!$this->isAvailable()) {
            return false;
        }

        try {
            // Convertir en Opus avec bitrate optimal
            $command = sprintf(
                '%s -i %s -c:a libopus -b:a 128k -vbr on -y %s 2>&1',
                $this->ffmpegPath,
                escapeshellarg($sourcePath),
                escapeshellarg($destinationPath)
            );

            exec($command, $output, $returnCode);

            if ($returnCode === 0 && file_exists($destinationPath)) {
                $originalSize = filesize($sourcePath);
                $compressedSize = filesize($destinationPath);

                if ($compressedSize < $originalSize) {
                    return true;
                } else {
                    @unlink($destinationPath);
                    return false;
                }
            }

            return false;

        } catch (\Exception $e) {
            error_log("Opus compression error: " . $e->getMessage());
            return false;
        }
    }
}
