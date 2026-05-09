<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Integration;

use Switon\Core\Attribute\Autowired;
use Switon\Orm\Attribute\BelongsTo;
use Switon\Orm\Attribute\Column;
use Switon\Orm\Attribute\HasMany;
use Switon\Orm\Attribute\HasOne;
use Switon\Orm\Attribute\Id;
use Switon\Orm\Attribute\Repository;
use Switon\Orm\Attribute\Table;
use Switon\Orm\Entity;
use Switon\Orm\EntityManagerInterface;
use Switon\Orm\Exception\RelationFieldMissingException;
use Switon\Orm\Tests\DatabaseTestCase;

/**
 * Integration tests for ORM relation loading with real database.
 *
 * Tests various relationship types (HasMany, BelongsTo, HasOne) using
 * SQLite in-memory database to verify correct SQL generation and data loading.
 */
class RelationLoadingIntegrationTest extends DatabaseTestCase
{
    #[Autowired] protected EntityManagerInterface $entityManager;
    protected AuthorRepository $authorRepository;
    protected BookRepository $bookRepository;
    protected ProfileRepository $profileRepository;

    protected function getSchema(): string
    {
        return "
            CREATE TABLE authors (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT UNIQUE NOT NULL,
                created_at TEXT
            );

            CREATE TABLE books (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                author_id INTEGER NOT NULL,
                title TEXT NOT NULL,
                isbn TEXT UNIQUE,
                published_year INTEGER,
                created_at TEXT,
                FOREIGN KEY (author_id) REFERENCES authors(id)
            );

            CREATE TABLE profiles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                author_id INTEGER NOT NULL UNIQUE,
                bio TEXT,
                website TEXT,
                created_at TEXT,
                FOREIGN KEY (author_id) REFERENCES authors(id)
            );
        ";
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Skip if required packages not available
        if (!interface_exists(\Switon\Orm\RelationManagerInterface::class, true)) {
            $this->markTestSkipped('RelationManager not available');
        }

        // Create repository instances
        $this->authorRepository = new AuthorRepository(
            $this->db,
            $this->entityManager,
            $this->container
        );
        $this->bookRepository = new BookRepository(
            $this->db,
            $this->entityManager,
            $this->container
        );
        $this->profileRepository = new ProfileRepository(
            $this->db,
            $this->entityManager,
            $this->container
        );

        // Register repositories in container for relation loading
        $this->container->set(AuthorRepository::class, $this->authorRepository);
        $this->container->set(BookRepository::class, $this->bookRepository);
        $this->container->set(ProfileRepository::class, $this->profileRepository);
    }

    // ==================== HasMany Relation Tests ====================

    public function testLoadHasManyRelation(): void
    {
        // Create author
        $authorId = $this->insert('authors', [
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ]);

        // Create books
        $this->insert('books', [
            'author_id' => $authorId,
            'title' => 'Book 1',
            'isbn' => 'ISBN-001',
            'published_year' => 2020
        ]);
        $this->insert('books', [
            'author_id' => $authorId,
            'title' => 'Book 2',
            'isbn' => 'ISBN-002',
            'published_year' => 2021
        ]);
        $this->insert('books', [
            'author_id' => $authorId,
            'title' => 'Book 3',
            'isbn' => 'ISBN-003',
            'published_year' => 2022
        ]);

        // Load author with books
        $author = $this->authorRepository->with('books')->find($authorId);

        $this->assertNotNull($author);
        $this->assertEquals('John Doe', $author->name);
        $this->assertIsArray($author->books);
        $this->assertCount(3, $author->books);
        $this->assertEquals('Book 1', $author->books[0]->title);
        $this->assertEquals('Book 2', $author->books[1]->title);
        $this->assertEquals('Book 3', $author->books[2]->title);
    }

    public function testLoadHasManyRelationWithEmptyResult(): void
    {
        // Create author without books
        $authorId = $this->insert('authors', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com'
        ]);

        // Load author with books
        $author = $this->authorRepository->with('books')->find($authorId);

        $this->assertNotNull($author);
        $this->assertEquals('Jane Doe', $author->name);
        $this->assertIsArray($author->books);
        $this->assertCount(0, $author->books);
    }

    public function testLoadHasManyRelationForMultipleEntities(): void
    {
        // Create authors
        $author1Id = $this->insert('authors', [
            'name' => 'Author 1',
            'email' => 'author1@example.com'
        ]);
        $author2Id = $this->insert('authors', [
            'name' => 'Author 2',
            'email' => 'author2@example.com'
        ]);

        // Create books for author 1
        $this->insert('books', [
            'author_id' => $author1Id,
            'title' => 'Author 1 Book 1',
            'isbn' => 'ISBN-101'
        ]);
        $this->insert('books', [
            'author_id' => $author1Id,
            'title' => 'Author 1 Book 2',
            'isbn' => 'ISBN-102'
        ]);

        // Create books for author 2
        $this->insert('books', [
            'author_id' => $author2Id,
            'title' => 'Author 2 Book 1',
            'isbn' => 'ISBN-201'
        ]);

        // Load all authors with books
        $authors = $this->authorRepository->with('books')->all();

        $this->assertCount(2, $authors);

        // Verify author 1
        $this->assertEquals('Author 1', $authors[0]->name);
        $this->assertCount(2, $authors[0]->books);
        $this->assertEquals('Author 1 Book 1', $authors[0]->books[0]->title);

        // Verify author 2
        $this->assertEquals('Author 2', $authors[1]->name);
        $this->assertCount(1, $authors[1]->books);
        $this->assertEquals('Author 2 Book 1', $authors[1]->books[0]->title);
    }

    public function testLoadHasManyRelationWithExplicitRelationFieldsIncludingForeignKey(): void
    {
        $authorId = $this->insert('authors', [
            'name' => 'Field Scoped Author',
            'email' => 'scoped-author@example.com'
        ]);

        $this->insert('books', [
            'author_id' => $authorId,
            'title' => 'Scoped Book',
            'isbn' => 'ISBN-SCOPED'
        ]);

        $author = $this->authorRepository->find($authorId, [
            'id',
            'name',
            'books' => ['id', 'author_id', 'title'],
        ]);

        $this->assertNotNull($author);
        $this->assertCount(1, $author->books);
        $this->assertEquals('Scoped Book', $author->books[0]->title);
    }

    public function testLoadHasManyRelationWithExplicitRelationFieldsCanOmitForeignKey(): void
    {
        $authorId = $this->insert('authors', [
            'name' => 'Missing Key Author',
            'email' => 'missing-key-author@example.com'
        ]);

        $this->insert('books', [
            'author_id' => $authorId,
            'title' => 'Broken Book',
            'isbn' => 'ISBN-BROKEN'
        ]);

        $author = $this->authorRepository->find($authorId, [
            'id',
            'name',
            'books' => ['id', 'title'],
        ]);

        $this->assertNotNull($author);
        $this->assertCount(1, $author->books);
        $this->assertEquals('Broken Book', $author->books[0]->title);
    }

    public function testLoadHasManyRelationCanOmitParentPrimaryKeyInRootFields(): void
    {
        $authorId = $this->insert('authors', [
            'name' => 'Parent Keyless Author',
            'email' => 'parent-keyless-author@example.com'
        ]);

        $this->insert('books', [
            'author_id' => $authorId,
            'title' => 'Keyless Parent Book',
            'isbn' => 'ISBN-KEYLESS-PARENT'
        ]);

        $author = $this->authorRepository->find($authorId, [
            'name',
            'books' => ['id', 'title'],
        ]);

        $this->assertNotNull($author);
        $this->assertCount(1, $author->books);
        $this->assertEquals('Keyless Parent Book', $author->books[0]->title);
    }

    // ==================== BelongsTo Relation Tests ====================

    public function testLoadBelongsToRelation(): void
    {
        // Create author
        $authorId = $this->insert('authors', [
            'name' => 'Stephen King',
            'email' => 'stephen@example.com'
        ]);

        // Create book
        $bookId = $this->insert('books', [
            'author_id' => $authorId,
            'title' => 'The Shining',
            'isbn' => 'ISBN-SHINING',
            'published_year' => 1977
        ]);

        // Load book with author
        $book = $this->bookRepository->with('author')->find($bookId);

        $this->assertNotNull($book);
        $this->assertEquals('The Shining', $book->title);
        $this->assertNotNull($book->author);
        $this->assertEquals('Stephen King', $book->author->name);
        $this->assertEquals('stephen@example.com', $book->author->email);
    }

    public function testLoadBelongsToRelationForMultipleEntities(): void
    {
        // Create authors
        $author1Id = $this->insert('authors', [
            'name' => 'Author A',
            'email' => 'authora@example.com'
        ]);
        $author2Id = $this->insert('authors', [
            'name' => 'Author B',
            'email' => 'authorb@example.com'
        ]);

        // Create books
        $this->insert('books', [
            'author_id' => $author1Id,
            'title' => 'Book by A',
            'isbn' => 'ISBN-A'
        ]);
        $this->insert('books', [
            'author_id' => $author2Id,
            'title' => 'Book by B',
            'isbn' => 'ISBN-B'
        ]);

        // Load all books with authors
        $books = $this->bookRepository->with('author')->all();

        $this->assertCount(2, $books);
        $this->assertEquals('Book by A', $books[0]->title);
        $this->assertEquals('Author A', $books[0]->author->name);
        $this->assertEquals('Book by B', $books[1]->title);
        $this->assertEquals('Author B', $books[1]->author->name);
    }

    public function testLoadBelongsToRelationWithExplicitRelationFieldsCanOmitPrimaryKey(): void
    {
        $authorId = $this->insert('authors', [
            'name' => 'Trimmed Author',
            'email' => 'trimmed-author@example.com'
        ]);

        $bookId = $this->insert('books', [
            'author_id' => $authorId,
            'title' => 'Trimmed Book',
            'isbn' => 'ISBN-TRIMMED'
        ]);

        $book = $this->bookRepository->find($bookId, [
            'id',
            'author_id',
            'title',
            'author' => ['name'],
        ]);

        $this->assertNotNull($book);
        $this->assertNotNull($book->author);
        $this->assertEquals('Trimmed Author', $book->author->name);
    }

    public function testLoadBelongsToRelationThrowsWhenSourceFieldsOmitForeignKey(): void
    {
        $authorId = $this->insert('authors', [
            'name' => 'Keyless Relation Author',
            'email' => 'keyless-relation-author@example.com'
        ]);

        $bookId = $this->insert('books', [
            'author_id' => $authorId,
            'title' => 'Missing Author Key Book',
            'isbn' => 'ISBN-MISSING-AUTHOR-KEY'
        ]);

        $this->expectException(RelationFieldMissingException::class);
        $this->expectExceptionMessage('Missing field author_id in relation author');

        $this->bookRepository->find($bookId, [
            'id',
            'title',
            'author' => ['name'],
        ]);
    }

    // ==================== HasOne Relation Tests ====================

    public function testLoadHasOneRelation(): void
    {
        // Create author
        $authorId = $this->insert('authors', [
            'name' => 'J.K. Rowling',
            'email' => 'jk@example.com'
        ]);

        // Create profile
        $this->insert('profiles', [
            'author_id' => $authorId,
            'bio' => 'British author, best known for Harry Potter series',
            'website' => 'https://jkrowling.com'
        ]);

        // Load author with profile
        $author = $this->authorRepository->with('profile')->find($authorId);

        $this->assertNotNull($author);
        $this->assertEquals('J.K. Rowling', $author->name);
        $this->assertNotNull($author->profile);
        $this->assertEquals('British author, best known for Harry Potter series', $author->profile->bio);
        $this->assertEquals('https://jkrowling.com', $author->profile->website);
    }

    public function testLoadHasOneRelationWithNoRelatedRecord(): void
    {
        // Create author without profile
        $authorId = $this->insert('authors', [
            'name' => 'New Author',
            'email' => 'new@example.com'
        ]);

        // Load author with profile
        $author = $this->authorRepository->with('profile')->find($authorId);

        $this->assertNotNull($author);
        $this->assertEquals('New Author', $author->name);
        $this->assertNull($author->profile);
    }

    public function testLoadHasOneRelationWithExplicitRelationFieldsCanOmitForeignKey(): void
    {
        $authorId = $this->insert('authors', [
            'name' => 'Profiled Author',
            'email' => 'profiled@example.com'
        ]);

        $this->insert('profiles', [
            'author_id' => $authorId,
            'bio' => 'Profile bio',
            'website' => 'https://profiled.example.com'
        ]);

        $author = $this->authorRepository->find($authorId, [
            'id',
            'name',
            'profile' => ['id', 'bio'],
        ]);

        $this->assertNotNull($author);
        $this->assertNotNull($author->profile);
        $this->assertEquals('Profile bio', $author->profile->bio);
    }

    public function testLoadHasOneRelationCanOmitParentPrimaryKeyInRootFields(): void
    {
        $authorId = $this->insert('authors', [
            'name' => 'Profile Keyless Author',
            'email' => 'profile-keyless@example.com'
        ]);

        $this->insert('profiles', [
            'author_id' => $authorId,
            'bio' => 'Keyless profile bio',
            'website' => 'https://profile-keyless.example.com'
        ]);

        $author = $this->authorRepository->find($authorId, [
            'name',
            'profile' => ['id', 'bio'],
        ]);

        $this->assertNotNull($author);
        $this->assertNotNull($author->profile);
        $this->assertEquals('Keyless profile bio', $author->profile->bio);
    }

    // ==================== Multiple Relations Tests ====================

    public function testLoadMultipleRelations(): void
    {
        // Create author
        $authorId = $this->insert('authors', [
            'name' => 'Multi Relation Author',
            'email' => 'multi@example.com'
        ]);

        // Create profile
        $this->insert('profiles', [
            'author_id' => $authorId,
            'bio' => 'Author bio',
            'website' => 'https://example.com'
        ]);

        // Create books
        $this->insert('books', [
            'author_id' => $authorId,
            'title' => 'First Book',
            'isbn' => 'ISBN-FIRST'
        ]);
        $this->insert('books', [
            'author_id' => $authorId,
            'title' => 'Second Book',
            'isbn' => 'ISBN-SECOND'
        ]);

        // Load author with both profile and books
        $author = $this->authorRepository
            ->with('profile')
            ->with('books')
            ->find($authorId);

        $this->assertNotNull($author);
        $this->assertEquals('Multi Relation Author', $author->name);

        // Verify profile
        $this->assertNotNull($author->profile);
        $this->assertEquals('Author bio', $author->profile->bio);

        // Verify books
        $this->assertIsArray($author->books);
        $this->assertCount(2, $author->books);
        $this->assertEquals('First Book', $author->books[0]->title);
        $this->assertEquals('Second Book', $author->books[1]->title);
    }

    // ==================== Lazy Loading Tests ====================

    public function testLazyLoadingNotTriggeredWhenNotAccessed(): void
    {
        // Create author with books
        $authorId = $this->insert('authors', [
            'name' => 'Lazy Author',
            'email' => 'lazy@example.com'
        ]);
        $this->insert('books', [
            'author_id' => $authorId,
            'title' => 'Lazy Book',
            'isbn' => 'ISBN-LAZY'
        ]);

        // Load author WITHOUT eager loading
        $author = $this->authorRepository->find($authorId);

        $this->assertNotNull($author);
        $this->assertEquals('Lazy Author', $author->name);

        // Books should not be loaded yet (lazy loading)
        // This test just verifies the entity loads correctly without relations
    }
}

// ==================== Test Entities ====================

#[Table('authors')]
#[Repository(AuthorRepository::class)]
class Author extends Entity
{
    #[Id]
    #[Column('id')]
    public ?int $id = null;

    #[Column('name')]
    public string $name;

    #[Column('email')]
    public string $email;

    #[Column('created_at')]
    public ?string $created_at = null;

    #[HasMany(Book::class, foreignKey: 'author_id')]
    public array $books = [];

    #[HasOne(foreignKey: 'author_id')]
    public ?Profile $profile = null;
}

#[Table('books')]
#[Repository(BookRepository::class)]
class Book extends Entity
{
    #[Id]
    #[Column('id')]
    public ?int $id = null;

    #[Column('author_id')]
    public int $author_id;

    #[Column('title')]
    public string $title;

    #[Column('isbn')]
    public ?string $isbn = null;

    #[Column('published_year')]
    public ?int $published_year = null;

    #[Column('created_at')]
    public ?string $created_at = null;

    #[BelongsTo(foreignKey: 'author_id')]
    public ?Author $author = null;
}

#[Table('profiles')]
#[Repository(ProfileRepository::class)]
class Profile extends Entity
{
    #[Id]
    #[Column('id')]
    public ?int $id = null;

    #[Column('author_id')]
    public int $author_id;

    #[Column('bio')]
    public ?string $bio = null;

    #[Column('website')]
    public ?string $website = null;

    #[Column('created_at')]
    public ?string $created_at = null;

    #[BelongsTo(foreignKey: 'author_id')]
    public ?Author $author = null;
}

// ==================== Test Repositories ====================

class AuthorRepository extends BaseTestRepository
{
    protected function getEntityClass(): string
    {
        return Author::class;
    }
}

class BookRepository extends BaseTestRepository
{
    protected function getEntityClass(): string
    {
        return Book::class;
    }
}

class ProfileRepository extends BaseTestRepository
{
    protected function getEntityClass(): string
    {
        return Profile::class;
    }
}
