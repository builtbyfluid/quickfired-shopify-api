<?php

namespace App\Console\Commands;

use App\Http\Controllers\ShopifyController;
use Illuminate\Console\Command;

class SyncShopifyProductsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shopify:sync-products';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync products from Shopify to Statamic';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $shopifyController = new ShopifyController();
        $shopifyController->fetchProductsFromShopify(true);
    }
}
