<?php

namespace Modules\Rental\Traits;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Modules\Rental\Entities\Trips;

/**
 * Eager loading for the trip details page, shared by the admin and provider panels.
 *
 * Both views render the same trip through two relation paths: trip_details.tripVehicleDetails
 * (keyed by trip_details_id) and trip.vehicle_identity (keyed by trip_id) resolve to the same
 * trip_vehicle_details rows, and trip_details.vehicle and vehicle_identity.vehicles resolve to
 * the same vehicles. Left alone each path hydrates its own models and re-queries every nested
 * relation, so the page issues each of those queries twice.
 */
trait TripDetailsPageRelations
{
    /**
     * Statuses where the assign-vehicle button — the only reader of the vehicle's identity
     * list — cannot render. Skip that relation entirely for them.
     */
    private array $vehicleAssignmentClosedStatuses = ['ongoing', 'pending', 'completed', 'canceled'];

    /**
     * Relations the trip details page reads, for the initial query.
     */
    private function tripDetailsPageRelations(): array
    {
        return [
            'proDiscount',
            'customer' => function ($query) {
                $query->with('storage')->withCount('trips');
            },
            'trip_details' => function ($query) {
                $query->withCount('vehicleVariations')->with(['vehicle.storage', 'vehicle.category.storage', 'vehicle.brand.storage']);
            },
            'vehicle_identity.vehicle_identity_data',
            'vehicle_identity.driver',
        ];
    }

    /**
     * Load what the initial query could not, then hand the already-loaded models to the
     * second relation path instead of letting it query for them again.
     */
    private function primeTripDetailsPageRelations(Trips $trip): void
    {
        if (! in_array($trip->trip_status, $this->vehicleAssignmentClosedStatuses)) {
            $trip->trip_details->loadMissing('vehicle.vehicleIdentities');
        }

        $vehicleDetailsByTripDetail = $trip->vehicle_identity->groupBy('trip_details_id');
        $vehicles = $trip->trip_details->pluck('vehicle')->filter()->keyBy('id');

        foreach ($trip->trip_details as $details) {
            $details->setRelation(
                'tripVehicleDetails',
                $vehicleDetailsByTripDetail->get($details->id, new EloquentCollection())
            );
        }

        foreach ($trip->vehicle_identity as $vehicleDetails) {
            if ($vehicles->has($vehicleDetails->vehicle_id)) {
                $vehicleDetails->setRelation('vehicles', $vehicles->get($vehicleDetails->vehicle_id));
            }
        }

        // A row whose vehicle is not among the trip's own details still renders, so it needs
        // the relation loaded rather than left to resolve itself in the view.
        $trip->vehicle_identity
            ->reject(fn ($vehicleDetails) => $vehicleDetails->relationLoaded('vehicles'))
            ->loadMissing('vehicles');
    }
}
