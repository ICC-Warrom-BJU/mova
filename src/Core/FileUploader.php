<?php

/**
 * FileUploader — Centralized, secure file upload handler.
 * Follows MOVA-SECURITY.md §2.3:
 *  - Validates MIME type from file content (not filename extension)
 *  - Whitelists allowed types & extensions
 *  - Generates random, non-guessable filenames
 *  - Stores to organised subdirectories under public/uploads/
 */
class FileUploader
{
    private const MAX_SIZE_BYTES = 5 * 1024 * 1024; // 5 MB

    private const ALLOWED = [
        'image/jpeg'       => 'jpg',
        'image/png'        => 'png',
        'image/webp'       => 'webp',
        'application/pdf'  => 'pdf',
    ];

    private string $uploadRoot;

    public function __construct()
    {
        // Resolve absolute path to public/uploads from any module depth
        $this->uploadRoot = dirname(__DIR__, 3) . '/public/uploads';
    }

    /**
     * Upload a single file from $_FILES.
     *
     * @param  array  $file       Entry from $_FILES (e.g. $_FILES['stnk_photo'])
     * @param  string $category   Sub-folder name, e.g. 'stnk', 'trip_photos', 'kir'
     * @return string             Relative web path: "uploads/stnk/2025/07/abc123.jpg"
     * @throws RuntimeException   On any validation or write failure
     */
    public function upload(array $file, string $category = 'general'): string
    {
        if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Upload gagal: ' . $this->uploadErrorMessage($file['error'] ?? UPLOAD_ERR_NO_FILE));
        }

        // 1. Size check
        if ($file['size'] > self::MAX_SIZE_BYTES) {
            throw new RuntimeException('Ukuran file melebihi batas maksimum 5 MB.');
        }

        // 2. MIME check from file content (not extension)
        $finfo    = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);

        if (!array_key_exists($mimeType, self::ALLOWED)) {
            throw new RuntimeException('Tipe file tidak diizinkan. Gunakan JPG, PNG, WebP, atau PDF.');
        }

        $ext = self::ALLOWED[$mimeType];

        // 3. Build destination path
        $subDir = $this->uploadRoot . '/' . preg_replace('/[^a-z0-9_\-]/i', '', $category)
                . '/' . date('Y') . '/' . date('m');

        if (!is_dir($subDir) && !mkdir($subDir, 0755, true)) {
            throw new RuntimeException('Gagal membuat direktori upload.');
        }

        // 4. Generate safe, unique filename
        $filename = bin2hex(random_bytes(12)) . '_' . time() . '.' . $ext;
        $destPath = $subDir . '/' . $filename;

        // 5. Move file
        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            throw new RuntimeException('Gagal menyimpan file.');
        }

        // Return relative web path (relative to public/)
        return 'uploads/' . preg_replace('/[^a-z0-9_\-]/i', '', $category)
            . '/' . date('Y') . '/' . date('m') . '/' . $filename;
    }

    /**
     * Delete a previously uploaded file by its stored relative web path.
     */
    public function delete(string $relativePath): void
    {
        if (empty($relativePath)) {
            return;
        }
        // Sanitize: must start with 'uploads/'
        if (strpos($relativePath, 'uploads/') !== 0) {
            return;
        }
        $absPath = $this->uploadRoot . '/' . ltrim(substr($relativePath, strlen('uploads')), '/');
        if (file_exists($absPath) && is_file($absPath)) {
            @unlink($absPath);
        }
    }

    private function uploadErrorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Ukuran file terlalu besar.',
            UPLOAD_ERR_NO_FILE  => 'Tidak ada file yang dipilih.',
            UPLOAD_ERR_PARTIAL  => 'Upload tidak lengkap.',
            default             => 'Error tidak diketahui (kode ' . $code . ').',
        };
    }
}
