<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use App\Models\Store;

class SearchController extends Controller
{
    public function search(Request $request)
    {
        $query = $request->input('query');
        if (!$query) {
            return response()->json([
                'status' => true,
                'message' => 'يرجى إدخال كلمة للبحث',
                'products' => [],
                'categories' => [],
                'stores' => [],
                'ads' => [],
                'promotions' => [],
            ]);
        }

        // Products (name, description)
        $products = Product::where(function($q) use ($query) {
            $q->where('name_ar', 'like', "%$query%")
              ->orWhere('name_en', 'like', "%$query%")
              ->orWhere('description_ar', 'like', "%$query%")
              ->orWhere('description_en', 'like', "%$query%")
              ;
        })->get();

        // Categories (main & sub)
        $categories = Category::where(function($q) use ($query) {
            $q->where('name_ar', 'like', "%$query%")
              ->orWhere('name_en', 'like', "%$query%")
              ;
        })->get();

        // Stores
        $stores = Store::where(function($q) use ($query) {
            $q->where('name', 'like', "%$query%")
              ->orWhere('description', 'like', "%$query%")
              ;
        })->get();

        // Ads




        return response()->json([
            'status' => true,
            'message' => 'تم البحث بنجاح',
            'products' => $products,
            'categories' => $categories,
            'stores' => $stores,
        ]);
    }
}
