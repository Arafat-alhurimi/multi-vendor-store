<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AttributeController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\CommentReplyController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\FavoriteController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProductVariantResolverController;
use App\Http\Controllers\Api\ProductVariantController;
use App\Http\Controllers\Api\ProductVariantSuggestionController;
use App\Http\Controllers\Api\ProductSetupController;
use App\Http\Controllers\Api\RatingController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\StoreController;
use App\Http\Controllers\Api\UploadController;
use App\Http\Controllers\Api\VendorDiscountController;
use App\Http\Controllers\Api\VendorAdController;
use App\Http\Controllers\Api\VendorAdPackageController;
use App\Http\Controllers\Api\VendorOrderController;
use App\Http\Controllers\Api\VendorAdSubscriptionController;
use App\Http\Controllers\Api\VendorFinancialDetailController;
use App\Http\Controllers\Api\VendorPromotionController;
use App\Http\Controllers\Api\VendorStorePromotionController;
use App\Http\Controllers\Api\VendorOnboardingController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');



// مرحلة إرسال البيانات (قبل الـ OTP)
Route::post('/register', [AuthController::class, 'register']);
Route::post('/vendor/register-complete', [VendorOnboardingController::class, 'register']);
Route::post('/vendor/onboarding/presign', [VendorOnboardingController::class, 'presign']);
Route::post('/vendor/onboarding/presign-batch', [VendorOnboardingController::class, 'presignBatch']);

// تسجيل الدخول بالهاتف وكلمة المرور
Route::post('/login', [AuthController::class, 'login']);

// مرحلة التحقق من توكن فايربيز (بعد وصول الـ SMS للموبايل)
Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);

Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::get('/promotions/{promotion}/products', [VendorPromotionController::class, 'products']);
Route::get('/vendor/ad-packages', [VendorAdPackageController::class, 'index']);
Route::get('/ads/active', [VendorAdController::class, 'active']);
Route::get('/products/{product}/details', [ProductController::class, 'show']);
Route::get('/products/{product}/resolve-variant', [ProductVariantResolverController::class, 'resolve']);
Route::post('/products/{product}/resolve-variant', [ProductVariantResolverController::class, 'resolve']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/stores', [StoreController::class, 'store']);
    Route::post('/products', [ProductController::class, 'store']);
    Route::post('/products/setup', [ProductSetupController::class, 'store']);
    Route::post('/products/{product}/attributes', [AttributeController::class, 'store']);
    Route::get('/products/{product}/suggest-variants', [ProductVariantSuggestionController::class, 'suggest']);
    Route::post('/products/{product}/variants', [ProductVariantController::class, 'store']);
    Route::post('/upload', [UploadController::class, 'upload']);
    Route::post('/upload/presign', [UploadController::class, 'presign']);
    Route::post('/upload/presign-batch', [UploadController::class, 'presignBatch']);
    Route::post('/vendor-financial-details', [VendorFinancialDetailController::class, 'upsert']);
    Route::post('/vendor/subscribe', [VendorAdSubscriptionController::class, 'store']);
    Route::post('/vendor/subscriptions/{id}/renew', [VendorAdSubscriptionController::class, 'requestRenewal']);
    Route::post('/vendor/ads', [VendorAdController::class, 'store']);
    Route::post('/vendor/discounts', [VendorDiscountController::class, 'upsert']);
    Route::post('/vendor/promotions/join', [VendorPromotionController::class, 'join']);
    Route::post('/vendor/promotions/store', [VendorStorePromotionController::class, 'store']);
    Route::post('/vendor/promotions/store/{promotion}/deactivate', [VendorStorePromotionController::class, 'deactivate']);
    Route::get('/vendor/campaigns/available', [VendorPromotionController::class, 'availableCampaigns']);

    Route::post('/products/{product}/comments', [CommentController::class, 'store']);
    Route::post('/stores/{store}/comments', [CommentController::class, 'store']);
    Route::get('/products/{product}/comments', [CommentController::class, 'index']);
    Route::get('/stores/{store}/comments', [CommentController::class, 'index']);

    Route::post('/comments/{comment}/reply', [CommentReplyController::class, 'store']);

    Route::post('/products/{product}/rate', [RatingController::class, 'rate']);
    Route::post('/stores/{store}/rate', [RatingController::class, 'rate']);
    Route::put('/products/{product}/rate', [RatingController::class, 'update']);
    Route::put('/stores/{store}/rate', [RatingController::class, 'update']);

    Route::post('/products/{product}/favorite', [FavoriteController::class, 'toggle']);
    Route::post('/stores/{store}/favorite', [FavoriteController::class, 'toggle']);

    Route::post('/products/{product}/report', [ReportController::class, 'store']);
    Route::post('/stores/{store}/report', [ReportController::class, 'store']);
    Route::post('/comments/{comment}/report', [ReportController::class, 'store']);

    Route::get('/cart', [CartController::class, 'index']);
    Route::post('/cart/add', [CartController::class, 'add']);
    Route::delete('/cart/clear', [CartController::class, 'clear']);
    Route::patch('/cart/{id}', [CartController::class, 'update']);
    Route::delete('/cart/{id}', [CartController::class, 'remove']);

    Route::post('/order/checkout', [OrderController::class, 'checkout']);

    Route::patch('/vendor/orders/{id}/status', [VendorOrderController::class, 'updateStatus']);
});
