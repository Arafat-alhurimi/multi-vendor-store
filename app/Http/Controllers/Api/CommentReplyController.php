<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Http\Request;

class CommentReplyController extends Controller
{
    public function store(Request $request, Comment $comment)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'غير مصرح.'], 401);
        }

        $isCommentOwner = (int) $comment->user_id === (int) $user->id;
        $isStoreOwner = false;

        $commentable = $comment->commentable;

        if ($commentable instanceof Store) {
            $isStoreOwner = (int) $commentable->user_id === (int) $user->id;
        }

        if ($commentable instanceof Product) {
            $isStoreOwner = (int) $commentable->store?->user_id === (int) $user->id;
        }

        if (! $isCommentOwner && ! $isStoreOwner) {
            return response()->json(['message' => 'الرد متاح فقط لصاحب التعليق أو صاحب المتجر.'], 403);
        }

        $data = $request->validate([
            'body' => 'required|string',
        ]);

        $reply = $comment->replies()->create([
            'body' => $data['body'],
            'user_id' => $user->id,
        ]);

        return response()->json([
            'message' => 'تمت إضافة الرد بنجاح',
            'reply' => $reply->load('user'),
        ], 201);
    }
}
