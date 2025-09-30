<?php

namespace App\Service;

use App\Entity\Reservation;
use App\Repository\ReservationRepository;
use App\Repository\TableRepository;

/**
 * Local availability checker based on the configured tables and existing reservations.
 *
 * Rules:
 *  - Each reservation blocks a table slot for a fixed duration (2 hours)
 *  - Slot step is 30 minutes, but we simply check overlap by time ranges
 *  - Capacity model: we use the total capacity of all tables. If you need per-table
 *    placement (bin-packing), we can extend this later.
 */
class TableAvailabilityService
{
    /** Duration of a reservation in minutes */
    private int $durationMinutes = 120; // 2 hours

    public function __construct(
        private readonly TableRepository $tableRepository,
        private readonly ReservationRepository $reservationRepository,
    ) {
    }

    /**
     * Checks availability for the given date/time and number of guests.
     */
    public function isAvailable(\DateTimeInterface $date, string $time, int $guests): bool
    {
        $totalCapacity = $this->getTotalCapacity();
        if ($totalCapacity <= 0) {
            // no tables configured – allow for dev but realistically return false
            return true;
        }

        // Requested slot
        $start = self::combineDateAndTime($date, $time);
        $end   = (clone $start)->modify("+{$this->durationMinutes} minutes");

        // Sum guests of overlapping reservations
        $overlapGuests = $this->sumGuestsForOverlappingReservations($start, $end);

        return ($totalCapacity - $overlapGuests) >= $guests;
    }

    private function getTotalCapacity(): int
    {
        $tables = $this->tableRepository->findAll();
        $sum = 0;
        foreach ($tables as $t) {
            $sum += (int)($t->getCapacity() ?? 0);
        }
        return $sum;
    }

    private function sumGuestsForOverlappingReservations(\DateTimeInterface $reqStart, \DateTimeInterface $reqEnd): int
    {
        $reservations = $this->reservationRepository->findBy([
            'date' => \DateTime::createFromFormat('Y-m-d', $reqStart->format('Y-m-d')),
        ]);

        $sum = 0;
        /** @var Reservation $r */
        foreach ($reservations as $r) {
            $rStart = self::combineDateAndTime($r->getDate(), (string)$r->getTime());
            $rEnd   = (clone $rStart)->modify("+{$this->durationMinutes} minutes");
            if ($this->rangesOverlap($reqStart, $reqEnd, $rStart, $rEnd)) {
                $sum += (int)$r->getGuests();
            }
        }
        return $sum;
    }

    private static function combineDateAndTime(?\DateTimeInterface $date, string $time): \DateTime
    {
        $dateStr = $date?->format('Y-m-d') ?? (new \DateTime())->format('Y-m-d');
        // time stored as HH:MM
        $dt = \DateTime::createFromFormat('Y-m-d H:i', $dateStr.' '.$time) ?: new \DateTime($dateStr.' '.$time);
        return $dt;
    }

    private function rangesOverlap(\DateTimeInterface $aStart, \DateTimeInterface $aEnd, \DateTimeInterface $bStart, \DateTimeInterface $bEnd): bool
    {
        return ($aStart < $bEnd) && ($aEnd > $bStart);
    }
}


