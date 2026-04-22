<?php

use App\Enums\VatRate;
use App\Models\Product;

// Test basic product creation
test('product can be created with required fields', function () {
    $product = Product::create([
        'name' => 'Test Product',
        'price' => 10000, // 100.00 EUR in cents
        'vat_rate' => VatRate::R22->value,
    ]);

    expect($product->name)->toBe('Test Product');
    expect($product->price)->toEqual(10000); // Price stored in cents
});

// Test product with VAT rate relationship
test('product can be associated with a VAT rate', function () {
    $product = Product::create([
        'name' => 'Product with VAT',
        'price' => 10000, // 100.00 EUR in cents
        'vat_rate' => VatRate::R22->value,
    ]);

    expect($product->vat_rate)->toBe(VatRate::R22);
    expect($product->vat_rate->percent())->toEqual(22.0);
});

// Product always requires VAT rate due to foreign key constraint
// This test is removed as it's not applicable with current schema

// Test price handling in cents
test('product handles prices in cents correctly', function () {
    $testPrices = [
        10000, // 100.00 EUR
        9999,  // 99.99 EUR
        1,     // 0.01 EUR
        100050, // 1000.50 EUR
        5050,  // 50.50 EUR
    ];

    foreach ($testPrices as $price) {
        $product = Product::create([
            'name' => 'Product',
            'price' => $price, // In cents
            'vat_rate' => VatRate::R22->value,
        ]);

        expect($product->price)->toBe($price);
    }
});

// Test zero price
test('product can have zero price', function () {
    $product = Product::create([
        'name' => 'Free Product',
        'price' => 0, // 0 cents
        'vat_rate' => VatRate::R22->value,
    ]);

    expect($product->price)->toEqual(0);
});

// Test product with description
test('product can have optional description field', function () {
    $product = Product::create([
        'name' => 'Product',
        'description' => 'Detailed product description',
        'price' => 10000, // 100.00 EUR in cents
        'vat_rate' => VatRate::R22->value,
    ]);

    expect($product->description)->toBe('Detailed product description');
});

// Test product update
test('product price can be updated', function () {
    $product = Product::create([
        'name' => 'Product',
        'price' => 10000, // 100.00 EUR in cents
        'vat_rate' => VatRate::R22->value,
    ]);

    $product->update(['price' => 15000]); // 150.00 EUR in cents

    expect($product->fresh()->price)->toEqual(15000);
});

test('product VAT rate can be changed', function () {
    $product = Product::create([
        'name' => 'Product',
        'price' => 10000, // 100.00 EUR in cents
        'vat_rate' => VatRate::R22->value,
    ]);

    expect($product->vat_rate->percent())->toEqual(22.0);

    $product->update(['vat_rate' => VatRate::R10->value]);

    expect($product->fresh()->vat_rate->percent())->toEqual(10.0);
});

// Test complete product scenario
test('product with all fields works correctly', function () {
    $product = Product::create([
        'name' => 'Complete Product',
        'description' => 'Full description of the product',
        'price' => 9999, // 99.99 EUR in cents
        'vat_rate' => VatRate::R22->value,
    ]);

    expect($product->name)->toBe('Complete Product');
    expect($product->description)->toBe('Full description of the product');
    expect($product->price)->toEqual(9999);
    expect($product->vat_rate)->not->toBeNull();
    expect($product->vat_rate->percent())->toEqual(22.0);
});

// Test product deletion
test('product can be deleted', function () {
    $product = Product::create([
        'name' => 'Product to delete',
        'price' => 10000, // 100.00 EUR in cents
        'vat_rate' => VatRate::R22->value,
    ]);

    $productId = $product->id;

    $product->delete();

    expect(Product::find($productId))->toBeNull();
});

// Test multiple products creation
test('multiple products can be created with different VAT rates', function () {
    $products = [
        Product::create(['name' => 'Product A', 'price' => 10000, 'vat_rate' => VatRate::R22->value]), // 100.00 EUR
        Product::create(['name' => 'Product B', 'price' => 20000, 'vat_rate' => VatRate::R10->value]), // 200.00 EUR
        Product::create(['name' => 'Product C', 'price' => 30000, 'vat_rate' => VatRate::R4->value]),  // 300.00 EUR
    ];

    expect(count($products))->toBe(3);
    expect($products[0]->vat_rate->percent())->toEqual(22.0);
    expect($products[1]->vat_rate->percent())->toEqual(10.0);
    expect($products[2]->vat_rate->percent())->toEqual(4.0);
});
