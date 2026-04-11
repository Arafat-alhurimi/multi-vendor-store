<?php

namespace Tests\Feature;

use App\Models\Ad;
use App\Models\Category;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\PromotionItem;
use App\Models\Store;
use App\Models\Subcategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_home_data_without_auth_and_includes_all_sections(): void
    {
        [$firstCategory, $firstSubcategory, $firstStore, $firstProduct] = $this->createCatalog('إلكترونيات', 'هواتف', 'متجر التقنية', 'هاتف ذكي');
        [$secondCategory, $secondSubcategory, $secondStore, $secondProduct] = $this->createCatalog('أزياء', 'أحذية', 'متجر الأناقة', 'حذاء رياضي');

        Ad::query()->create([
            'media_type' => 'image',
            'media_path' => 'https://example.com/ad-1.jpg',
            'click_action' => 'store',
            'action_id' => (string) $firstStore->id,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'is_active' => true,
        ]);

        $firstPromotion = Promotion::query()->create([
            'title' => 'عرض الإلكترونيات',
            'level' => 'app',
            'discount_type' => 'percentage',
            'discount_value' => 15,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'is_active' => true,
        ]);

        PromotionItem::query()->create([
            'promotion_id' => $firstPromotion->id,
            'store_id' => $firstStore->id,
            'promotable_type' => Category::class,
            'promotable_id' => $firstCategory->id,
            'status' => 'approved',
        ]);

        $secondPromotion = Promotion::query()->create([
            'title' => 'عرض الأزياء',
            'level' => 'app',
            'discount_type' => 'fixed',
            'discount_value' => 20,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'is_active' => true,
        ]);

        PromotionItem::query()->create([
            'promotion_id' => $secondPromotion->id,
            'store_id' => $secondStore->id,
            'promotable_type' => Category::class,
            'promotable_id' => $secondCategory->id,
            'status' => 'approved',
        ]);

        $response = $this->getJson('/api/home');

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('message', 'تم جلب بيانات الصفحة الرئيسية بنجاح.')
            ->assertJsonPath('filters.category_id', null)
            ->assertJsonPath('filters.language', 'ar')
            ->assertJsonPath('categories_type', 'categories')
            ->assertJsonCount(1, 'ads')
            ->assertJsonCount(2, 'categories')
            ->assertJsonCount(2, 'promotions')
            ->assertJsonCount(2, 'products.data')
            ->assertJsonCount(2, 'stores')
            ->assertJsonMissingPath('categories.0.name_ar')
            ->assertJsonMissingPath('categories.0.name_en')
            ->assertJsonMissingPath('products.data.0.name_ar')
            ->assertJsonMissingPath('products.data.0.name_en')
            ->assertJsonMissingPath('products.data.0.description_ar')
            ->assertJsonMissingPath('products.data.0.description_en')
            ->assertJsonMissingPath('products.data.0.subcategory.name_ar')
            ->assertJsonMissingPath('products.data.0.subcategory.name_en')
            ->assertJsonStructure([
                'products' => ['current_page', 'data', 'per_page', 'total'],
            ]);
    }

    public function test_it_returns_localized_content_based_on_requested_language(): void
    {
        $category = Category::query()->create([
            'name_ar' => 'إلكترونيات',
            'name_en' => 'Electronics',
            'description_ar' => 'وصف عربي للقسم',
            'description_en' => 'English category description',
            'is_active' => true,
            'order' => 1,
        ]);

        $subcategory = Subcategory::query()->create([
            'category_id' => $category->id,
            'name_ar' => 'هواتف',
            'name_en' => 'Phones',
            'description_ar' => 'وصف عربي للقسم الفرعي',
            'description_en' => 'English subcategory description',
            'is_active' => true,
            'order' => 1,
        ]);

        $vendor = User::factory()->create([
            'role' => 'vendor',
            'is_active' => true,
        ]);

        $store = Store::query()->create([
            'user_id' => $vendor->id,
            'name' => 'Tech Store',
            'description' => 'Store description',
            'city' => 'Riyadh',
            'is_active' => true,
        ]);

        $store->categories()->attach($category->id);

        $product = Product::query()->create([
            'store_id' => $store->id,
            'subcategory_id' => $subcategory->id,
            'name_ar' => 'هاتف ذكي',
            'name_en' => 'Smart Phone',
            'description_ar' => 'وصف عربي للمنتج',
            'description_en' => 'English product description',
            'base_price' => 150,
            'stock' => 10,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/home?category_id=' . $category->id . '&lang=en');

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('message', 'Home data fetched successfully.')
            ->assertJsonPath('filters.language', 'en')
            ->assertJsonPath('categories.0.name', 'Phones')
            ->assertJsonPath('categories.0.description', 'English subcategory description')
            ->assertJsonPath('products.data.0.name', 'Smart Phone')
            ->assertJsonPath('products.data.0.description', 'English product description')
            ->assertJsonPath('products.data.0.subcategory.name', 'Phones')
            ->assertJsonPath('stores.0.categories.0.name', 'Electronics')
            ->assertJsonMissingPath('categories.0.name_ar')
            ->assertJsonMissingPath('categories.0.name_en')
            ->assertJsonMissingPath('products.data.0.name_ar')
            ->assertJsonMissingPath('products.data.0.name_en')
            ->assertJsonMissingPath('products.data.0.description_ar')
            ->assertJsonMissingPath('products.data.0.description_en')
            ->assertJsonMissingPath('products.data.0.subcategory.name_ar')
            ->assertJsonMissingPath('products.data.0.subcategory.name_en');
    }

    public function test_it_returns_active_ads_and_promotions_without_time_checks(): void
    {
        [$category, $subcategory, $store, $product] = $this->createCatalog('إلكترونيات', 'هواتف', 'متجر التقنية', 'هاتف ذكي');

        Ad::query()->create([
            'media_type' => 'image',
            'media_path' => 'https://example.com/ad-future.jpg',
            'click_action' => 'store',
            'action_id' => (string) $store->id,
            'starts_at' => now()->addDays(5),
            'ends_at' => now()->addDays(10),
            'is_active' => true,
        ]);

        $promotion = Promotion::query()->create([
            'title' => 'عرض مستقبلي مفعل',
            'level' => 'app',
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'starts_at' => now()->addDays(3),
            'ends_at' => now()->addDays(7),
            'is_active' => true,
        ]);

        PromotionItem::query()->create([
            'promotion_id' => $promotion->id,
            'store_id' => $store->id,
            'promotable_type' => Category::class,
            'promotable_id' => $category->id,
            'status' => 'approved',
        ]);

        $response = $this->getJson('/api/home');

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonCount(1, 'ads')
            ->assertJsonCount(1, 'promotions')
            ->assertJsonPath('ads.0.id', 1)
            ->assertJsonPath('promotions.0.id', $promotion->id)
            ->assertJsonPath('promotions.0.is_active', true);
    }

    public function test_it_filters_home_data_by_category_id_and_returns_subcategories(): void
    {
        [$firstCategory, $firstSubcategory, $firstStore, $firstProduct] = $this->createCatalog('إلكترونيات', 'هواتف', 'متجر التقنية', 'هاتف ذكي');
        [$secondCategory, $secondSubcategory, $secondStore, $secondProduct] = $this->createCatalog('أزياء', 'أحذية', 'متجر الأناقة', 'حذاء رياضي');

        Ad::query()->create([
            'media_type' => 'image',
            'media_path' => 'https://example.com/ad-1.jpg',
            'click_action' => 'store',
            'action_id' => (string) $firstStore->id,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'is_active' => true,
        ]);

        $matchingPromotion = Promotion::query()->create([
            'title' => 'عرض الإلكترونيات',
            'level' => 'app',
            'discount_type' => 'percentage',
            'discount_value' => 15,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'is_active' => true,
        ]);

        PromotionItem::query()->create([
            'promotion_id' => $matchingPromotion->id,
            'store_id' => $firstStore->id,
            'promotable_type' => Category::class,
            'promotable_id' => $firstCategory->id,
            'status' => 'approved',
        ]);

        $otherPromotion = Promotion::query()->create([
            'title' => 'عرض الأزياء',
            'level' => 'app',
            'discount_type' => 'fixed',
            'discount_value' => 20,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDay(),
            'is_active' => true,
        ]);

        PromotionItem::query()->create([
            'promotion_id' => $otherPromotion->id,
            'store_id' => $secondStore->id,
            'promotable_type' => Category::class,
            'promotable_id' => $secondCategory->id,
            'status' => 'approved',
        ]);

        $response = $this->getJson('/api/home?category_id=' . $firstCategory->id);

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('filters.category_id', $firstCategory->id)
            ->assertJsonPath('filters.language', 'ar')
            ->assertJsonPath('categories_type', 'subcategories')
            ->assertJsonCount(1, 'ads')
            ->assertJsonCount(1, 'categories')
            ->assertJsonCount(1, 'promotions')
            ->assertJsonCount(1, 'products.data')
            ->assertJsonCount(1, 'stores')
            ->assertJsonPath('categories.0.id', $firstSubcategory->id)
            ->assertJsonPath('promotions.0.id', $matchingPromotion->id)
            ->assertJsonPath('products.data.0.id', $firstProduct->id)
            ->assertJsonPath('stores.0.id', $firstStore->id);
    }

    private function createCatalog(string $categoryName, string $subcategoryName, string $storeName, string $productName): array
    {
        $category = Category::query()->create([
            'name_ar' => $categoryName,
            'name_en' => $categoryName,
            'is_active' => true,
            'order' => 1,
        ]);

        $subcategory = Subcategory::query()->create([
            'category_id' => $category->id,
            'name_ar' => $subcategoryName,
            'name_en' => $subcategoryName,
            'is_active' => true,
            'order' => 1,
        ]);

        $vendor = User::factory()->create([
            'role' => 'vendor',
            'is_active' => true,
        ]);

        $store = Store::query()->create([
            'user_id' => $vendor->id,
            'name' => $storeName,
            'city' => 'الرياض',
            'is_active' => true,
        ]);

        $store->categories()->attach($category->id);

        $product = Product::query()->create([
            'store_id' => $store->id,
            'subcategory_id' => $subcategory->id,
            'name_ar' => $productName,
            'name_en' => $productName,
            'base_price' => 150,
            'stock' => 10,
            'is_active' => true,
        ]);

        return [$category, $subcategory, $store, $product];
    }
}
