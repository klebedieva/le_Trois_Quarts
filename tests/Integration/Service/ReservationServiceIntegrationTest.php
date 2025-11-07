<?php

namespace App\Tests\Integration\Service;

use App\DTO\ReservationCreateRequest;
use App\Entity\Reservation;
use App\Enum\ReservationStatus;
use App\Service\ReservationService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ReservationServiceIntegrationTest extends KernelTestCase
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

        // Reset the table structure for each test.
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

    public function testCreateReservationInitializesPendingReservation(): void
    {
        // --- Arrange ---------------------------------------------------------------------------------------------
        $dto = new ReservationCreateRequest();
        $dto->firstName = 'Paul';
        $dto->lastName = 'Martin';
        $dto->email = 'paul.martin@example.test';
        $dto->phone = '0611223344';
        $dto->date = (new \DateTime('+1 day'))->format('Y-m-d');
        $dto->time = '19:00';
        $dto->guests = 4;
        $dto->message = 'Corner table please.';

        $service = new ReservationService($this->entityManager);

        // --- Act -------------------------------------------------------------------------------------------------
        $reservation = $service->createReservation($dto);

        // --- Assert ----------------------------------------------------------------------------------------------
        self::assertNotNull($reservation->getId());
        self::assertSame('Paul', $reservation->getFirstName());
        self::assertSame('Martin', $reservation->getLastName());
        self::assertSame('paul.martin@example.test', $reservation->getEmail());
        self::assertSame('Corner table please.', $reservation->getMessage());
        self::assertSame(ReservationStatus::PENDING, $reservation->getStatus());
        self::assertFalse($reservation->isConfirmed());

        $persisted = $this->entityManager->getRepository(Reservation::class)->findAll();
        self::assertCount(1, $persisted);
    }
}

