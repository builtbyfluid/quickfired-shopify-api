<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Statamic\Events\EntrySaved;
use Statamic\Entries\Entry;

class ShopifyService
{
    public function syncProduct(Entry $entry)
    {
        $productData = $this->formatProductDataForShopify($entry);

        $shopifyProductId = $this->getShopifyProductId($entry->slug());

        if ($shopifyProductId) {
            // This product already exists in Shopify, so we should update it.
            $endpoint = "https://6f25e1.myshopify.com/admin/api/2022-04/products/{$shopifyProductId}.json";
            $method = 'PUT';
        } else {
            // This is a new product, so we'll create it in Shopify.
            $endpoint = "https://6f25e1.myshopify.com/admin/api/2022-04/products.json";
            $method = 'POST';
        }

        try {
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => env('SHOPIFY_ACCESS_TOKEN'),
                'Content-Type' => 'application/json',
            ])->$method($endpoint, $productData);

            if ($response->failed()) {
                Log::error("Failed to sync product to Shopify: " . $response->body());
            } else {
                Log::info("Successfully synced product to Shopify: " . $response->body());
            }
        } catch (\Exception $e) {
            Log::error("Failed to sync product to Shopify: " . $e->getMessage());
        }
    }

    protected function getShopifyProductId($handle)
    {
        try {
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => env('SHOPIFY_ACCESS_TOKEN'),
                'Content-Type' => 'application/json',
            ])->get("https://6f25e1.myshopify.com/admin/api/2022-04/products.json?handle={$handle}");

            if ($response->successful() && count($response->json()['products']) > 0) {
                return $response->json()['products'][0]['id'];
            }
            return null;

        } catch (\Exception $e) {
            Log::error("Failed to retrieve Shopify product ID: " . $e->getMessage());
            return null;
        }
    }

    public function formatProductDataForShopify($entry)
    {
        $productData = [
            'product' => [
                'title' => $entry->get('title'),
                'body_html' => $entry->get('content'),
                'variants' => [
                    [
                        'price' => $entry->get('price'),
                        'inventory_quantity' => $entry->get('stock_levels'),
                        'inventory_policy' => 'continue' // Set inventory policy to continue
                    ]
                ]
            ]
        ];
        Log::info("Formatted data for Shopify: " . json_encode($productData));
        return $productData;
    }

    protected function callShopifyApi($method, $endpoint, $data = [])
    {
        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => env('SHOPIFY_ACCESS_TOKEN'),
            'Content-Type' => 'application/json',
        ])->$method(env('SHOPIFY_API_BASE_URL'). "/{$endpoint}", $data);

        return $response->json();
    }
}
