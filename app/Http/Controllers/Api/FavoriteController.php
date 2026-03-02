<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    public function toggle(Request $request, ?Product $product = null, ?Store $store = null)
    {
        $user = $request->user();

        if (! $user || $user->role !== 'customer') {
            return response()->json(['message' => 'المفضلة متاحة للعملاء فقط.'], 403);
        }

        $target = $product ?? $store;

        if (! $target) {
            return response()->json(['message' => 'العنصر غير صالح للمفضلة.'], 422);
        }

        $existing = $target->favorites()->where('user_id', $user->id)->first();

        if ($existing) {
            $existing->delete();

            return response()->json([
                'message' => 'تمت إزالة العنصر من المفضلة',
                'is_favorite' => false,
            ]);
        }

        $target->favorites()->create([
            'user_id' => $user->id,
        ]);

        return response()->json([
            'message' => 'تمت إضافة العنصر إلى المفضلة',
            'is_favorite' => true,
        ], 201);
    }
}
