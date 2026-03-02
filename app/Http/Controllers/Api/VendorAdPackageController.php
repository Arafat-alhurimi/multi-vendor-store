<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdPackage;
use Illuminate\Http\JsonResponse;

class VendorAdPackageController extends Controller
{
    public function index(): JsonResponse
    {
        $packages = AdPackage::query()
            ->where('is_active', true)
            ->orderBy('price')
            ->get();

        return response()->json([
            'message' => 'تم جلب الباقات الإعلانية بنجاح.',
            'packages' => $packages,
        ]);
    }
}
