<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Statamic\Facades\Entry;
use Statamic\Auth\User;

class ShopifyController extends Controller
{
    /**
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|\Illuminate\Foundation\Application
     */
    public function getProducts() {
        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => env('SHOPIFY_ACCESS_TOKEN'),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->get(env('SHOPIFY_URL'));

        if ($response->successful()) {
            $products = $response->json()['products'] ?? [];
        } else {
            $products = [];
        }

        return view('shopify.products', ['products' => $products]);
    }

    /**
     * @return void
     */
    public function fetchProductsFromShopify($fromConsole = false)
    {
        if (!$fromConsole) {
            $user = User::current();

            if (!$user || !$user->isSuper()) {
                abort(403, 'Unauthorized action.');
            }
        }

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => env('SHOPIFY_ACCESS_TOKEN'),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->get(env('SHOPIFY_URL'));

        if ($response->successful()) {
            $products = $response->json()['products'];

            foreach ($products as $product) {
                // Make sure the product has a valid handle before using it as the slug
                $slug = $product['handle'] ?? Str::slug($product['title']);

                $existingEntry = Entry::query()
                    ->where('collection', 'products')
                    ->where('slug', $slug)
                    ->first();

                if ($existingEntry) {
                    $entry = $existingEntry;
                } else {
                    $entry = Entry::make()
                        ->collection('products')
                        ->slug($slug);
                }

                $entry->data([
                    'title' => $product['title'],
                    'content' => $product['body_html'],
                    'price' => isset($product['variants'][0]['price']) ? $product['variants'][0]['price'] : null,
                    'stock_levels' => isset($product['variants'][0]['inventory_quantity']) ? $product['variants'][0]['inventory_quantity'] : null,
                    // Add image handling here once you've worked out the logic
                ]);

                // Add the Shopify ID to the 'shopify_id' field
                $entry->set('shopify_id', $product['id']);

                // Save the entry regardless of whether it's new or existing
                $entry->save();
            }

        } else {
            Log::error("Failed to fetch products from Shopify. Response: " . $response->body());
        }
    }

}
