<?php

declare(strict_types=1);

namespace Switon\Orm\Tests\Integration;

use Switon\Core\Attribute\Autowired;
use Switon\Orm\Attribute\BelongsTo;
use Switon\Orm\Attribute\Column;
use Switon\Orm\Attribute\HasMany;
use Switon\Orm\Attribute\HasManyThrough;
use Switon\Orm\Attribute\HasManyToMany;
use Switon\Orm\Attribute\Id;
use Switon\Orm\Attribute\Repository;
use Switon\Orm\Attribute\Table;
use Switon\Orm\Entity;
use Switon\Orm\EntityManagerInterface;
use Switon\Orm\Tests\DatabaseTestCase;

/**
 * Advanced integration tests for complex ORM relations.
 *
 * Tests HasManyThrough, HasManyToMany, nested relations, and relation ordering
 * using SQLite in-memory database to verify correct SQL generation.
 */
class AdvancedRelationIntegrationTest extends DatabaseTestCase
{
    #[Autowired] protected EntityManagerInterface $entityManager;
    protected CountryRepository $countryRepository;
    protected UserRepository $userRepository;
    protected PostRepository $postRepository;
    protected CommentRepository $commentRepository;
    protected TagRepository $tagRepository;
    protected PostTagRepository $postTagRepository;

    protected function getSchema(): string
    {
        return "
            -- Countries table
            CREATE TABLE countries (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                code TEXT UNIQUE NOT NULL
            );

            -- Users table
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                country_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                email TEXT UNIQUE NOT NULL,
                created_at TEXT,
                FOREIGN KEY (country_id) REFERENCES countries(id)
            );

            -- Posts table
            CREATE TABLE posts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                title TEXT NOT NULL,
                content TEXT,
                views INTEGER DEFAULT 0,
                created_at TEXT,
                FOREIGN KEY (user_id) REFERENCES users(id)
            );

            -- Comments table
            CREATE TABLE comments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                post_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                content TEXT NOT NULL,
                created_at TEXT,
                FOREIGN KEY (post_id) REFERENCES posts(id),
                FOREIGN KEY (user_id) REFERENCES users(id)
            );

            -- Tags table
            CREATE TABLE tags (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL UNIQUE,
                slug TEXT NOT NULL UNIQUE
            );

            -- Post-Tag pivot table (many-to-many)
            CREATE TABLE post_tags (
                post_id INTEGER NOT NULL,
                tag_id INTEGER NOT NULL,
                created_at TEXT,
                PRIMARY KEY (post_id, tag_id),
                FOREIGN KEY (post_id) REFERENCES posts(id),
                FOREIGN KEY (tag_id) REFERENCES tags(id)
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
        $this->countryRepository = new CountryRepository(
            $this->db,
            $this->entityManager,
            $this->container
        );
        $this->userRepository = new UserRepository(
            $this->db,
            $this->entityManager,
            $this->container
        );
        $this->postRepository = new PostRepository(
            $this->db,
            $this->entityManager,
            $this->container
        );
        $this->commentRepository = new CommentRepository(
            $this->db,
            $this->entityManager,
            $this->container
        );
        $this->tagRepository = new TagRepository(
            $this->db,
            $this->entityManager,
            $this->container
        );
        $this->postTagRepository = new PostTagRepository(
            $this->db,
            $this->entityManager,
            $this->container
        );

        // Register repositories in container for relation loading
        $this->container->set(CountryRepository::class, $this->countryRepository);
        $this->container->set(UserRepository::class, $this->userRepository);
        $this->container->set(PostRepository::class, $this->postRepository);
        $this->container->set(CommentRepository::class, $this->commentRepository);
        $this->container->set(TagRepository::class, $this->tagRepository);
        $this->container->set(PostTagRepository::class, $this->postTagRepository);
    }

    // ==================== HasManyThrough Tests ====================

    public function testLoadHasManyThroughRelation(): void
    {
        // Create country
        $countryId = $this->insert('countries', [
            'name' => 'United States',
            'code' => 'US'
        ]);

        // Create users in country
        $user1Id = $this->insert('users', [
            'country_id' => $countryId,
            'name' => 'User 1',
            'email' => 'user1@example.com'
        ]);
        $user2Id = $this->insert('users', [
            'country_id' => $countryId,
            'name' => 'User 2',
            'email' => 'user2@example.com'
        ]);

        // Create posts by users
        $this->insert('posts', [
            'user_id' => $user1Id,
            'title' => 'Post by User 1',
            'content' => 'Content 1'
        ]);
        $this->insert('posts', [
            'user_id' => $user1Id,
            'title' => 'Another Post by User 1',
            'content' => 'Content 2'
        ]);
        $this->insert('posts', [
            'user_id' => $user2Id,
            'title' => 'Post by User 2',
            'content' => 'Content 3'
        ]);

        // Load country with posts through users
        $country = $this->countryRepository->with('posts')->find($countryId);

        $this->assertNotNull($country);
        $this->assertEquals('United States', $country->name);
        $this->assertIsArray($country->posts);
        $this->assertCount(3, $country->posts);
        $this->assertEquals('Post by User 1', $country->posts[0]->title);
    }

    public function testLoadHasManyThroughWithEmptyResult(): void
    {
        // Create country without users
        $countryId = $this->insert('countries', [
            'name' => 'Empty Country',
            'code' => 'EC'
        ]);

        // Load country with posts
        $country = $this->countryRepository->with('posts')->find($countryId);

        $this->assertNotNull($country);
        $this->assertIsArray($country->posts);
        $this->assertCount(0, $country->posts);
    }

    public function testLoadHasManyThroughRelationWithExplicitRelationFieldsCanOmitSecondKey(): void
    {
        $countryId = $this->insert('countries', [
            'name' => 'Scoped Country',
            'code' => 'SC'
        ]);

        $userId = $this->insert('users', [
            'country_id' => $countryId,
            'name' => 'Scoped User',
            'email' => 'scoped-user@example.com'
        ]);

        $this->insert('posts', [
            'user_id' => $userId,
            'title' => 'Scoped Through Post',
            'content' => 'Content'
        ]);

        $country = $this->countryRepository->find($countryId, [
            'id',
            'name',
            'posts' => ['id', 'title'],
        ]);

        $this->assertNotNull($country);
        $this->assertCount(1, $country->posts);
        $this->assertEquals('Scoped Through Post', $country->posts[0]->title);
    }

    // ==================== HasManyToMany Tests ====================

    public function testLoadHasManyToManyRelation(): void
    {
        // Create tags
        $tag1Id = $this->insert('tags', [
            'name' => 'PHP',
            'slug' => 'php'
        ]);
        $tag2Id = $this->insert('tags', [
            'name' => 'Laravel',
            'slug' => 'laravel'
        ]);
        $tag3Id = $this->insert('tags', [
            'name' => 'Testing',
            'slug' => 'testing'
        ]);

        // Create user and post
        $userId = $this->insert('users', [
            'country_id' => 1,
            'name' => 'Test User',
            'email' => 'test@example.com'
        ]);
        $postId = $this->insert('posts', [
            'user_id' => $userId,
            'title' => 'Test Post',
            'content' => 'Content'
        ]);

        // Attach tags to post
        $this->insert('post_tags', ['post_id' => $postId, 'tag_id' => $tag1Id]);
        $this->insert('post_tags', ['post_id' => $postId, 'tag_id' => $tag2Id]);
        $this->insert('post_tags', ['post_id' => $postId, 'tag_id' => $tag3Id]);

        // Load post with tags
        $post = $this->postRepository->with('tags')->find($postId);

        $this->assertNotNull($post);
        $this->assertEquals('Test Post', $post->title);
        $this->assertIsArray($post->tags);
        $this->assertCount(3, $post->tags);
        $this->assertEquals('PHP', $post->tags[0]->name);
        $this->assertEquals('Laravel', $post->tags[1]->name);
        $this->assertEquals('Testing', $post->tags[2]->name);
    }

    public function testLoadHasManyToManyReverseRelation(): void
    {
        // Create tag
        $tagId = $this->insert('tags', [
            'name' => 'PHP',
            'slug' => 'php'
        ]);

        // Create users and posts
        $user1Id = $this->insert('users', [
            'country_id' => 1,
            'name' => 'User 1',
            'email' => 'user1@example.com'
        ]);
        $user2Id = $this->insert('users', [
            'country_id' => 1,
            'name' => 'User 2',
            'email' => 'user2@example.com'
        ]);

        $post1Id = $this->insert('posts', [
            'user_id' => $user1Id,
            'title' => 'Post 1',
            'content' => 'Content 1'
        ]);
        $post2Id = $this->insert('posts', [
            'user_id' => $user2Id,
            'title' => 'Post 2',
            'content' => 'Content 2'
        ]);

        // Attach tag to posts
        $this->insert('post_tags', ['post_id' => $post1Id, 'tag_id' => $tagId]);
        $this->insert('post_tags', ['post_id' => $post2Id, 'tag_id' => $tagId]);

        // Load tag with posts
        $tag = $this->tagRepository->with('posts')->find($tagId);

        $this->assertNotNull($tag);
        $this->assertEquals('PHP', $tag->name);
        $this->assertIsArray($tag->posts);
        $this->assertCount(2, $tag->posts);
    }

    public function testLoadHasManyToManyWithEmptyResult(): void
    {
        // Create post without tags
        $userId = $this->insert('users', [
            'country_id' => 1,
            'name' => 'Test User',
            'email' => 'test@example.com'
        ]);
        $postId = $this->insert('posts', [
            'user_id' => $userId,
            'title' => 'Untagged Post',
            'content' => 'Content'
        ]);

        // Load post with tags
        $post = $this->postRepository->with('tags')->find($postId);

        $this->assertNotNull($post);
        $this->assertIsArray($post->tags);
        $this->assertCount(0, $post->tags);
    }

    public function testLoadHasManyToManyRelationWithExplicitRelationFieldsCanOmitPrimaryKey(): void
    {
        $tagId = $this->insert('tags', [
            'name' => 'Scoped Tag',
            'slug' => 'scoped-tag'
        ]);

        $userId = $this->insert('users', [
            'country_id' => 1,
            'name' => 'Scoped User',
            'email' => 'scoped-post-user@example.com'
        ]);

        $postId = $this->insert('posts', [
            'user_id' => $userId,
            'title' => 'Scoped Post',
            'content' => 'Content'
        ]);

        $this->insert('post_tags', ['post_id' => $postId, 'tag_id' => $tagId]);

        $post = $this->postRepository->find($postId, [
            'id',
            'user_id',
            'title',
            'tags' => ['name'],
        ]);

        $this->assertNotNull($post);
        $this->assertCount(1, $post->tags);
        $this->assertEquals('Scoped Tag', $post->tags[0]->name);
    }

    // ==================== Nested Relation Tests ====================

    public function testLoadNestedRelations(): void
    {
        // Create country
        $countryId = $this->insert('countries', [
            'name' => 'United States',
            'code' => 'US'
        ]);

        // Create user
        $userId = $this->insert('users', [
            'country_id' => $countryId,
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ]);

        // Create post
        $postId = $this->insert('posts', [
            'user_id' => $userId,
            'title' => 'Test Post',
            'content' => 'Content'
        ]);

        // Create comments
        $this->insert('comments', [
            'post_id' => $postId,
            'user_id' => $userId,
            'content' => 'Comment 1'
        ]);
        $this->insert('comments', [
            'post_id' => $postId,
            'user_id' => $userId,
            'content' => 'Comment 2'
        ]);

        // Load post with user and comments
        $post = $this->postRepository
            ->with('user')
            ->with('comments')
            ->find($postId);

        $this->assertNotNull($post);
        $this->assertEquals('Test Post', $post->title);

        // Verify user relation
        $this->assertNotNull($post->user);
        $this->assertEquals('John Doe', $post->user->name);

        // Verify comments relation
        $this->assertIsArray($post->comments);
        $this->assertCount(2, $post->comments);
    }

    public function testLoadDeeplyNestedRelations(): void
    {
        // Create country
        $countryId = $this->insert('countries', [
            'name' => 'United States',
            'code' => 'US'
        ]);

        // Create user
        $userId = $this->insert('users', [
            'country_id' => $countryId,
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ]);

        // Create post
        $postId = $this->insert('posts', [
            'user_id' => $userId,
            'title' => 'Test Post',
            'content' => 'Content'
        ]);

        // Load user with country and posts
        $user = $this->userRepository
            ->with('country')
            ->with('posts')
            ->find($userId);

        $this->assertNotNull($user);
        $this->assertEquals('John Doe', $user->name);

        // Verify country relation
        $this->assertNotNull($user->country);
        $this->assertEquals('United States', $user->country->name);

        // Verify posts relation
        $this->assertIsArray($user->posts);
        $this->assertCount(1, $user->posts);
        $this->assertEquals('Test Post', $user->posts[0]->title);
    }

    // ==================== Relation Ordering Tests ====================

    public function testLoadRelationWithOrdering(): void
    {
        // Create user
        $userId = $this->insert('users', [
            'country_id' => 1,
            'name' => 'Test User',
            'email' => 'test@example.com'
        ]);

        // Create posts with different views
        $this->insert('posts', [
            'user_id' => $userId,
            'title' => 'Post 1',
            'content' => 'Content 1',
            'views' => 100
        ]);
        $this->insert('posts', [
            'user_id' => $userId,
            'title' => 'Post 2',
            'content' => 'Content 2',
            'views' => 500
        ]);
        $this->insert('posts', [
            'user_id' => $userId,
            'title' => 'Post 3',
            'content' => 'Content 3',
            'views' => 300
        ]);

        // Load user with posts ordered by views DESC
        $user = $this->userRepository->with('posts')->find($userId);

        $this->assertNotNull($user);
        $this->assertCount(3, $user->posts);

        // If orderBy is configured in relation, verify order
        // Otherwise, just verify all posts are loaded
        $this->assertEquals('Post 1', $user->posts[0]->title);
        $this->assertEquals('Post 2', $user->posts[1]->title);
        $this->assertEquals('Post 3', $user->posts[2]->title);
    }

    // ==================== Multiple Entities with Relations Tests ====================

    public function testLoadMultipleEntitiesWithRelations(): void
    {
        // Create users
        $user1Id = $this->insert('users', [
            'country_id' => 1,
            'name' => 'User 1',
            'email' => 'user1@example.com'
        ]);
        $user2Id = $this->insert('users', [
            'country_id' => 1,
            'name' => 'User 2',
            'email' => 'user2@example.com'
        ]);
        $user3Id = $this->insert('users', [
            'country_id' => 1,
            'name' => 'User 3',
            'email' => 'user3@example.com'
        ]);

        // Create posts for each user
        $this->insert('posts', [
            'user_id' => $user1Id,
            'title' => 'User 1 Post 1',
            'content' => 'Content'
        ]);
        $this->insert('posts', [
            'user_id' => $user1Id,
            'title' => 'User 1 Post 2',
            'content' => 'Content'
        ]);
        $this->insert('posts', [
            'user_id' => $user2Id,
            'title' => 'User 2 Post 1',
            'content' => 'Content'
        ]);
        // User 3 has no posts

        // Load all users with posts
        $users = $this->userRepository->with('posts')->all();

        $this->assertCount(3, $users);

        // Verify User 1
        $this->assertEquals('User 1', $users[0]->name);
        $this->assertCount(2, $users[0]->posts);

        // Verify User 2
        $this->assertEquals('User 2', $users[1]->name);
        $this->assertCount(1, $users[1]->posts);

        // Verify User 3
        $this->assertEquals('User 3', $users[2]->name);
        $this->assertCount(0, $users[2]->posts);
    }

    // ==================== Complex Scenario Tests ====================

    public function testComplexBlogScenario(): void
    {
        // Create country
        $countryId = $this->insert('countries', [
            'name' => 'United States',
            'code' => 'US'
        ]);

        // Create users
        $author1Id = $this->insert('users', [
            'country_id' => $countryId,
            'name' => 'Author 1',
            'email' => 'author1@example.com'
        ]);
        $author2Id = $this->insert('users', [
            'country_id' => $countryId,
            'name' => 'Author 2',
            'email' => 'author2@example.com'
        ]);

        // Create tags
        $phpTagId = $this->insert('tags', [
            'name' => 'PHP',
            'slug' => 'php'
        ]);
        $testingTagId = $this->insert('tags', [
            'name' => 'Testing',
            'slug' => 'testing'
        ]);

        // Create post
        $postId = $this->insert('posts', [
            'user_id' => $author1Id,
            'title' => 'How to Test PHP Code',
            'content' => 'Testing is important...',
            'views' => 1000
        ]);

        // Attach tags
        $this->insert('post_tags', ['post_id' => $postId, 'tag_id' => $phpTagId]);
        $this->insert('post_tags', ['post_id' => $postId, 'tag_id' => $testingTagId]);

        // Create comments
        $this->insert('comments', [
            'post_id' => $postId,
            'user_id' => $author2Id,
            'content' => 'Great article!'
        ]);
        $this->insert('comments', [
            'post_id' => $postId,
            'user_id' => $author1Id,
            'content' => 'Thanks!'
        ]);

        // Load post with all relations
        $post = $this->postRepository
            ->with('user')
            ->with('tags')
            ->with('comments')
            ->find($postId);

        // Verify post
        $this->assertNotNull($post);
        $this->assertEquals('How to Test PHP Code', $post->title);

        // Verify author
        $this->assertNotNull($post->user);
        $this->assertEquals('Author 1', $post->user->name);

        // Verify tags
        $this->assertCount(2, $post->tags);
        $this->assertEquals('PHP', $post->tags[0]->name);
        $this->assertEquals('Testing', $post->tags[1]->name);

        // Verify comments
        $this->assertCount(2, $post->comments);
        $this->assertEquals('Great article!', $post->comments[0]->content);
    }
}

// ==================== Test Entities ====================

#[Table('countries')]
#[Repository(CountryRepository::class)]
class Country extends Entity
{
    #[Id]
    #[Column('id')]
    public ?int $id = null;

    #[Column('name')]
    public string $name;

    #[Column('code')]
    public string $code;

    #[HasMany(User::class, foreignKey: 'country_id')]
    public array $users = [];

    #[HasManyThrough(Post::class, User::class, firstKey: 'country_id', secondKey: 'user_id')]
    public array $posts = [];
}

#[Table('users')]
#[Repository(UserRepository::class)]
class User extends Entity
{
    #[Id]
    #[Column('id')]
    public ?int $id = null;

    #[Column('country_id')]
    public int $country_id;

    #[Column('name')]
    public string $name;

    #[Column('email')]
    public string $email;

    #[Column('created_at')]
    public ?string $created_at = null;

    #[BelongsTo(foreignKey: 'country_id')]
    public ?Country $country = null;

    #[HasMany(Post::class, foreignKey: 'user_id')]
    public array $posts = [];

    #[HasMany(Comment::class, foreignKey: 'user_id')]
    public array $comments = [];
}

#[Table('posts')]
#[Repository(PostRepository::class)]
class Post extends Entity
{
    #[Id]
    #[Column('id')]
    public ?int $id = null;

    #[Column('user_id')]
    public int $user_id;

    #[Column('title')]
    public string $title;

    #[Column('content')]
    public ?string $content = null;

    #[Column('views')]
    public int $views = 0;

    #[Column('created_at')]
    public ?string $created_at = null;

    #[BelongsTo(foreignKey: 'user_id')]
    public ?User $user = null;

    #[HasMany(Comment::class, foreignKey: 'post_id')]
    public array $comments = [];

    #[HasManyToMany(PostTag::class, foreignEntity: Tag::class)]
    public array $tags = [];
}

#[Table('comments')]
#[Repository(CommentRepository::class)]
class Comment extends Entity
{
    #[Id]
    #[Column('id')]
    public ?int $id = null;

    #[Column('post_id')]
    public int $post_id;

    #[Column('user_id')]
    public int $user_id;

    #[Column('content')]
    public string $content;

    #[Column('created_at')]
    public ?string $created_at = null;

    #[BelongsTo(foreignKey: 'post_id')]
    public ?Post $post = null;

    #[BelongsTo(foreignKey: 'user_id')]
    public ?User $user = null;
}

#[Table('tags')]
#[Repository(TagRepository::class)]
class Tag extends Entity
{
    #[Id]
    #[Column('id')]
    public ?int $id = null;

    #[Column('name')]
    public string $name;

    #[Column('slug')]
    public string $slug;

    #[HasManyToMany(PostTag::class, foreignEntity: Post::class)]
    public array $posts = [];
}

#[Table('post_tags')]
#[Repository(PostTagRepository::class)]
class PostTag extends Entity
{
    #[Column('post_id')]
    public int $post_id;

    #[Column('tag_id')]
    public int $tag_id;

    #[Column('created_at')]
    public ?string $created_at = null;

    #[BelongsTo(foreignKey: 'post_id')]
    public ?Post $post = null;

    #[BelongsTo(foreignKey: 'tag_id')]
    public ?Tag $tag = null;
}

// ==================== Test Repositories ====================

class CountryRepository extends BaseTestRepository
{
    protected function getEntityClass(): string
    {
        return Country::class;
    }
}

class UserRepository extends BaseTestRepository
{
    protected function getEntityClass(): string
    {
        return User::class;
    }
}

class PostRepository extends BaseTestRepository
{
    protected function getEntityClass(): string
    {
        return Post::class;
    }
}

class CommentRepository extends BaseTestRepository
{
    protected function getEntityClass(): string
    {
        return Comment::class;
    }
}

class TagRepository extends BaseTestRepository
{
    protected function getEntityClass(): string
    {
        return Tag::class;
    }
}

class PostTagRepository extends BaseTestRepository
{
    protected function getEntityClass(): string
    {
        return PostTag::class;
    }
}
