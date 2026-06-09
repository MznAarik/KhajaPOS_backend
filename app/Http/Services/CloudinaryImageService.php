<?php

namespace App\Http\Services;

use Cloudinary\Cloudinary;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class CloudinaryImageService
{
    public function uploadMenuImage(UploadedFile $file): string
    {
        $cloudinary = $this->cloudinary();

        $result = $cloudinary->uploadApi()->upload($file->getRealPath(), [
            'folder' => config('cloudinary.folder'),
            'resource_type' => 'image',
        ]);

        return (string) ($result['secure_url'] ?? '');
    }

    public function deleteImage(?string $imageUrl): void
    {
        if (!$imageUrl) {
            return;
        }

        if ($this->isCloudinaryUrl($imageUrl)) {
            $publicId = $this->publicIdFromUrl($imageUrl);

            if ($publicId) {
                $this->cloudinary()->uploadApi()->destroy($publicId, [
                    'resource_type' => 'image',
                ]);
            }

            return;
        }

        if (Storage::disk('public')->exists($imageUrl)) {
            Storage::disk('public')->delete($imageUrl);
        }
    }

    private function cloudinary(): Cloudinary
    {
        $cloudName = config('cloudinary.cloud_name');
        $apiKey = config('cloudinary.api_key');
        $apiSecret = config('cloudinary.api_secret');

        if (!$cloudName || !$apiKey || !$apiSecret) {
            throw new RuntimeException('Cloudinary image upload is not configured.');
        }

        return new Cloudinary([
            'cloud' => [
                'cloud_name' => $cloudName,
                'api_key' => $apiKey,
                'api_secret' => $apiSecret,
            ],
            'url' => [
                'secure' => true,
            ],
        ]);
    }

    private function isCloudinaryUrl(string $url): bool
    {
        return str_contains($url, 'res.cloudinary.com/');
    }

    private function publicIdFromUrl(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);

        if (!$path || !str_contains($path, '/upload/')) {
            return null;
        }

        $publicPath = preg_replace('#^.*?/upload/#', '', $path);
        $parts = array_values(array_filter(explode('/', $publicPath), static fn ($part) => $part !== ''));

        while ($parts && (str_starts_with($parts[0], 'v') && ctype_digit(substr($parts[0], 1)))) {
            array_shift($parts);
        }

        if (!$parts) {
            return null;
        }

        $publicId = implode('/', $parts);

        return preg_replace('/\.[^.\/]+$/', '', $publicId);
    }
}
