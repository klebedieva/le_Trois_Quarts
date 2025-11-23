<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

class FileUploadValidator
{
    // Allowed MIME types for images
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/webp',
        'image/gif',
        'image/avif',
    ];

    // Allowed file extensions (must match MIME types)
    private const ALLOWED_EXTENSIONS = [
        'jpg',
        'jpeg',
        'png',
        'webp',
        'gif',
        'avif',
    ];

    // Maximum file size in bytes (5 MB default)
    private int $maxFileSize;

    public function __construct(int $maxFileSize = 5242880)
    {
        $this->maxFileSize = $maxFileSize; // 5 MB default
    }

    /**
     * Validate uploaded file: PHP upload errors, extension, size and MIME type
     *
     * @param UploadedFile $file
     * @throws FileException if validation fails
     */
    public function validate(UploadedFile $file): void
    {
        // First: handle low-level PHP upload errors to avoid heavy processing
        $errorCode = $file->getError();
        if ($errorCode !== UPLOAD_ERR_OK) {
            throw new FileException($this->translateUploadError($errorCode));
        }
        // Fast-fail: extension whitelist (cheap check before MIME inspection)
        $extension = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension());
        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new FileException(
                sprintf('Extension de fichier non autorisée : %s. Extensions autorisées : %s',
                    $extension,
                    implode(', ', self::ALLOWED_EXTENSIONS)
                )
            );
        }
        // Enforce size limit (server-side safety net)
        $size = $file->getSize();
        if ($size !== null && $size > $this->maxFileSize) {
            $maxSizeMB = round($this->maxFileSize / 1048576, 2);
            throw new FileException(
                sprintf('Le fichier est trop volumineux. Taille maximum autorisée : %s MB', $maxSizeMB)
            );
        }
        // Validate MIME type from file content (reliable check)
        $mimeType = $file->getMimeType();
        if (!$mimeType || !in_array(strtolower($mimeType), self::ALLOWED_MIME_TYPES, true)) {
            throw new FileException(
                sprintf('Type MIME non autorisé : %s. Types autorisés : %s',
                    $mimeType ?? 'inconnu',
                    implode(', ', self::ALLOWED_MIME_TYPES)
                )
            );
        }
        // Ensure extension matches MIME type
        $this->validateMimeExtensionMatch($mimeType, $extension);
    }

    /**
     * Convert PHP upload error codes to readable messages
     */
    private function translateUploadError(int $errorCode): string
    {
        return match ($errorCode) {
            UPLOAD_ERR_INI_SIZE => 'Le fichier est trop volumineux. Taille maximum autorisée : 5 MB',
            UPLOAD_ERR_FORM_SIZE => 'Le fichier est trop volumineux. Taille maximum autorisée : 5 MB',
            UPLOAD_ERR_PARTIAL => 'Le fichier n\'a été que partiellement téléchargé.',
            UPLOAD_ERR_NO_FILE => 'Aucun fichier n\'a été téléchargé.',
            UPLOAD_ERR_NO_TMP_DIR => 'Dossier temporaire manquant sur le serveur.',
            UPLOAD_ERR_CANT_WRITE => 'Impossible d\'écrire le fichier sur le disque.',
            UPLOAD_ERR_EXTENSION => 'Une extension PHP a interrompu le téléchargement du fichier.',
            default => 'Erreur de téléchargement du fichier (code: ' . $errorCode . ').',
        };
    }

    /**
     * Validate that MIME type matches file extension
     */
    private function validateMimeExtensionMatch(string $mimeType, string $extension): void
    {
        $mimeExtensionMap = [
            'image/jpeg' => ['jpg', 'jpeg'],
            'image/jpg' => ['jpg', 'jpeg'],
            'image/png' => ['png'],
            'image/webp' => ['webp'],
            'image/gif' => ['gif'],
            'image/avif' => ['avif'],
        ];

        if (isset($mimeExtensionMap[$mimeType]) && !in_array($extension, $mimeExtensionMap[$mimeType], true)) {
            throw new FileException(
                sprintf('Le type MIME %s ne correspond pas à l\'extension %s', $mimeType, $extension)
            );
        }
    }

    /**
     * Get maximum allowed file size
     */
    public function getMaxFileSize(): int
    {
        return $this->maxFileSize;
    }

    /**
     * Get allowed MIME types
     */
    public static function getAllowedMimeTypes(): array
    {
        return self::ALLOWED_MIME_TYPES;
    }

    /**
     * Get allowed extensions
     */
    public static function getAllowedExtensions(): array
    {
        return self::ALLOWED_EXTENSIONS;
    }
}

