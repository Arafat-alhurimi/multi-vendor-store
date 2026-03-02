<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class AttributeValueProductVariant extends Pivot
{
    protected $table = 'attribute_value_product_variant';

    protected $fillable = [
        'product_variant_id',
        'attribute_value_id',
    ];
}
