<?php

declare(strict_types=1);

namespace App\Forwarder\Services;

use App\Forwarder\DTO\StepResult;

final class FlightService
{
    public function searchFlight(string $flightNumber): StepResult
    {
        return $this->notImplemented('search_flight', [
            'flight_number' => $flightNumber,
        ]);
    }

    public function addFlight(string $flightNumber, string $flightDate): StepResult
    {
        return $this->notImplemented('add_flight', [
            'flight_number' => $flightNumber,
            'flight_date' => $flightDate,
        ]);
    }

    public function deleteFlight(string $flightNumber, string $flightDate): StepResult
    {
        return $this->notImplemented('delete_flight', [
            'flight_number' => $flightNumber,
            'flight_date' => $flightDate,
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function notImplemented(string $operation, array $payload): StepResult
    {
        return new StepResult(
            false,
            501,
            'NOT_IMPLEMENTED',
            array_merge(
                ['operation' => $operation],
                $payload
            ),
            0
        );
    }
}
