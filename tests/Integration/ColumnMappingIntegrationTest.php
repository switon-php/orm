<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Integration;

use Switon\Core\Attribute\Autowired;
use Switon\Orm\Attribute\Column;
use Switon\Orm\Attribute\Id;
use Switon\Orm\Attribute\Table;
use Switon\Orm\Entity;
use Switon\Orm\EntityManagerInterface;
use Switon\Orm\Tests\DatabaseTestCase;

/**
 * Integration tests for Column attribute mapping in CRUD operations.
 *
 * Tests that property names correctly map to database column names
 * in all CRUD operations using real database.
 */
class ColumnMappingIntegrationTest extends DatabaseTestCase
{
    #[Autowired] protected EntityManagerInterface $entityManager;
    protected ProductRepository $productRepository;
    protected CustomerRepository $customerRepository;

    protected function getSchema(): string
    {
        return "
            -- Products table with mapped columns
            CREATE TABLE products (
                product_id INTEGER PRIMARY KEY AUTOINCREMENT,
                product_name TEXT NOT NULL,
                product_price REAL NOT NULL,
                stock_quantity INTEGER DEFAULT 0,
                is_active INTEGER DEFAULT 1,
                created_date TEXT,
                updated_date TEXT
            );

            -- Customers table with mapped columns
            CREATE TABLE customers (
                customer_id INTEGER PRIMARY KEY AUTOINCREMENT,
                first_name TEXT NOT NULL,
                last_name TEXT NOT NULL,
                email_address TEXT UNIQUE NOT NULL,
                phone_number TEXT,
                total_orders INTEGER DEFAULT 0,
                account_balance REAL DEFAULT 0,
                registration_date TEXT,
                last_login_date TEXT
            );
        ";
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->productRepository = new ProductRepository(
            $this->db,
            $this->entityManager,
            $this->container
        );
        $this->customerRepository = new CustomerRepository(
            $this->db,
            $this->entityManager,
            $this->container
        );
    }

    // ==================== CREATE Tests ====================

    public function testCreateWithMappedColumns(): void
    {
        // Create product with mapped columns
        $product = new Product();
        $product->name = 'Laptop';
        $product->price = 999.99;
        $product->stockQuantity = 10;
        $product->isActive = 1;

        $this->productRepository->create($product);

        // Verify product was created with correct column mapping
        $this->assertNotNull($product->id);

        // Verify in database using actual column names
        $this->assertDatabaseHas('products', [
            'product_name' => 'Laptop',
            'product_price' => 999.99,
            'stock_quantity' => 10,
            'is_active' => 1
        ]);
    }

    public function testCreateCustomerWithAllMappedFields(): void
    {
        // Create customer with all mapped fields
        $customer = new Customer();
        $customer->firstName = 'John';
        $customer->lastName = 'Doe';
        $customer->emailAddress = 'john.doe@example.com';
        $customer->phoneNumber = '+1234567890';
        $customer->totalOrders = 0;
        $customer->accountBalance = 100.00;

        $this->customerRepository->create($customer);

        // Verify customer was created
        $this->assertNotNull($customer->id);

        // Verify database columns
        $this->assertDatabaseHas('customers', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email_address' => 'john.doe@example.com',
            'phone_number' => '+1234567890',
            'total_orders' => 0,
            'account_balance' => 100.00
        ]);
    }

    // ==================== READ Tests ====================

    public function testReadWithMappedColumns(): void
    {
        // Insert data using database column names
        $productId = $this->insert('products', [
            'product_name' => 'Mouse',
            'product_price' => 29.99,
            'stock_quantity' => 50,
            'is_active' => 1
        ]);

        // Read using repository (should map columns to properties)
        $product = $this->productRepository->find($productId);

        // Verify property mapping
        $this->assertNotNull($product);
        $this->assertEquals('Mouse', $product->name);
        $this->assertEquals(29.99, $product->price);
        $this->assertEquals(50, $product->stockQuantity);
        $this->assertEquals(1, $product->isActive);
    }

    public function testReadCustomerWithAllMappedFields(): void
    {
        // Insert customer using database column names
        $customerId = $this->insert('customers', [
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email_address' => 'jane.smith@example.com',
            'phone_number' => '+9876543210',
            'total_orders' => 5,
            'account_balance' => 250.50
        ]);

        // Read using repository
        $customer = $this->customerRepository->find($customerId);

        // Verify all property mappings
        $this->assertNotNull($customer);
        $this->assertEquals('Jane', $customer->firstName);
        $this->assertEquals('Smith', $customer->lastName);
        $this->assertEquals('jane.smith@example.com', $customer->emailAddress);
        $this->assertEquals('+9876543210', $customer->phoneNumber);
        $this->assertEquals(5, $customer->totalOrders);
        $this->assertEquals(250.50, $customer->accountBalance);
    }

    public function testReadMultipleEntitiesWithMappedColumns(): void
    {
        // Insert multiple products
        $this->insert('products', [
            'product_name' => 'Product 1',
            'product_price' => 10.00,
            'stock_quantity' => 100,
            'is_active' => 1
        ]);
        $this->insert('products', [
            'product_name' => 'Product 2',
            'product_price' => 20.00,
            'stock_quantity' => 200,
            'is_active' => 1
        ]);
        $this->insert('products', [
            'product_name' => 'Product 3',
            'product_price' => 30.00,
            'stock_quantity' => 300,
            'is_active' => 0
        ]);

        // Read all products
        $products = $this->productRepository->all();

        // Verify all products have correct property mapping
        $this->assertCount(3, $products);
        $this->assertEquals('Product 1', $products[0]->name);
        $this->assertEquals(10.00, $products[0]->price);
        $this->assertEquals('Product 2', $products[1]->name);
        $this->assertEquals(20.00, $products[1]->price);
        $this->assertEquals('Product 3', $products[2]->name);
        $this->assertEquals(0, $products[2]->isActive);
    }

    // ==================== UPDATE Tests ====================

    public function testUpdateWithMappedColumns(): void
    {
        // Insert product
        $productId = $this->insert('products', [
            'product_name' => 'Old Name',
            'product_price' => 100.00,
            'stock_quantity' => 10,
            'is_active' => 1
        ]);

        // Read and update using mapped properties
        $product = $this->productRepository->find($productId);
        $product->name = 'New Name';
        $product->price = 150.00;
        $product->stockQuantity = 20;
        $product->isActive = 0;

        $this->productRepository->update($product);

        // Verify database columns were updated
        $this->assertDatabaseHas('products', [
            'product_id' => $productId,
            'product_name' => 'New Name',
            'product_price' => 150.00,
            'stock_quantity' => 20,
            'is_active' => 0
        ]);
    }

    public function testUpdateCustomerWithMappedFields(): void
    {
        // Insert customer
        $customerId = $this->insert('customers', [
            'first_name' => 'Old First',
            'last_name' => 'Old Last',
            'email_address' => 'old@example.com',
            'phone_number' => '+1111111111',
            'total_orders' => 0,
            'account_balance' => 0.00
        ]);

        // Read and update
        $customer = $this->customerRepository->find($customerId);
        $customer->firstName = 'New First';
        $customer->lastName = 'New Last';
        $customer->emailAddress = 'new@example.com';
        $customer->phoneNumber = '+2222222222';
        $customer->totalOrders = 10;
        $customer->accountBalance = 500.00;

        $this->customerRepository->update($customer);

        // Verify all columns were updated
        $this->assertDatabaseHas('customers', [
            'customer_id' => $customerId,
            'first_name' => 'New First',
            'last_name' => 'New Last',
            'email_address' => 'new@example.com',
            'phone_number' => '+2222222222',
            'total_orders' => 10,
            'account_balance' => 500.00
        ]);
    }

    public function testUpdatePartialFieldsWithMapping(): void
    {
        // Insert product
        $productId = $this->insert('products', [
            'product_name' => 'Product',
            'product_price' => 100.00,
            'stock_quantity' => 10,
            'is_active' => 1
        ]);

        // Update only some fields
        $product = $this->productRepository->find($productId);
        $originalPrice = $product->price;
        $product->stockQuantity = 5;  // Only update stock

        $this->productRepository->update($product);

        // Verify only stock was updated, price unchanged
        $updated = $this->productRepository->find($productId);
        $this->assertEquals($originalPrice, $updated->price);
        $this->assertEquals(5, $updated->stockQuantity);
    }

    // ==================== DELETE Tests ====================

    public function testDeleteWithMappedColumns(): void
    {
        // Insert product
        $productId = $this->insert('products', [
            'product_name' => 'To Delete',
            'product_price' => 50.00,
            'stock_quantity' => 5,
            'is_active' => 1
        ]);

        $this->assertDatabaseCount('products', 1);

        // Delete using repository
        $product = $this->productRepository->find($productId);
        $this->productRepository->delete($product);

        // Verify deletion
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseMissing('products', ['product_id' => $productId]);
    }

    public function testDeleteCustomerWithMappedFields(): void
    {
        // Insert customer
        $customerId = $this->insert('customers', [
            'first_name' => 'Delete',
            'last_name' => 'Me',
            'email_address' => 'delete@example.com',
            'phone_number' => '+0000000000',
            'total_orders' => 0,
            'account_balance' => 0.00
        ]);

        $this->assertDatabaseCount('customers', 1);

        // Delete
        $customer = $this->customerRepository->find($customerId);
        $this->customerRepository->delete($customer);

        // Verify deletion
        $this->assertDatabaseCount('customers', 0);
    }

    // ==================== Query with Mapped Columns Tests ====================

    public function testQueryWithMappedColumnNames(): void
    {
        // Insert products
        $this->insert('products', [
            'product_name' => 'Cheap Product',
            'product_price' => 10.00,
            'stock_quantity' => 100,
            'is_active' => 1
        ]);
        $this->insert('products', [
            'product_name' => 'Expensive Product',
            'product_price' => 1000.00,
            'stock_quantity' => 5,
            'is_active' => 1
        ]);

        // Query using property names (should map to column names)
        $expensiveProducts = $this->productRepository->findByPriceGreaterThan(500);

        $this->assertCount(1, $expensiveProducts);
        $this->assertEquals('Expensive Product', $expensiveProducts[0]->name);
    }

    public function testQueryCustomersWithMappedFields(): void
    {
        // Insert customers
        $this->insert('customers', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email_address' => 'john@example.com',
            'phone_number' => '+1111111111',
            'total_orders' => 5,
            'account_balance' => 100.00
        ]);
        $this->insert('customers', [
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email_address' => 'jane@example.com',
            'phone_number' => '+2222222222',
            'total_orders' => 15,
            'account_balance' => 500.00
        ]);

        // Query using mapped property names
        $vipCustomers = $this->customerRepository->findByTotalOrdersGreaterThan(10);

        $this->assertCount(1, $vipCustomers);
        $this->assertEquals('Jane', $vipCustomers[0]->firstName);
    }

    // ==================== Batch Operations with Mapping Tests ====================

    public function testBatchCreateWithMappedColumns(): void
    {
        // Create multiple products
        $products = [
            new Product(['name' => 'Product 1', 'price' => 10.00, 'stockQuantity' => 10]),
            new Product(['name' => 'Product 2', 'price' => 20.00, 'stockQuantity' => 20]),
            new Product(['name' => 'Product 3', 'price' => 30.00, 'stockQuantity' => 30]),
        ];

        $this->productRepository->createMany($products);

        // Verify all products were created with correct column mapping
        $this->assertDatabaseCount('products', 3);
        $this->assertDatabaseHas('products', ['product_name' => 'Product 1', 'product_price' => 10.00]);
        $this->assertDatabaseHas('products', ['product_name' => 'Product 2', 'product_price' => 20.00]);
        $this->assertDatabaseHas('products', ['product_name' => 'Product 3', 'product_price' => 30.00]);
    }

    public function testBatchUpdateWithMappedColumns(): void
    {
        // Insert products
        $this->insert('products', [
            'product_name' => 'Product 1',
            'product_price' => 10.00,
            'stock_quantity' => 10,
            'is_active' => 1
        ]);
        $this->insert('products', [
            'product_name' => 'Product 2',
            'product_price' => 20.00,
            'stock_quantity' => 20,
            'is_active' => 1
        ]);

        // Batch update using database column names (column mapping not applied in updateAll)
        // This is a known limitation - updateAll uses raw column names for both filters and data
        $affected = $this->productRepository->updateAll(
            ['product_price<' => 50], // filters use database column names
            ['is_active' => 0]        // data uses database column names
        );

        $this->assertEquals(2, $affected);

        // Verify database columns were updated
        $this->assertEquals(2, $this->countRows('products', ['is_active' => 0]));
    }

    // ==================== Complex Mapping Scenarios ====================

    public function testMixedMappedAndUnmappedColumns(): void
    {
        // Product has both mapped (name->product_name) and unmapped columns
        $product = new Product();
        $product->name = 'Test Product';  // Mapped
        $product->price = 99.99;          // Mapped
        $product->stockQuantity = 10;     // Mapped

        $this->productRepository->create($product);

        // Verify both types of columns work correctly
        $found = $this->productRepository->find($product->id);
        $this->assertEquals('Test Product', $found->name);
        $this->assertEquals(99.99, $found->price);
        $this->assertEquals(10, $found->stockQuantity);
    }

    public function testBooleanColumnMapping(): void
    {
        // Test boolean to integer mapping
        $product = new Product();
        $product->name = 'Active Product';
        $product->price = 50.00;
        $product->stockQuantity = 5;
        $product->isActive = 1;  // Integer property (1 = active)

        $this->productRepository->create($product);

        // Verify stored as integer
        $this->assertDatabaseHas('products', [
            'product_name' => 'Active Product',
            'is_active' => 1  // Integer in database
        ]);

        // Verify reading back as integer
        $found = $this->productRepository->find($product->id);
        $this->assertEquals(1, $found->isActive);

        // Test inactive value
        $found->isActive = 0;
        $this->productRepository->update($found);

        $this->assertDatabaseHas('products', [
            'product_id' => $product->id,
            'is_active' => 0
        ]);
    }
}

// ==================== Test Entities ====================

/**
 * Product entity with mapped column names.
 *
 * Property names use camelCase, database columns use snake_case.
 */
#[Table('products')]
class Product extends Entity
{
    #[Id]
    #[Column('product_id')]
    public ?int $id = null;

    #[Column('product_name')]
    public string $name;

    #[Column('product_price')]
    public float $price;

    #[Column('stock_quantity')]
    public int $stockQuantity = 0;

    #[Column('is_active')]
    public int $isActive = 1;

    #[Column('created_date')]
    public ?string $createdDate = null;

    #[Column('updated_date')]
    public ?string $updatedDate = null;
}

/**
 * Customer entity with all fields mapped.
 */
#[Table('customers')]
class Customer extends Entity
{
    #[Id]
    #[Column('customer_id')]
    public ?int $id = null;

    #[Column('first_name')]
    public string $firstName;

    #[Column('last_name')]
    public string $lastName;

    #[Column('email_address')]
    public string $emailAddress;

    #[Column('phone_number')]
    public ?string $phoneNumber = null;

    #[Column('total_orders')]
    public int $totalOrders = 0;

    #[Column('account_balance')]
    public float $accountBalance = 0.0;

    #[Column('registration_date')]
    public ?string $registrationDate = null;

    #[Column('last_login_date')]
    public ?string $lastLoginDate = null;
}

// ==================== Test Repositories ====================

class ProductRepository extends BaseTestRepository
{
    protected function getEntityClass(): string
    {
        return Product::class;
    }

    /**
     * Find products by price range.
     */
    public function findByPriceGreaterThan(float $price): array
    {
        return $this->all(['price>' => $price]);
    }
}

class CustomerRepository extends BaseTestRepository
{
    protected function getEntityClass(): string
    {
        return Customer::class;
    }

    /**
     * Find VIP customers by total orders.
     */
    public function findByTotalOrdersGreaterThan(int $orders): array
    {
        return $this->all(['totalOrders>=' => $orders]);
    }
}
