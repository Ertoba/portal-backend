<?php

namespace Tests\Feature;

use Tests\TestCase;

class RentalVehicleSearchValidationTest extends TestCase
{
    /**
     * Validation must run before Carbon receives an untrusted search date.
     * Disabling route middleware keeps this regression isolated from the database.
     */
    public function test_invalid_search_date_returns_validation_error_instead_of_server_error(): void
    {
        $this->withoutMiddleware();

        $response = $this->getJson('/api/v1/rental/vehicle/search?date=Invalid%20Date');

        $response
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.code', 'date');
    }
}
