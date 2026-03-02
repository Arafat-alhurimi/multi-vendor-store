<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Http\Request;

class RatingController extends Controller
{
    public function rate(Request $request, ?Product $product = null, ?Store $store = null)
    {
        $user = $request->user();

        if (! $user || $user->role !== 'customer') {
            return response()->json(['message' => 'التقييم متاح للعملاء فقط.'], 403);
        }

        $target = $product ?? $store;

        if (! $target) {
            return response()->json(['message' => 'العنصر غير صالح للتقييم.'], 422);
        }

        $data = $request->validate([
            'value' => 'required|integer|min:1|max:5',
        ]);

        $existing = $target->ratings()->where('user_id', $user->id)->first();

        if ($existing) {
            return response()->json(['message' => 'تم تقييم هذا العنصر مسبقاً. استخدم تحديث التقييم.'], 422);
        }

        $rating = $target->ratings()->create([
            'value' => $data['value'],
            'user_id' => $user->id,
        ]);

        return response()->json([
            'message' => 'تمت إضافة التقييم بنجاح',
            'rating' => $rating,
        ], 201);
    }

    public function update(Request $request, ?Product $product = null, ?Store $store = null)
    {
        $user = $request->user();

        if (! $user || $user->role !== 'customer') {
            return response()->json(['message' => 'تعديل التقييم متاح للعملاء فقط.'], 403);
        }

        $target = $product ?? $store;

        if (! $target) {
            return response()->json(['message' => 'العنصر غير صالح للتقييم.'], 422);
        }

        $data = $request->validate([
            'value' => 'required|integer|min:1|max:5',
        ]);

        $rating = $target->ratings()->where('user_id', $user->id)->first();

        if (! $rating) {
            return response()->json(['message' => 'لا يوجد تقييم سابق لتعديله.'], 404);
        }

        $rating->update(['value' => $data['value']]);

        return response()->json([
            'message' => 'تم تحديث التقييم بنجاح',
            'rating' => $rating,
        ]);
    }
}
