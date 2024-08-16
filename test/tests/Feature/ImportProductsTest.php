<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ImportProductsTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
    }

    protected function importProducts($csvFile = 'stock.csv'): void
    {
        $csv = base_path("tests/Fixtures/{$csvFile}");
        Artisan::call('products:import', ['file' => $csv]);
    }

    public function testCommandHandlesImportProducts()
    {
        $this->importProducts();

        $this->assertNotEmpty(Product::all(), 'The products table is empty. No products were imported.');
    }

    public function testCommandHandlesInvalidData()
    {
        $this->importProducts("stock_invalid_data.csv");

        $this->assertDatabaseMissing('products', ['code' => 'P0011']);
        $this->assertDatabaseMissing('products', ['code' => 'P0017']);
    }

    public function testCommandHandlesValidData()
    {
        $this->importProducts('stock_valid_data.csv');

        $this->assertDatabaseHas('products', [
            'code' => 'P0001',
            'name' => 'TV',
            'description' => '32” Tv',
            'stock' => 10,
            'cost' => 399.99,
            'currency' => 'gbp',
            'is_discontinued' => 0,
        ]);

        $this->assertDatabaseHas('products', [
            'code' => 'P0002',
            'name' => 'Cd Player',
            'description' => 'Nice CD player',
            'stock' => 11,
            'cost' => 50.12,
            'currency' => 'gbp',
            'is_discontinued' => 1,
        ]);
    }

    public function testCommandHandlesBusinessLogic()
    {
        $this->importProducts('stock_business_logic.csv');

        $this->assertDatabaseMissing('products', ['code' => 'P0027']);
        $this->assertDatabaseMissing('products', ['code' => 'P0028']);
    }
}
