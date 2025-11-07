<?php

namespace App\Tests\Integration\Service;

use App\DTO\ReviewCreateRequest;
use App\Entity\Review;
use App\Service\ReviewService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ReviewServiceIntegrationTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $sqliteUrl = 'sqlite:///:memory:';
        putenv('DATABASE_URL=' . $sqliteUrl);
        $_ENV['DATABASE_URL'] = $sqliteUrl;
        $_SERVER['DATABASE_URL'] = $sqliteUrl;

        self::bootKernel();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);

        // Recreate the schema so every test starts from a blank slate.
        $schemaTool = new SchemaTool($this->entityManager);
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->entityManager->close();
        self::ensureKernelShutdown();
    }

    public function testCreateReviewPersistsEntityWithModerationDisabled(): void
    {
        // --- Arrange ---------------------------------------------------------------------------------------------
        $dto = new ReviewCreateRequest();
        $dto->name = 'Alice';
        $dto->email = 'alice@example.test';
        $dto->rating = 5;
        $dto->comment = 'Fantastic experience!';

        $service = new ReviewService($this->entityManager);

        // --- Act -------------------------------------------------------------------------------------------------
        $review = $service->createReview($dto);

        // --- Assert ----------------------------------------------------------------------------------------------
        // Ensure the entity actually persisted and the moderation flag defaults to false.
        self::assertNotNull($review->getId());
        self::assertSame('Alice', $review->getName());
        self::assertSame('alice@example.test', $review->getEmail());
        self::assertSame(5, $review->getRating());
        self::assertSame('Fantastic experience!', $review->getComment());
        self::assertFalse($review->isIsApproved());

        // Cross-check with a fresh repository lookup to ensure the data hit the database.
        $persisted = $this->entityManager->getRepository(Review::class)->findAll();
        self::assertCount(1, $persisted);
    }
}

