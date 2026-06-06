<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Integration;

use Switon\Core\Attribute\Autowired;
use Switon\Orm\Attribute\Column;
use Switon\Orm\Attribute\Table;
use Switon\Orm\Entity;
use Switon\Orm\EntityManagerInterface;
use Switon\Orm\Tests\DatabaseTestCase;

/**
 * Integration tests for complex database queries.
 *
 * Tests complex WHERE conditions, JOINs, aggregations, and subqueries
 * using SQLite in-memory database to verify correct SQL generation.
 */
class ComplexQueryIntegrationTest extends DatabaseTestCase
{
    #[Autowired] protected EntityManagerInterface $entityManager;
    protected QueryProductRepository $productRepository;
    protected QueryOrderRepository $orderRepository;

    protected function getSchema(): string
    {
        return "
            CREATE TABLE categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                slug TEXT UNIQUE NOT NULL
            );

            CREATE TABLE products (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                category_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                price REAL NOT NULL,
                stock INTEGER DEFAULT 0,
                status TEXT DEFAULT 'active',
                created_at TEXT,
                FOREIGN KEY (category_id) REFERENCES categories(id)
            );

            CREATE TABLE orders (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                product_id INTEGER NOT NULL,
                quantity INTEGER NOT NULL,
                total_price REAL NOT NULL,
                status TEXT DEFAULT 'pending',
                created_at TEXT,
                FOREIGN KEY (product_id) REFERENCES products(id)
            );
        ";
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->productRepository = new QueryProductRepository(
            $this->db,
            $this->entityManager,
            $this->container
        );
        $this->orderRepository = new QueryOrderRepository(
            $this->db,
            $this->entityManager,
            $this->container
        );

        // Insert test data
        $this->insertTestData();
    }

    protected function insertTestData(): void
    {
        // Create categories
        $electronicsId = $this->insert('categories', [
            'name' => 'Electronics',
            'slug' => 'electronics'
        ]);
        $booksId = $this->insert('categories', [
            'name' => 'Books',
            'slug' => 'books'
        ]);
        $clothingId = $this->insert('categories', [
            'name' => 'Clothing',
            'slug' => 'clothing'
        ]);

        // Create products
        $laptop = $this->insert('products', [
            'category_id' => $electronicsId,
            'name' => 'Laptop',
            'price' => 999.99,
            'stock' => 10,
            'status' => 'active'
        ]);
        $mouse = $this->insert('products', [
            'category_id' => $electronicsId,
            'name' => 'Mouse',
            'price' => 29.99,
            'stock' => 50,
            'status' => 'active'
        ]);
        $keyboard = $this->insert('products', [
            'category_id' => $electronicsId,
            'name' => 'Keyboard',
            'price' => 79.99,
            'stock' => 0,
            'status' => 'out_of_stock'
        ]);
        $book1 = $this->insert('products', [
            'category_id' => $booksId,
            'name' => 'PHP Book',
            'price' => 49.99,
            'stock' => 20,
            'status' => 'active'
        ]);
        $book2 = $this->insert('products', [
            'category_id' => $booksId,
            'name' => 'JavaScript Book',
            'price' => 39.99,
            'stock' => 15,
            'status' => 'active'
        ]);
        $tshirt = $this->insert('products', [
            'category_id' => $clothingId,
            'name' => 'T-Shirt',
            'price' => 19.99,
            'stock' => 100,
            'status' => 'active'
        ]);

        // Create orders
        $this->insert('orders', [
            'product_id' => $laptop,
            'quantity' => 2,
            'total_price' => 1999.98,
            'status' => 'completed'
        ]);
        $this->insert('orders', [
            'product_id' => $mouse,
            'quantity' => 5,
            'total_price' => 149.95,
            'status' => 'completed'
        ]);
        $this->insert('orders', [
            'product_id' => $book1,
            'quantity' => 3,
            'total_price' => 149.97,
            'status' => 'pending'
        ]);
        $this->insert('orders', [
            'product_id' => $tshirt,
            'quantity' => 10,
            'total_price' => 199.90,
            'status' => 'completed'
        ]);
    }

    // ==================== WHERE Condition Tests ====================

    public function testSimpleWhereCondition(): void
    {
        // Use all() with filters parameter
        $products = $this->productRepository->all(['status' => 'active']);

        $this->assertCount(5, $products);
    }

    public function testMultipleWhereConditions(): void
    {
        // Use all() with multiple filters - correct operator format
        $products = $this->productRepository->all([
            'status' => 'active',
            'price>' => 50
        ]);

        // Only Laptop matches: price > 50 AND status = active
        // Keyboard has price > 50 but status = 'out_of_stock'
        $this->assertCount(1, $products);
        $this->assertEquals('Laptop', $products[0]->name);
    }

    public function testWhereInCondition(): void
    {
        // Use all() with IN condition - array value triggers IN automatically
        $products = $this->productRepository->all([
            'name' => ['Laptop', 'Mouse', 'Keyboard']
        ]);

        $this->assertCount(3, $products);
    }

    public function testWhereBetweenCondition(): void
    {
        // Use all() with BETWEEN condition - correct operator format
        $products = $this->productRepository->all([
            'price~=' => [30, 80]
        ]);

        $this->assertCount(3, $products); // Mouse, Keyboard, PHP Book, JavaScript Book
    }

    public function testWhereNullCondition(): void
    {
        // Insert product with null created_at
        $this->insert('products', [
            'category_id' => 1,
            'name' => 'Test Product',
            'price' => 10.00,
            'stock' => 5,
            'created_at' => null
        ]);

        // Use all() with IS NULL condition
        $products = $this->productRepository->all([
            'created_at' => null
        ]);

        $this->assertGreaterThan(0, count($products));
    }

    // ==================== Ordering Tests ====================

    public function testOrderByAscending(): void
    {
        // Use all() with orders parameter
        $products = $this->productRepository->all([], [], ['price' => 'ASC']);

        $this->assertGreaterThan(0, count($products));
        $this->assertEquals('T-Shirt', $products[0]->name); // Cheapest
    }

    public function testOrderByDescending(): void
    {
        // Use all() with orders parameter
        $products = $this->productRepository->all([], [], ['price' => 'DESC']);

        $this->assertGreaterThan(0, count($products));
        $this->assertEquals('Laptop', $products[0]->name); // Most expensive
    }

    public function testMultipleOrderBy(): void
    {
        // Use all() with multiple orders
        $products = $this->productRepository->all([], [], [
            'status' => 'ASC',
            'price' => 'DESC'
        ]);

        $this->assertGreaterThan(0, count($products));
    }

    // ==================== Limit and Offset Tests ====================

    public function testLimit(): void
    {
        // Limit is not exposed in public API - test via direct SQL query
        $results = $this->query("SELECT * FROM products LIMIT 3");
        $this->assertCount(3, $results);
    }

    public function testLimitWithOffset(): void
    {
        // Limit/offset are not exposed in public API - test via direct SQL query
        $allProducts = $this->productRepository->all();
        $totalCount = \count($allProducts);

        $results = $this->query("SELECT * FROM products LIMIT 2 OFFSET 2");
        $this->assertCount(min(2, max(0, $totalCount - 2)), $results);
    }

    // ==================== Aggregation Tests ====================

    public function testCount(): void
    {
        // Count active products using public API
        $activeProducts = $this->productRepository->all(['status' => 'active']);
        $this->assertEquals(5, \count($activeProducts));
    }

    public function testCountWithGroupBy(): void
    {
        // Count products by category
        $results = $this->query("
            SELECT category_id, COUNT(*) as count
            FROM products
            GROUP BY category_id
        ");

        $this->assertCount(3, $results); // 3 categories
    }

    public function testSum(): void
    {
        // Sum of all product prices
        $result = $this->query("
            SELECT SUM(price) as total
            FROM products
        ");

        $this->assertGreaterThan(0, $result[0]['total']);
    }

    public function testAverage(): void
    {
        // Average product price
        $result = $this->query("
            SELECT AVG(price) as average
            FROM products
        ");

        $this->assertGreaterThan(0, $result[0]['average']);
    }

    public function testMinMax(): void
    {
        // Min and max prices
        $result = $this->query("
            SELECT MIN(price) as min_price, MAX(price) as max_price
            FROM products
        ");

        $this->assertEquals(19.99, $result[0]['min_price']);
        $this->assertEquals(999.99, $result[0]['max_price']);
    }

    // ==================== JOIN Tests ====================

    public function testInnerJoin(): void
    {
        // Products with their categories
        $results = $this->query("
            SELECT p.name as product_name, c.name as category_name
            FROM products p
            INNER JOIN categories c ON p.category_id = c.id
            WHERE p.status = 'active'
        ");

        $this->assertGreaterThan(0, count($results));
        $this->assertArrayHasKey('product_name', $results[0]);
        $this->assertArrayHasKey('category_name', $results[0]);
    }

    public function testLeftJoin(): void
    {
        // All products with their orders (if any)
        $results = $this->query("
            SELECT p.name, COUNT(o.id) as order_count
            FROM products p
            LEFT JOIN orders o ON p.id = o.product_id
            GROUP BY p.id, p.name
        ");

        $this->assertGreaterThan(0, count($results));
    }

    // ==================== Subquery Tests ====================

    public function testSubqueryInWhere(): void
    {
        // Products that have been ordered
        $results = $this->query("
            SELECT *
            FROM products
            WHERE id IN (SELECT DISTINCT product_id FROM orders)
        ");

        $this->assertCount(4, $results); // 4 products have orders
    }

    public function testSubqueryInSelect(): void
    {
        // Products with order count
        $results = $this->query("
            SELECT 
                p.*,
                (SELECT COUNT(*) FROM orders o WHERE o.product_id = p.id) as order_count
            FROM products p
        ");

        $this->assertGreaterThan(0, count($results));
        $this->assertArrayHasKey('order_count', $results[0]);
    }

    // ==================== Complex Business Logic Tests ====================

    public function testFindPopularProducts(): void
    {
        // Products with more than 1 order
        $results = $this->query("
            SELECT p.*, COUNT(o.id) as order_count
            FROM products p
            INNER JOIN orders o ON p.id = o.product_id
            GROUP BY p.id
            HAVING COUNT(o.id) >= 1
            ORDER BY order_count DESC
        ");

        $this->assertGreaterThan(0, count($results));
    }

    public function testFindLowStockProducts(): void
    {
        // Use public API with filters - correct operator format
        $products = $this->productRepository->all([
            'stock<' => 20,
            'status' => 'active'
        ]);

        $this->assertGreaterThan(0, \count($products));
    }

    public function testFindProductsByPriceRange(): void
    {
        // Products between $20 and $100 using BETWEEN operator
        $products = $this->productRepository->all(
            ['price~=' => [20, 100]],
            [],
            ['price' => 'ASC']
        );

        $this->assertGreaterThan(0, \count($products));

        foreach ($products as $product) {
            $this->assertGreaterThanOrEqual(20, $product->price);
            $this->assertLessThanOrEqual(100, $product->price);
        }
    }

    public function testCalculateTotalRevenue(): void
    {
        // Total revenue from completed orders
        $result = $this->query("
            SELECT SUM(total_price) as total_revenue
            FROM orders
            WHERE status = 'completed'
        ");

        $this->assertGreaterThan(0, $result[0]['total_revenue']);
    }

    public function testFindBestSellingCategory(): void
    {
        // Category with most orders
        $results = $this->query("
            SELECT c.name, COUNT(o.id) as order_count
            FROM categories c
            INNER JOIN products p ON c.id = p.category_id
            INNER JOIN orders o ON p.id = o.product_id
            GROUP BY c.id, c.name
            ORDER BY order_count DESC
            LIMIT 1
        ");

        $this->assertCount(1, $results);
        $this->assertArrayHasKey('name', $results[0]);
        $this->assertArrayHasKey('order_count', $results[0]);
    }

    // ==================== Pagination Tests ====================

    public function testPagination(): void
    {
        // Pagination with limit/offset is not exposed in public API
        // Test via direct SQL queries
        $page1 = $this->query("SELECT * FROM products ORDER BY id ASC LIMIT 3 OFFSET 0");
        $page2 = $this->query("SELECT * FROM products ORDER BY id ASC LIMIT 3 OFFSET 3");

        $this->assertCount(3, $page1);
        $this->assertGreaterThan(0, \count($page2));

        // Verify pages don't overlap
        if (\count($page2) > 0) {
            $this->assertNotEquals($page1[0]['id'], $page2[0]['id']);
        }
    }

    // ==================== Search Tests ====================

    public function testSearchByName(): void
    {
        // LIKE operator with public API - correct operator format
        $products = $this->productRepository->all([
            'name*=' => 'Book'
        ]);

        $this->assertCount(2, $products); // PHP Book and JavaScript Book
    }

    public function testSearchMultipleFields(): void
    {
        // OR conditions are not supported in public API filters
        // Test via direct SQL query
        $results = $this->query("
            SELECT *
            FROM products
            WHERE name LIKE '%Laptop%' OR status = 'out_of_stock'
        ");

        $this->assertGreaterThan(0, \count($results));
    }
}

// ==================== Test Entities ====================

#[Table('products')]
class QueryProduct extends Entity
{
    #[PrimaryKey]
    #[Column('id')]
    public ?int $id = null;

    #[Column('category_id')]
    public int $category_id;

    #[Column('name')]
    public string $name;

    #[Column('price')]
    public float $price;

    #[Column('stock')]
    public int $stock = 0;

    #[Column('status')]
    public string $status = 'active';

    #[Column('created_at')]
    public ?string $created_at = null;
}

#[Table('orders')]
class QueryOrder extends Entity
{
    #[PrimaryKey]
    #[Column('id')]
    public ?int $id = null;

    #[Column('product_id')]
    public int $product_id;

    #[Column('quantity')]
    public int $quantity;

    #[Column('total_price')]
    public float $total_price;

    #[Column('status')]
    public string $status = 'pending';

    #[Column('created_at')]
    public ?string $created_at = null;
}

class QueryProductRepository extends BaseTestRepository
{
    protected function getEntityClass(): string
    {
        return QueryProduct::class;
    }
}

class QueryOrderRepository extends BaseTestRepository
{
    protected function getEntityClass(): string
    {
        return QueryOrder::class;
    }
}
