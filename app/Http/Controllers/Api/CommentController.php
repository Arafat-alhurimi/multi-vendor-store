<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function store(Request $request, ?Product $product = null, ?Store $store = null)
    {
        $user = $request->user();

        if (! $user || $user->role !== 'customer') {
            return response()->json(['message' => 'التعليقات متاحة للعملاء فقط.'], 403);
        }

        $target = $product ?? $store;

        if (! $target) {
            return response()->json(['message' => 'العنصر غير صالح للتعليق.'], 422);
        }

        $data = $request->validate([
            'body' => 'required|string',
        ]);

        $comment = $target->comments()->create([
            'body' => $data['body'],
            'user_id' => $user->id,
        ]);

        return response()->json([
            'message' => 'تم إضافة التعليق بنجاح',
            'comment' => $comment->load(['user', 'replies.user']),
        ], 201);
    }

    public function index(Request $request, ?Product $product = null, ?Store $store = null)
    {
        $target = $product ?? $store;

        if (! $target) {
            return response()->json(['message' => 'العنصر غير صالح.'], 422);
        }

        $comments = $target->comments()
            ->with(['user', 'replies.user'])
            ->latest()
            ->get();

        return response()->json([
            'comments' => $comments,
        ]);
    }
}
