#!/usr/bin/env php
<?php

require __DIR__ . '/vendor/autoload.php';

use Cloudinary\Cloudinary;

$cloudName = 'dzpad15ji';
$apiKey = '438266428925177';
$apiSecret = 'XOKXFEH2-yyeAmzZupKYtxIp66s';

$cloudinary = new Cloudinary([
    'cloud' => [
        'cloud_name' => $cloudName,
        'api_key' => $apiKey,
        'api_secret' => $apiSecret,
    ],
    'url' => [
        'secure' => true,
    ],
]);

$sampleImageUrl = 'https://res.cloudinary.com/demo/image/upload/sample.jpg';

echo "Uploading sample image...\n";

$uploadResult = $cloudinary->uploadApi()->upload($sampleImageUrl, [
    'folder' => 'khajapos_onboarding',
    'use_filename' => true,
    'unique_filename' => true,
    'overwrite' => false,
]);

$secureUrl = $uploadResult['secure_url'] ?? '';
$publicId = $uploadResult['public_id'] ?? '';

echo "Uploaded secure URL: {$secureUrl}\n";
echo "Public ID: {$publicId}\n";

echo "Fetching image details...\n";

$details = $cloudinary->adminApi()->asset($publicId);

echo "Width: " . ($details['width'] ?? 'unknown') . "\n";
echo "Height: " . ($details['height'] ?? 'unknown') . "\n";
echo "Format: " . ($details['format'] ?? 'unknown') . "\n";
echo "File size bytes: " . ($details['bytes'] ?? 'unknown') . "\n";

$encodedPublicId = implode('/', array_map('rawurlencode', explode('/', $publicId)));

// f_auto lets Cloudinary choose the best image format for the browser.
// q_auto lets Cloudinary choose an optimized image quality automatically.
$transformedUrl = "https://res.cloudinary.com/{$cloudName}/image/upload/f_auto,q_auto/{$encodedPublicId}";

echo "Done! Click link below to see optimized version of the image. Check the size and the format.\n";
echo $transformedUrl . "\n";
