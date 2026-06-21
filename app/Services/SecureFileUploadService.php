<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\SecuritySetting;

class SecureFileUploadService
{
    /**
     * Allowed MIME types mapped to extensions
     */
    protected static $allowedMimeTypes = [
        // Images
        'image/jpeg' => ['jpg', 'jpeg'],
        'image/png' => ['png'],
        'image/gif' => ['gif'],
        'image/webp' => ['webp'],
        'image/bmp' => ['bmp'],
        
        // Documents
        'application/pdf' => ['pdf'],
        'application/msword' => ['doc'],
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => ['docx'],
        'application/vnd.ms-excel' => ['xls'],
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => ['xlsx'],
        'application/vnd.ms-powerpoint' => ['ppt'],
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => ['pptx'],
        'text/plain' => ['txt'],
        'text/csv' => ['csv'],
        
        // Archives
        'application/zip' => ['zip'],
        'application/x-rar-compressed' => ['rar'],
        'application/x-7z-compressed' => ['7zip'],
        
        // Audio/Video
        'audio/mpeg' => ['mp3'],
        'video/mp4' => ['mp4'],
        'video/mpeg' => ['mpeg'],
        'video/x-msvideo' => ['avi'],
        'video/quicktime' => ['mov'],
    ];

    /**
     * Dangerous file extensions that should never be allowed
     */
    protected static $blockedExtensions = [
        'php', 'php3', 'php4', 'php5', 'phtml', 'phar',
        'exe', 'bat', 'cmd', 'com', 'sh', 'bash',
        'js', 'jar', 'vbs', 'scr', 'msi', 'app',
        'deb', 'rpm', 'dmg', 'iso',
    ];

    /**
     * Upload a file securely with validation
     *
     * @param UploadedFile $file
     * @param string $directory Directory within storage (e.g., 'student/photos')
     * @param array $options Additional options (allowed_types, max_size, etc.)
     * @return array ['success' => bool, 'path' => string|null, 'message' => string]
     */
    public static function upload(UploadedFile $file, string $directory, array $options = []): array
    {
        try {
            // Get max file size from settings (in MB)
            $maxSizeMB = SecuritySetting::getValue('max_upload_size_mb', 10);
            $maxSizeBytes = $maxSizeMB * 1024 * 1024;

            // Validate file size
            if ($file->getSize() > $maxSizeBytes) {
                return [
                    'success' => false,
                    'path' => null,
                    'message' => "File size exceeds maximum allowed size of {$maxSizeMB}MB.",
                ];
            }

            // Get file extension
            $extension = strtolower($file->getClientOriginalExtension());

            // Check if extension is blocked
            if (in_array($extension, self::$blockedExtensions)) {
                return [
                    'success' => false,
                    'path' => null,
                    'message' => "File type '{$extension}' is not allowed for security reasons.",
                ];
            }

            // Get MIME type
            $mimeType = $file->getMimeType();

            // Validate MIME type
            $allowed = $options['allowed_types'] ?? array_keys(self::$allowedMimeTypes);
            if (!in_array($mimeType, $allowed)) {
                return [
                    'success' => false,
                    'path' => null,
                    'message' => "File type '{$mimeType}' is not allowed.",
                ];
            }

            // Verify extension matches MIME type
            if (isset(self::$allowedMimeTypes[$mimeType])) {
                $validExtensions = self::$allowedMimeTypes[$mimeType];
                if (!in_array($extension, $validExtensions)) {
                    return [
                        'success' => false,
                        'path' => null,
                        'message' => "File extension does not match file type.",
                    ];
                }
            }

            // Generate secure random filename
            $randomName = Str::random(40);
            $filename = $randomName . '.' . $extension;

            // Store file in storage/app/private (not publicly accessible)
            $path = $file->storeAs($directory, $filename, 'private');

            return [
                'success' => true,
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'filename' => $filename,
                'size' => $file->getSize(),
                'mime_type' => $mimeType,
                'message' => 'File uploaded successfully.',
            ];

        } catch (\Exception $e) {
            \Log::error('Secure file upload failed', [
                'error' => $e->getMessage(),
                'file' => $file->getClientOriginalName(),
            ]);

            return [
                'success' => false,
                'path' => null,
                'message' => 'File upload failed. Please try again.',
            ];
        }
    }

    /**
     * Get a file from secure storage
     *
     * @param string $path
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public static function download(string $path)
    {
        if (!Storage::disk('private')->exists($path)) {
            abort(404, 'File not found.');
        }

        return Storage::disk('private')->download($path);
    }

    /**
     * Delete a file from secure storage
     *
     * @param string $path
     * @return bool
     */
    public static function delete(string $path): bool
    {
        if (Storage::disk('private')->exists($path)) {
            return Storage::disk('private')->delete($path);
        }

        return false;
    }

    /**
     * Check if file exists in secure storage
     *
     * @param string $path
     * @return bool
     */
    public static function exists(string $path): bool
    {
        return Storage::disk('private')->exists($path);
    }

    /**
     * Get allowed MIME types for specific category
     *
     * @param string $category (images, documents, archives, media, all)
     * @return array
     */
    public static function getAllowedMimeTypes(string $category = 'all'): array
    {
        $categories = [
            'images' => [
                'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp',
            ],
            'documents' => [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.ms-powerpoint',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'text/plain',
                'text/csv',
            ],
            'archives' => [
                'application/zip',
                'application/x-rar-compressed',
                'application/x-7z-compressed',
            ],
            'media' => [
                'audio/mpeg',
                'video/mp4',
                'video/mpeg',
                'video/x-msvideo',
                'video/quicktime',
            ],
        ];

        if ($category === 'all') {
            return array_keys(self::$allowedMimeTypes);
        }

        return $categories[$category] ?? [];
    }

    /**
     * Validate file without uploading
     *
     * @param UploadedFile $file
     * @param array $options
     * @return array ['valid' => bool, 'message' => string]
     */
    public static function validate(UploadedFile $file, array $options = []): array
    {
        $maxSizeMB = $options['max_size_mb'] ?? SecuritySetting::getValue('max_upload_size_mb', 10);
        $maxSizeBytes = $maxSizeMB * 1024 * 1024;

        if ($file->getSize() > $maxSizeBytes) {
            return [
                'valid' => false,
                'message' => "File size exceeds maximum allowed size of {$maxSizeMB}MB.",
            ];
        }

        $extension = strtolower($file->getClientOriginalExtension());
        if (in_array($extension, self::$blockedExtensions)) {
            return [
                'valid' => false,
                'message' => "File type '{$extension}' is not allowed for security reasons.",
            ];
        }

        $mimeType = $file->getMimeType();
        $allowed = $options['allowed_types'] ?? array_keys(self::$allowedMimeTypes);
        
        if (!in_array($mimeType, $allowed)) {
            return [
                'valid' => false,
                'message' => "File type is not allowed.",
            ];
        }

        return [
            'valid' => true,
            'message' => 'File is valid.',
        ];
    }
}
