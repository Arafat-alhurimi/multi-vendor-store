<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class StoreController extends Controller
{
    public function store(Request $request)
    {
        $user = $request->user();

        if (! $user || $user->role !== 'vendor') {
            return response()->json(['message' => 'لا يمكن إنشاء متجر إلا للبائع.'], 403);
        }

        if ($user->stores()->exists()) {
            return response()->json(['message' => 'لا يمكن إنشاء أكثر من متجر لنفس البائع.'], 422);
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'city' => 'required|string|max:255',
            'address' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'logo' => 'nullable|image|max:4096',
            'categories' => 'nullable|array',
            'categories.*' => 'integer|exists:categories,id',
            'is_active' => 'nullable|boolean',
        ]);

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('stores', 's3');
            /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
            $disk = Storage::disk('s3');
            $data['logo'] = $disk->url($path);
        }

        $data['user_id'] = $user->id;
        $data['is_active'] = $user->is_active && ($request->boolean('is_active', true));

        $store = DB::transaction(function () use ($data) {
            $categories = $data['categories'] ?? [];
            unset($data['categories']);

            $store = Store::create($data);

            if (! empty($categories)) {
                $store->categories()->sync($categories);
            }

            return $store;
        });

        return response()->json([
            'message' => 'تم إنشاء المتجر بنجاح',
            'store' => $store->load('categories'),
        ], 201);
    }
}
