<?php

namespace App\Http\Controllers;

use App\Models\Media;
use App\Models\Store;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class S3DirectUploadController extends Controller
{
    public function signPut(Request $request): JsonResponse
    {
        $data = $request->validate([
            'filename' => ['required', 'string', 'max:255'],
            'mime_type' => ['required', 'string', 'max:255'],
            'directory' => ['required', 'string', 'in:stores/logos,stores/media/images,stores/media/videos'],
        ]);

        $safeName = preg_replace('/[^A-Za-z0-9._-]/', '-', $data['filename']) ?: 'file';
        $key = trim($data['directory'], '/') . '/' . Str::uuid() . '-' . $safeName;

        /** @var AwsS3V3Adapter $disk */
        $disk = Storage::disk('s3');
        $client = $disk->getClient();
        $bucket = config('filesystems.disks.s3.bucket');

        $command = $client->getCommand('PutObject', [
            'Bucket' => $bucket,
            'Key' => $key,
            'ContentType' => $data['mime_type'],
        ]);

        $request = $client->createPresignedRequest($command, '+20 minutes');

        return response()->json([
            'upload_url' => (string) $request->getUri(),
            'key' => $key,
            'public_url' => $disk->url($key),
        ]);
    }

    public function attachUploaded(Request $request): JsonResponse
    {
        $data = $request->validate([
            'store_id' => ['required', 'integer', 'exists:stores,id'],
            'key' => ['required', 'string', 'max:2048'],
            'file_name' => ['required', 'string', 'max:255'],
            'mime_type' => ['nullable', 'string', 'max:255'],
            'kind' => ['required', 'in:logo,image,video'],
        ]);

        try {
            $store = Store::query()->findOrFail($data['store_id']);

            if ($data['kind'] === 'logo') {
                $store->update(['logo' => $data['key']]);

                return response()->json(['ok' => true]);
            }

            $fileType = $data['kind'] === 'video' ? 'video' : 'image';

            /** @var AwsS3V3Adapter $s3 */
            $s3 = Storage::disk('s3');

            Media::query()->create([
                'file_name' => $data['file_name'],
                'file_type' => $fileType,
                'mime_type' => $data['mime_type'] ?? null,
                'url' => $s3->url($data['key']),
                'mediable_id' => $store->id,
                'mediable_type' => Store::class,
            ]);

            return response()->json(['ok' => true]);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'ok' => false,
                'message' => 'فشل ربط الملف بالمتجر.',
                'error' => $exception->getMessage(),
            ], 422);
        }
    }
}
