<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\VendorFinancialDetail;
use Illuminate\Http\Request;

class VendorFinancialDetailController extends Controller
{
    public function upsert(Request $request)
    {
        $authUser = $request->user();

        if (! $authUser) {
            return response()->json(['message' => 'غير مصرح.'], 401);
        }

        $data = $request->validate([
            'user_id' => 'nullable|integer|exists:users,id',
            'card_image' => 'nullable|string|max:2048',
            'back_card_image' => 'nullable|string|max:2048',
            'kuraimi_account_number' => 'nullable|string|max:50',
            'kuraimi_account_name' => 'nullable|string|max:255',
            'jeeb_id' => 'nullable|string|max:100',
            'jeeb_name' => 'nullable|string|max:255',
            'total_commission_owed' => 'nullable|numeric|min:0',
        ]);

        $targetUser = $authUser;

        if (isset($data['user_id'])) {
            if ($authUser->role !== 'admin') {
                return response()->json(['message' => 'غير مسموح لك إضافة تفاصيل مالية لمستخدم آخر.'], 403);
            }

            $targetUser = User::query()->findOrFail($data['user_id']);
        }

        if ($targetUser->role !== 'vendor') {
            return response()->json(['message' => 'يمكن إضافة التفاصيل المالية للبائع فقط.'], 422);
        }

        unset($data['user_id']);

        if ($authUser->role !== 'admin') {
            unset($data['total_commission_owed']);
        }

        if (! array_key_exists('card_image', $data) || $data['card_image'] === null) {
            $data['card_image'] = '';
        }

        if (! array_key_exists('back_card_image', $data) || $data['back_card_image'] === null) {
            $data['back_card_image'] = '';
        }

        $financialDetail = VendorFinancialDetail::query()->updateOrCreate(
            ['user_id' => $targetUser->id],
            $data
        );

        return response()->json([
            'message' => 'تم حفظ التفاصيل المالية بنجاح.',
            'financial_detail' => $financialDetail,
            'user_id' => $targetUser->id,
        ]);
    }
}
