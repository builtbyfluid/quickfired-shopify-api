<?php

namespace App\Listeners;

use App\Services\ShopifyService;
use Statamic\Events\EntrySaved;
use Illuminate\Support\Facades\Http;

class SyncToShopifyListener
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

        // check if the product already exists on shopify
        if ($existingProductData !== null && isset($existingProductData['shopify_id'])) {
            // Call the hasProductDataChanged method to determine if an update is needed.
            if ($this->hasProductDataChanged($productData, $existingProductData)) {
                // Instantiate the ShopifyService and call the syncProduct method to perform the update
                $shopifyService = new ShopifyService();
                $shopifyService->syncProduct($event->entry);
            } else {
                \Log::info("Product data is unchanged. No update needed.");
            }
        } else {
            // Perform a crate action for a new product
            $shopifyService = new ShopifyService();
            $shopifyService->syncProduct($event->entry);
        }
    }

    protected function callShopifyApi($method, $endpoint, $data = [])
    {

        $url = "https://6f25e1.myshopify.com/admin/api/2022-04/{$endpoint}";

        try {
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => env('SHOPIFY_ACCESS_TOKEN'),
                'Content-Type' => 'application/json',
            ])->$method($url, $data);

            return $response->json();
        } catch (\Exception $e) {
            \Log::error("Failed to make Shopify API request: " . $e->getMessage());
            return null;
        }
    }

    protected function fetchExistingProductData($productData) {
        // Get the Shopify product ID from the $productData array
        $shopifyProductId = $productData['shopify_id'];

        // Use the Shopify API to retrieve the product data
        $response = $this->callShopifyApi("GET", "products/{$shopifyProductId}.json");

        if ($response && isset($response['product'])) {
            return $response['product'];
        } else {
            return null;
        }
    }

    protected function hasProductDataChanged($productData, $existingProductData)
    {
        // Compare the relevant fields in the $productData with the $existingProductData
        if (
            $productData['title'] !== $existingProductData['title'] ||
            $productData['content'] !== $existingProductData['content'] ||
            $productData['price'] !== $existingProductData['price'] ||
            $productData['stock_levels'] !== $existingProductData['stock_levels']
        ) {
            return true;
        } else {
            return false;
        }
    }
}
