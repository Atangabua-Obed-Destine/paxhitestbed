<?php

namespace App\Traits;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;

trait SecureFileUpload
{
    /**
     * Securely upload a file with comprehensive validation
     *
     * @param Request $request
     * @param string $fieldName
     * @param string $path
     * @param int|null $maxSize Max size in KB (default 10MB)
     * @return string|null
     */
    public function secureUpload(Request $request, string $fieldName, string $path, ?int $maxSize = 10240): ?string
    {
        if (!$request->hasFile($fieldName)) {
            return null;
        }

        $file = $request->file($fieldName);

        if (!$file->isValid()) {
            throw new \Exception("Invalid file upload for {$fieldName}");
        }

        // Check file size (in bytes)
        if ($file->getSize() > ($maxSize * 1024)) {
            throw new \Exception("File size exceeds maximum allowed size of {$maxSize}KB");
        }

        // Get MIME type
        $mimeType = $file->getMimeType();
        $extension = $file->getClientOriginalExtension();

        // Allowed MIME types with their extensions
        $allowedTypes = [
            // Images
            'image/jpeg' => ['jpg', 'jpeg'],
            'image/png' => ['png'],
            'image/gif' => ['gif'],
            'image/webp' => ['webp'],
            // Documents
            'application/pdf' => ['pdf'],
            'application/msword' => ['doc'],
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx'],
            'application/vnd.ms-excel' => ['xls'],
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => ['xlsx'],
            'text/plain' => ['txt'],
            'text/csv' => ['csv'],
            // Archives
            'application/zip' => ['zip'],
            'application/x-rar-compressed' => ['rar'],
        ];

        // Validate MIME type
        if (!isset($allowedTypes[$mimeType])) {
            throw new \Exception("File type not allowed: {$mimeType}");
        }

        // Validate extension matches MIME type
        if (!in_array(strtolower($extension), $allowedTypes[$mimeType])) {
            throw new \Exception("File extension does not match content type");
        }

        // Generate secure filename
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeName = Str::slug($originalName);
        $randomString = Str::random(16);
        $timestamp = time();
        $secureFilename = "{$safeName}_{$timestamp}_{$randomString}.{$extension}";

        // Create directory if doesn't exist
        $uploadPath = public_path("uploads/{$path}");
        if (!File::exists($uploadPath)) {
            File::makeDirectory($uploadPath, 0755, true);
        }

        // Move file to secure location
        $file->move($uploadPath, $secureFilename);

        return $secureFilename;
    }

    /**
     * Securely upload an image with resize option
     *
     * @param Request $request
     * @param string $fieldName
     * @param string $path
     * @param int|null $width
     * @param int|null $height
     * @param int|null $maxSize Max size in KB (default 5MB for images)
     * @return string|null
     */
    public function secureImageUpload(Request $request, string $fieldName, string $path, ?int $width = null, ?int $height = null, ?int $maxSize = 5120): ?string
    {
        if (!$request->hasFile($fieldName)) {
            return null;
        }

        $file = $request->file($fieldName);

        if (!$file->isValid()) {
            throw new \Exception("Invalid image upload for {$fieldName}");
        }

        // Check file size
        if ($file->getSize() > ($maxSize * 1024)) {
            throw new \Exception("Image size exceeds maximum allowed size of {$maxSize}KB");
        }

        // Validate it's actually an image
        $mimeType = $file->getMimeType();
        $allowedImageTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

        if (!in_array($mimeType, $allowedImageTypes)) {
            throw new \Exception("File must be an image (JPEG, PNG, GIF, or WebP)");
        }

        // Validate extension
        $extension = strtolower($file->getClientOriginalExtension());
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        if (!in_array($extension, $allowedExtensions)) {
            throw new \Exception("Invalid image file extension");
        }

        // Generate secure filename
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeName = Str::slug($originalName);
        $randomString = Str::random(16);
        $timestamp = time();
        $secureFilename = "{$safeName}_{$timestamp}_{$randomString}.{$extension}";

        // Create directory if doesn't exist
        $uploadPath = public_path("uploads/{$path}");
        if (!File::exists($uploadPath)) {
            File::makeDirectory($uploadPath, 0755, true);
        }

        $fullPath = "{$uploadPath}/{$secureFilename}";

        // Resize if dimensions provided
        if ($width && $height) {
            $img = Image::make($file->getRealPath());
            $img->fit($width, $height)->save($fullPath);
        } else {
            $file->move($uploadPath, $secureFilename);
        }

        return $secureFilename;
    }

    /**
     * Delete a file securely
     *
     * @param string|null $filename
     * @param string $path
     * @return bool
     */
    public function secureDelete(?string $filename, string $path): bool
    {
        if (!$filename) {
            return false;
        }

        $filePath = public_path("uploads/{$path}/{$filename}");

        if (File::exists($filePath)) {
            return File::delete($filePath);
        }

        return false;
    }
}
