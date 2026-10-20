<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ActionPhotoService
{
    private Filesystem $filesystem;

    public function __construct(
        #[Autowire('%kernel.project_dir%/public_html/photos/actions')]
        private readonly string $targetDirectory
    ) {
        $this->filesystem = new Filesystem();
        if (!$this->filesystem->exists($this->targetDirectory)) {
            $this->filesystem->mkdir($this->targetDirectory);
        }
    }

    /**
     * Enregistre un fichier photo d'action uploadé et retourne son nom unique.
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

        $safeFilename = sprintf('action_%s_%s.%s', date('Ymd_His'), bin2hex(random_bytes(6)), $extension);

        try {
            $file->move($this->targetDirectory, $safeFilename);
        } catch (\Exception $e) {
            throw new FileException('Erreur lors de l\'enregistrement de la photo : ' . $e->getMessage(), 0, $e);
        }

        return $safeFilename;
    }

    /**
     * Supprime le fichier photo du disque s'il existe.
     */
    public function delete(?string $filename): void
    {
        if ($filename === null || $filename === '') {
            return;
        }

        $filePath = $this->targetDirectory . '/' . basename($filename);
        if ($this->filesystem->exists($filePath)) {
            $this->filesystem->remove($filePath);
        }
    }

    public function getTargetDirectory(): string
    {
        return $this->targetDirectory;
    }

    public function getUrl(?string $photo): ?string
    {
        if ($photo === null || $photo === '') {
            return null;
        }

        if (str_starts_with($photo, 'http://') || str_starts_with($photo, 'https://')) {
            return $photo;
        }

        return '/photos/actions/' . rawurlencode($photo);
    }
}
