<?php

namespace App\Service;

use App\DTO\ReservationCreateRequest;
use App\Entity\Reservation;
use App\Enum\ReservationStatus;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Reservation Service
 *
 * Encapsulates reservation creation and status transitions so controllers remain thin
 * (receive/validate requests and format responses only).
 */
class ReservationService
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /**
     * Create and persist a Reservation from validated DTO data.
     *
     * Side effects:
     * - Sets initial status to PENDING (via ReservationStatus enum)
     * - Initializes confirmation flags to false
     *
     * @param ReservationCreateRequest $dto Validated reservation request data
     * @return Reservation Persisted reservation entity
     */
    public function createReservation(ReservationCreateRequest $dto): Reservation
    {
        $reservation = new Reservation();
        $reservation->setFirstName($dto->firstName);
        $reservation->setLastName($dto->lastName);
        $reservation->setEmail($dto->email);
        $reservation->setPhone($dto->phone);
        $reservation->setDate(new \DateTime($dto->date));
        $reservation->setTime($dto->time);
        $reservation->setGuests($dto->guests);
        $reservation->setMessage($dto->message);
        $reservation->setStatus(ReservationStatus::PENDING);
        $reservation->setIsConfirmed(false);

        $this->entityManager->persist($reservation);
        $this->entityManager->flush();

        return $reservation;
    }

    /**
     * Persist a Reservation entity coming from a legacy form.
     * Ensures initial domain invariants are set when absent.
     */
    public function createReservationFromEntity(Reservation $reservation): Reservation
    {
        if ($reservation->getStatus() === null) {
            $reservation->setStatus(ReservationStatus::PENDING);
        }
        if ($reservation->getIsConfirmed() === null) {
            $reservation->setIsConfirmed(false);
        }

        $this->entityManager->persist($reservation);
        $this->entityManager->flush();

        return $reservation;
    }

    /**
     * Change reservation status and synchronize auxiliary fields.
     *
     * Automatically sets confirmation timestamp/message when moving to CONFIRMED.
     *
     * @param Reservation $reservation Target reservation
     * @param ReservationStatus $status New status
     * @param string|null $message Optional confirmation message stored on the reservation
     */
    public function changeStatus(Reservation $reservation, ReservationStatus $status, ?string $message = null): void
    {
        $reservation->setStatus($status);

        if ($status === ReservationStatus::CONFIRMED) {
            $reservation->setConfirmedAt(new \DateTimeImmutable());
            if ($message !== null) {
                $reservation->setConfirmationMessage($message);
            }
        }

        $this->entityManager->flush();
    }
}


