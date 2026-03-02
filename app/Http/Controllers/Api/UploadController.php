<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UploadController extends Controller
{
    public function presignBatch(Request $request)
    {
        $data = $request->validate([
            'files' => 'required|array|min:1',
            'files.*.file_name' => 'required|string|max:255',
            'files.*.mime_type' => 'required|string|max:255',
            'files.*.file_type' => 'nullable|in:image,video',
            'files.*.expires_in' => 'nullable|integer|min:120|max:7200',
        ]);

        return response()->json([
            'uploads' => collect($data['files'])->map(function (array $file) {
                $request = new Request($file);
                return $this->presign($request)->getData(true);
            })->values()->all(),
        ]);
    }

    public function presign(Request $request)
    {
        $data = $request->validate([
            'file_name' => 'required|string|max:255',
            'mime_type' => 'required|string|max:255',
            'file_type' => 'nullable|in:image,video',
            'expires_in' => 'nullable|integer|min:120|max:7200',
        ]);

        $mimeType = $data['mime_type'];
        $fileType = $data['file_type'] ?? null;

        if (! $fileType) {
            if (str_starts_with($mimeType, 'image/')) {
                $fileType = 'image';
            } elseif (str_starts_with($mimeType, 'video/')) {
                $fileType = 'video';
            }
        }

        if (! $fileType) {
            return response()->json(['message' => 'نوع الملف غير مدعوم، فقط صور أو فيديو.'], 422);
        }

        $defaultExpiresIn = $fileType === 'video' ? 3600 : 900;
        $expiresIn = (int) ($data['expires_in'] ?? $defaultExpiresIn);

        $originalName = basename($data['file_name']);
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $baseName = pathinfo($originalName, PATHINFO_FILENAME);
        $safeName = trim(Str::slug($baseName)) ?: 'file';
        $finalName = $safeName . ($extension ? '.' . $extension : '');

        $directory = $fileType === 'image' ? 'products/images' : 'products/videos';
        $key = $directory . '/' . Str::uuid() . '-' . $finalName;

        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('s3');
        $client = $disk->getClient();
        $bucket = config('filesystems.disks.s3.bucket');

        $command = $client->getCommand('PutObject', [
            'Bucket' => $bucket,
            'Key' => $key,
            'ContentType' => $mimeType,
        ]);

        $presigned = $client->createPresignedRequest($command, now()->addSeconds($expiresIn));

        return response()->json([
            'upload_url' => (string) $presigned->getUri(),
            'file_url' => $disk->url($key),
            'key' => $key,
            'file_type' => $fileType,
            'mime_type' => $mimeType,
            'expires_in' => $expiresIn,
        ]);
    }

    public function upload(Request $request)
    {
        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'file' => 'required|file|max:51200',
        ]);

        $file = $request->file('file');
        $mimeType = $file->getMimeType() ?: 'application/octet-stream';

        $fileType = str_starts_with($mimeType, 'image/')
            ? 'image'
            : (str_starts_with($mimeType, 'video/') ? 'video' : null);

        if (! $fileType) {
            return response()->json(['message' => 'نوع الملف غير مدعوم، فقط صور أو فيديو.'], 422);
        }

        $directory = $fileType === 'image' ? 'products/images' : 'products/videos';
        $path = $file->store($directory, 's3');

        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('s3');
        $url = $disk->url($path);

        $product = Product::findOrFail($data['product_id']);

        $media = $product->media()->create([
            'file_name' => $file->getClientOriginalName(),
            'file_type' => $fileType,
            'mime_type' => $mimeType,
            'url' => $url,
        ]);

        return response()->json([
            'message' => 'تم رفع الملف بنجاح',
            'media' => $media,
        ], 201);
    }
}
