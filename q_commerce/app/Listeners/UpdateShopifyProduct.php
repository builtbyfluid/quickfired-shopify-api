<?php
namespace App\Listeners;

use Illuminate\Support\Facades\Http;
use Statamic\Events\EntrySaved;
use Statamic\Facades\Entry;
use Statamic\Facades\EntryAPI;
use App\Services\ShopifyService;

class UpdateShopifyProduct
{
    public function handle(EntrySaved $event)
    {
        if ($event->entry->collection()->handle() != 'products') {
            \Log::info('Listener triggered but not for products collection.');
            return;
        }

        \Log::info('Attempting to update Shopify with product: ' . $event->entry->get('title'));

        $productData = $event->entry->data()->toArray();
        $existingProductData = $this->fetchExistingProductData($productData);

        // Check if the product already exists on Shopify
        if ($existingProductData !== null && isset($existingProductData['shopify_id'])) {
            // Call the hasProductDataChanged method to determine if an update is needed.
            if ($this->hasProductDataChanged($productData, $existingProductData)) {
                // Instantiae the ShopifyService and call the syncProduct method to perform the update
                app(ShopifyService::class)->syncProduct($event->entry);
            } else {
                \Log::info("Product data is unchanged. No update needed.");
            }
        } else {
            // Perform a create action for a new product
            app(ShopifyService::class)->syncProduct($event->entry);
        }
    }

    /**
     * @param $productData
     * @return mixed|null
     */
    protected function fetchExistingProductData($productData)
    {
        // Check if shopify_id exists
        if (!isset($productData['shopify_id']) || is_null($productData['shopify_id'])) {
            \Log::warning('shopify_id is missing or null for product: ' . $productData['title']);
            return null; // Exit the function
        }

        try {
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => env('SHOPIFY_ACCESS_TOKEN'),
                'Content-Type' => 'application/json',
            ])->get('https://6f25e1.myshopify.com/admin/api/2022-04/products/' . $productData['shopify_id'] . '.json');

            if ($response->failed()) {
                \Log::error("Failed to fetch product data from Shopify: " . $response->body());
                return null;
            }

            return $response->json()['product'];
        } catch (\Exception $e) {
            \Log::error("Failed to fetch product data from Shopify: " . $e->getMessage());
            return null;
        }
    }

    /**
     * @param $productData
     * @return void
     */
    protected function performUpdate($productData)
    {
        $data = [
            'product' => [
                'title' => $productData['title'],
                'body_html' => $productData['content'],
                'variants' => [
                    [
                        'price' => $productData['price'],
                        'inventory_quantity' => $productData['stock_levels'],
                        'inventory_policy' => 'continue' // inventory wasn't updating when adding a product from statamic
                    ]
                ],
            ],
        ];

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => env('SHOPIFY_ACCESS_TOKEN'),
            'Content-Type' => 'application/json',
        ])->put('https://6f25e1.myshopify.com/admin/api/2022-04/products/' . $productData['shopify_id'] . '.json', $data);

        if ($response->failed()) {
            \Log::error("Failed to update product in Shopify: " . $response->body());
        } else {
            \Log::info("Successfully updated product in Shopify: " . $response->body());
        }
    }
}
