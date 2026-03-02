<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attribute;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttributeController extends Controller
{
    public function store(Request $request, Product $product)
    {
        $data = $request->validate([
            'attributes' => 'required|array|min:1',
            'attributes.*.name_ar' => 'required|string|max:255',
            'attributes.*.name_en' => 'required|string|max:255',
            'attributes.*.values' => 'required|array|min:1',
            'attributes.*.values.*.value_ar' => 'required|string|max:255',
            'attributes.*.values.*.value_en' => 'required|string|max:255',
        ]);

        DB::transaction(function () use ($data, $product) {
            foreach ($data['attributes'] as $attributeInput) {
                $attribute = Attribute::firstOrCreate([
                    'name_ar' => $attributeInput['name_ar'],
                    'name_en' => $attributeInput['name_en'],
                ]);

                foreach ($attributeInput['values'] as $valueInput) {
                    $attribute->values()->firstOrCreate([
                        'product_id' => $product->id,
                        'value_ar' => $valueInput['value_ar'],
                        'value_en' => $valueInput['value_en'],
                    ]);
                }
            }
        });

        return response()->json([
            'message' => 'تمت إضافة الخصائص والقيم للمنتج بنجاح',
            'attributes' => $product->attributeValues()->with('attribute')->get(),
        ], 201);
    }
}
