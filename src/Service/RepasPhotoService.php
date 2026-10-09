<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class RepasPhotoService
{
    private Filesystem $filesystem;

    public function __construct(
        #[Autowire('%kernel.project_dir%/public_html/photos/repas')]
        private readonly string $targetDirectory
    ) {
        $this->filesystem = new Filesystem();
    }

    /**
     * Enregistre un fichier photo uploadé et retourne son nom unique.
     *
     * @throws \InvalidArgumentException Si le fichier n'est pas une image valide
     * @throws FileException Si l'enregistrement échoue
     */
    public function upload(UploadedFile $file): string
    {
        $allowedMimeTypes = [
            'image/jpeg',
            'image/png',
            'image/webp',
            'image/gif',
            'image/avif',
            'image/heic',
            'image/heif',
        ];

        $mimeType = $file->getMimeType();
        if ($mimeType && !in_array(strtolower($mimeType), $allowedMimeTypes, true)) {
            throw new \InvalidArgumentException('Format d\'image non supporté (' . $mimeType . '). Formats acceptés : JPG, PNG, WEBP, HEIC, GIF, AVIF.');
        }

        $extension = $file->guessExtension() ?: $file->getClientOriginalExtension();
        $extension = strtolower((string) $extension);
        if ($extension === '' || $extension === 'bin') {
            $extension = 'jpg';
        }

        // Nom unique, horodaté et aléatoire pour éviter toute collision
        $safeFilename = sprintf('repas_%s_%s.%s', date('Ymd_His'), bin2hex(random_bytes(6)), $extension);

        if (!$this->filesystem->exists($this->targetDirectory)) {
            $this->filesystem->mkdir($this->targetDirectory, 0775);
        }

        $file->move($this->targetDirectory, $safeFilename);

        return $safeFilename;
    }

    /**
     * Supprime le fichier photo s'il existe localement.
     */
    public function delete(?string $photo): void
    {
        if ($photo === null || trim($photo) === '') {
            return;
        }

        // Si le nom correspond à une URL externe (ex. futur Cloudinary), ne rien supprimer en local
        if (str_starts_with($photo, 'http://') || str_starts_with($photo, 'https://')) {
            return;
        }

        // Sécurité supplémentaire contre le directory traversal
        $cleanFilename = basename($photo);
        $filePath = $this->targetDirectory . \DIRECTORY_SEPARATOR . $cleanFilename;

        if ($this->filesystem->exists($filePath)) {
            $this->filesystem->remove($filePath);
        }
    }

    /**
     * Retourne l'URL publique de la photo.
     */
    public function getUrl(?string $photo): ?string
    {
        if ($photo === null || trim($photo) === '') {
            return null;
        }

        if (str_starts_with($photo, 'http://') || str_starts_with($photo, 'https://')) {
            return $photo;
        }

        return '/photos/repas/' . rawurlencode($photo);
    }
}
