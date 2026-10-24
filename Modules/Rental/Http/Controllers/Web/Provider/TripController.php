<?php

namespace Modules\Rental\Http\Controllers\Web\Provider;

use App\Models\Zone;
use Illuminate\Http\Request;
use App\CentralLogics\Helpers;
use Illuminate\Support\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Modules\Rental\Entities\Trips;
use Brian2694\Toastr\Facades\Toastr;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Rental\Entities\Vehicle;
use Illuminate\Http\RedirectResponse;
use Modules\Rental\Exports\TripExport;
use Modules\Rental\Entities\TripDetails;
use Modules\Rental\Traits\TripLogicTrait;
use Illuminate\Contracts\Support\Renderable;
use Modules\Rental\Entities\TripVehicleDetails;
use Modules\Rental\Traits\TripDetailsPageRelations;
use Modules\Rental\Traits\RentalPushNotification;
use MatanYadaev\EloquentSpatial\Objects\Point;

class TripController extends Controller
{

    use TripLogicTrait, RentalPushNotification, TripDetailsPageRelations;
    private Trips $trips;
    private TripDetails $tripDetails;
    private TripVehicleDetails $tripVehicleDetails;

    public function __construct( Trips $trips, TripDetails $tripDetails, TripVehicleDetails $tripVehicleDetails)
    {
        $this->trips = $trips;
        $this->tripDetails = $tripDetails;
        $this->tripVehicleDetails = $tripVehicleDetails;
    }

    /**
     * Display a listing of the resource.
     * @param Request $request
     * @return Renderable
     */
    public function list(Request $request): Renderable
    {
        $key = explode(' ', $request['search'] ?? '');
        $status = $request['status'];

        $this->trips->where(['checked' => 0,'provider_id' => Helpers::get_store_id()])->update(['checked' => 1]);

        $trips = $this->trips->with(['customer.storage', 'proDiscount', 'assignedVehicle', 'assignedDriver.driver.storage', 'trip_details.vehicle.storage', 'trip_details.tripVehicleDetails'])
            ->where(['provider_id' => Helpers::get_store_id()])
            ->when($status == 'scheduled', function ($query) {
                return $query->scheduled();
            })
            ->when($status == 'instant', function ($query) {
                return $query->instant();
            })
            ->when($status == 'pending', function ($query) {
                return $query->Pending();
            })
            ->when($status == 'confirmed', function ($query) {
                return $query->Confirmed();
            })
            ->when($status == 'ongoing', function ($query) {
                return $query->Ongoing();
            })
            ->when($status == 'completed', function ($query) {
                return $query->Completed();
            })
            ->when($status == 'canceled', function ($query) {
                return $query->Canceled();
            })
            ->when($status == 'payment_failed', function ($query) {
                return $query->PaymentFailed();
            })
            ->when(isset($request->from_date) && isset($request->to_date) && $request->from_date != null && $request->to_date != null, function ($query) use ($request) {
                return $query->whereBetween('created_at', [$request->from_date . " 00:00:00", $request->to_date . " 23:59:59"]);
            })
            ->when($request['search'], function ($query) use ($key) {
                return $query->where(function ($q) use ($key) {
                    foreach ($key as $value) {
                        $q->orWhere('id', 'like', "%{$value}%")
                            ->orWhereHas('customer', function ($q) use ($value) {
                                $q->where('f_name', 'like', "%{$value}%")
                                    ->orWhere('l_name', 'like', "%{$value}%")
                                    ->orWhere('email', 'like', "%{$value}%");
                            });
                    }
                });
            })
            ->orderBy('schedule_at', 'desc')
            ->paginate(config('default_pagination'));

        if ($provider = Helpers::get_store_data()) {
            foreach ($trips as $trip) {
                $trip->setRelation('provider', $provider);
            }
        }

        $total = $trips->total();

        return view('rental::provider.trip.list', compact('trips', 'status', 'total'));

    }

    /**
     * Give a trip the Store instance the query was already filtered by.
     *
     * Every method here scopes to provider_id = get_store_id(), so `$trip->provider` is
     * always the authenticated vendor's Store — the one already hydrated by the auth guard.
     * Letting the views lazy-load it re-ran that Store's translate and storage scopes.
     */
    private function shareProvider($trips): void
    {
        $provider = Helpers::get_store_data();

        if (! $provider) {
            return;
        }

        foreach (is_iterable($trips) ? $trips : [$trips] as $trip) {
            $trip?->setRelation('provider', $provider);
        }
    }

    /**
     * Show the form for creating a new resource.
     * @param $id
     * @return Renderable
     */
    public function details($id): Renderable
    {
        $trip = $this->trips
            ->where(['provider_id' => Helpers::get_store_id()])
            ->with($this->tripDetailsPageRelations())
            ->findOrFail($id);
        $this->shareProvider($trip);
        $this->primeTripDetailsPageRelations($trip);

        $is_deleted = 0;

        $trip->trip_details->each(function ($details) use (&$is_deleted) {
            if (!$details->vehicle) {
                $is_deleted = 1;
            }
        });

        return view('rental::provider.trip.details', compact('trip','is_deleted'));
    }

    /**
     * @param $id
     * @param $status
     * @return RedirectResponse
     */

    public function status($id, $status): RedirectResponse
    {
        DB::beginTransaction();

        try {
            $trip = $this->trips
            ->where(['provider_id' => Helpers::get_store_id()])
            ->findOrFail($id);

            if (!$trip) {
                Toastr::success(translate('messages.trip_not_found'));
                return back();
            }

            if ($trip->trip_status != 'pending' && $status == 'pending') {
                $trip->vehicle_identity()->delete();
            }

            $totalVehicle = count($trip->assignedVehicle);
            $is_deleted = 0;
            $trip->trip_details->each(function ($details) use (&$is_deleted) {
                if (!$details->vehicle) {
                    $is_deleted = 1;
                }
            });
            if (in_array($status, ['ongoing', 'completed']) && $totalVehicle <= 0 && $is_deleted !=1) {
                Toastr::error(translate('messages.At first assign a vehicle'));
                return back();
            }


            if ($status == 'canceled') {
                $trip->canceled_by = 'vendor';
                foreach ($trip->trip_details as $detail) {
                    $detail?->vehicle?->total_trip > 0 ? $detail?->vehicle?->decrement('total_trip', $detail->quantity) : '';
                }
                Helpers::increment_order_count($trip->provider);
            }


            $trip->trip_status = $status;
            $trip[$status] = now();
            $trip->save();

            if ($status == 'completed' && $trip->payment_status == 'paid' && !$trip->trip_transaction) {
                if ($this->create_transaction($trip, 'vendor') === false) {
                    DB::rollBack();

                    Toastr::error(translate('Failed to create transaction'));
                    return back();
                }
            }

            if($status == 'completed'){
                TripVehicleDetails::where('trip_id' , $trip->id)->update([
                    'is_completed' => 1
                ]);
            }


            $this->sendTripNotificationCustomer($trip);
            DB::commit();

            Toastr::success(translate('Updated successfully'));
            return back();
        } catch (\Exception $e) {
            DB::rollBack();

            Toastr::error(translate('Something wrong'));
            return back();
        }
    }

    /**
     * @param $id
     * @param $status
     * @return RedirectResponse
     */
    public function paymentStatus($id, $status): RedirectResponse
    {
        DB::beginTransaction();

        try {
            $trip = $this->trips
            ->where(['provider_id' => Helpers::get_store_id()])
            ->findOrFail($id);

            if (!$trip) {
                Toastr::success(translate('messages.trip_not_found'));
                return back();
            }

            if($trip->payment_status == 'paid' && $status == 'paid'){
                Toastr::success(translate('messages.This trip is already paid'));
                return back();
            }

            $trip->payment_method =  $trip->payment_method ?? 'cash_payment';
            $trip->transaction_reference =  $trip?->transaction_reference;
            $trip->payment_status = $status;
            $trip->save();

            if ($trip->trip_status == 'completed' && $trip->payment_status == 'paid' && !$trip->trip_transaction) {
                if ($this->create_transaction($trip, 'vendor') === false) {
                    DB::rollBack();

                    Toastr::error(translate('Failed to create transaction'));
                    return back();
                }
            }
            $this->sendTripPaymentNotificationCustomer($trip);
            DB::commit();

            Toastr::success(translate('Updated successfully'));
            return back();
        } catch (\Exception $e) {
            DB::rollBack();

            Toastr::error(translate('Something wrong'));
            return back();
        }
    }


    /**
     * @param Request $request
     * @return JsonResponse|RedirectResponse
     */
    public function assignVehicle(Request $request): \Illuminate\Http\JsonResponse|RedirectResponse
    {
        $request->validate([
            'trip_id' => 'required',
            'vehicle_identity_ids'=>'required',
            'vehicle_id'=>'required',
            'details_id'=>'required',
        ]);

        $trip = $this->trips
        ->where(['provider_id' => Helpers::get_store_id()])
        ->find($request->trip_id);

        if(!$trip){
            Toastr::success(translate('No data found'));
            return back();
        }

        if(Vehicle::where(['id'=> $request->vehicle_id])->doesntExist()){
            Toastr::success(translate('No data found'));
            return back();
        }

        $vehicle_identity_ids = $request->vehicle_identity_ids;

        foreach($vehicle_identity_ids as  $identity_id){
            $vehicle_data = TripVehicleDetails::where(['trip_id' => $request->trip_id ,'vehicle_id' => $request->vehicle_id ,'vehicle_identity_id' => $identity_id])->firstOrNew();
            $vehicle_data->trip_id = $request->trip_id;
            $vehicle_data->vehicle_id = $request->vehicle_id;
            $vehicle_data->trip_details_id = $request->details_id;
            $vehicle_data->vehicle_identity_id = $identity_id;
            $vehicle_data->estimated_trip_end_time = $trip->estimated_trip_end_time;
            $vehicle_data->save();
        }

        TripVehicleDetails::where(['trip_id'=> $request->trip_id ,'vehicle_id' => $request->vehicle_id])->whereNotIn('vehicle_identity_id', $vehicle_identity_ids)->delete();

        Toastr::success(translate('messages.Trip vehicle assigned successfully'));
        return back();
    }

    /**
     * @param Request $request
     * @return JsonResponse|RedirectResponse
     */
    public function assignDriver(Request $request): RedirectResponse
    {
        $request->validate([
            'trip_id' => 'required',
            'driver_ids'=>'required',
        ]);

        $trip = $this->trips
        ->where(['provider_id' => Helpers::get_store_id()])
        ->find($request->trip_id);
        if(!$trip){
            Toastr::error(translate('No data found'));
            return back();
        }

        $driver_ids = $request->driver_ids;

        foreach($driver_ids as $key => $driver_id){
            // Scoped to this trip's own vehicles, not just any TripVehicleDetails row by id — a
            // key posted from a stale/tampered form must not be able to reassign another trip's
            // (or another provider's) vehicle.
            $vehicle_data = $trip->vehicle_identity()->where('id', $key)->first();

            if(!$vehicle_data){
                // The assign-driver form is a plain HTML POST, not an AJAX submission — a JSON
                // response here rendered as a blank page of raw JSON text instead of the expected
                // toast-and-redirect, which is what made this look like it "did nothing" from the
                // vendor's side.
                Toastr::error(translate('No data found'));
                return back();
            }

            $vehicle_data->vehicle_driver_id = $driver_id;
            $vehicle_data->save();
        }

        Toastr::success(translate('messages.Trip driver assigned successfully'));
        return back();
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function export(Request $request): mixed
    {
        $key = explode(' ', $request['search'] ?? '');
        $status = $request['status'];



        $trips = $this->trips->with(['customer', 'proDiscount'])
        ->where(['provider_id' => Helpers::get_store_id()])
            ->when($status == 'scheduled', function ($query) {
                return $query->scheduled();
            })
            ->when($status == 'pending', function ($query) {
                return $query->Pending();
            })
            ->when($status == 'confirmed', function ($query) {
                return $query->Confirmed();
            })
            ->when($status == 'ongoing', function ($query) {
                return $query->Ongoing();
            })
            ->when($status == 'completed', function ($query) {
                return $query->Completed();
            })
            ->when($status == 'canceled', function ($query) {
                return $query->Canceled();
            })
            ->when($status == 'payment_failed', function ($query) {
                return $query->PaymentFailed();
            })
            ->when(isset($request->vendor), function ($query) use ($request) {
                return $query->whereHas('provider', function ($query) use ($request) {
                    return $query->whereIn('id', $request->vendor);
                });
            })
            ->when(isset($request->from_date) && isset($request->to_date) && $request->from_date != null && $request->to_date != null, function ($query) use ($request) {
                return $query->whereBetween('created_at', [$request->from_date . " 00:00:00", $request->to_date . " 23:59:59"]);
            })
            ->when($request['search'], function ($query) use ($key) {
                return $query->where(function ($q) use ($key) {
                    foreach ($key as $value) {
                        $q->orWhere('id', 'like', "%{$value}%")
                            ->orWhereHas('customer', function ($q) use ($value) {
                                $q->where('f_name', 'like', "%{$value}%")
                                    ->orWhere('l_name', 'like', "%{$value}%")
                                    ->orWhere('email', 'like', "%{$value}%");
                            });
                    }
                });
            })
            ->orderBy('schedule_at', 'desc')->get();
        $this->shareProvider($trips);

        $data = [
            'data' => $trips,
            'search' => $request['search'] ?? null,
        ];

        if ($request['type'] == 'csv') {
            return Excel::download(new TripExport($data), 'Trips.csv');
        }
        return Excel::download(new TripExport($data), 'Trips.xlsx');
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function update(Request $request)
    {
        $request->validate([
            'trip_id' => 'required',
        ]);

        $trip = $this->trips
        ->where(['provider_id' => Helpers::get_store_id()])
        ->find($request->trip_id);

        if (!$trip) {
            return response()->json(['success' => false,
            'message' => translate('No data found')], 400);
        }

        if (in_array($trip->trip_status, ['completed', 'canceled'])) {
            return response()->json(['success' => false,
            'message' => translate('messages.You can not edit this')], 400);
        }

        $pickup = [
            'lat' => $request->pickup_lat ??  $trip->pickup_location['lat']?? null,
            'lng' => $request->pickup_lng ?? $trip->pickup_location['lng']??null,
            'location_name' => $request->pickup_location ?? $trip->pickup_location['location_name']?? null,
        ];

        $zones = [];
        if (data_get($pickup, 'lat')  && data_get($pickup, 'lng')) {
            $zones = Zone::whereContains('coordinates', new Point(data_get($pickup, 'lat'), data_get($pickup, 'lng'), POINT_SRID))->pluck('id')->toArray();
        }

        if (!empty($trip?->provider?->pickup_zone_id)) {
            $pickup_zone_id = is_string($trip->provider->pickup_zone_id)
                ? (array) json_decode($trip->provider->pickup_zone_id, true)
                : (array) $trip->provider->pickup_zone_id;
        } else {
            $pickup_zone_id = [];
        }

        if (count($zones) > 0 &&  count($pickup_zone_id) > 0 &&  empty(array_intersect($pickup_zone_id, $zones)) == true) {
            return response()->json(['success' => false,
            'message' => translate('messages.Pickup location is out of zone')], 400);
        }

        $destination = [
            'lat' => $request->destination_lat ??  $trip->destination_location['lat'] ?? null,
            'lng' => $request->destination_lng ??  $trip->destination_location['lng'] ?? null,
            'location_name' => $request->destination_location ??  $trip->destination_location['location_name'] ?? null,
        ];

        $destinationLocation = $request->destination_location ? json_encode($destination) :json_encode($trip->destination_location);

        $pickupLocation = $request->pickup_location  ? json_encode($pickup)   : json_encode($trip->pickup_location);



        $scheduleAt = $request->schedule_at ? Carbon::parse($request->schedule_at) : Carbon::parse($trip->schedule_at);

        $estimatedHours = $request->estimated_hours ?? $trip->estimated_hours;
        $distance = $trip->distance;

        if($request->distance && is_string($request->distance)){
            $distance = preg_replace('/[^\d]/', '', $request->distance);
        }
        $scheduled = $request->scheduled ?? $trip->scheduled;

        $estimatedTripEndTime = $scheduleAt->copy()->addHours(ceil(
              in_array($trip->rental_type, ['hourly', 'day_wise']) ? $estimatedHours : ($request->destination_time ?? $trip->destination_time)
        ));

        $vehicleQuantities = array_combine($request->vehicle_ids, $request->quantities);
        $modifiedPrices = array_combine($request->vehicle_ids, $request->prices);

        $data = [
            'destinationLocation' => $destinationLocation,
            'pickupLocation' => $pickupLocation,
            'scheduleAt' => $scheduleAt,
            'estimatedHours' => $estimatedHours,
            'distance' => $distance,
            'scheduled' => $scheduled,
            'estimatedTripEndTime' => $estimatedTripEndTime,
            'vehicleQuantities' => $vehicleQuantities,
            'modifiedPrices' => $modifiedPrices,
            'taxPercentage' => $trip?->provider?->tax,
            'quantityUpdate' => $request->quantityUpdate,
            'updateDistance' => $request->update_distance ?? null,

        ];

            $calculationData = $this->getUpdatedTrip($request, $trip, $data, $request->update == 1 ? true : false);
            if ($request->update == 1) {
                return response()->json(['status' => 'updated',  'message' =>translate('Updated successfully'), ], 200);
            } else{
                return response()->json([
                    'success' => true,
                    'status' => 'success',
                    'details' =>$calculationData['details'],
                    'subTotal' => round($calculationData['subTotal'], config('round_up_to_digit')),
                    'grandTotal' => round($calculationData['tripAmount'], config('round_up_to_digit')),
                    'refBonus' => round($calculationData['refBonus'], config('round_up_to_digit')),
                    'discount' => round($calculationData['discount'], config('round_up_to_digit')),
                    'couponDiscount' => round($calculationData['couponDiscount'], config('round_up_to_digit')),
                    'proDiscount' => round($calculationData['proDiscount'] ?? 0, config('round_up_to_digit')),
                    'taxAmount' => round($calculationData['taxAmount'], config('round_up_to_digit')),
                    'taxStatus' => $calculationData['taxStatus'],
                    'additionalCharge' => round($calculationData['additionalCharge'], config('round_up_to_digit')),
                ]);
            }
            return response()->json(['success' => false], 400);
    }


    /**
     * @param Request $request
     * @param $id
     * @return RedirectResponse
     */



    public function generateInvoice($id)
    {
        $trip = $this->trips->where(['provider_id' => Helpers::get_store_id()])->with('proDiscount')->findOrFail($id);
        $this->shareProvider($trip);
        return view('rental::provider.trip.invoice', compact('trip'));
    }
    public function printInvoice($id)
    {
        $trip = $this->trips->where(['provider_id' => Helpers::get_store_id()])->with('proDiscount')->findOrFail($id);
        $this->shareProvider($trip);
        return view('rental::provider.trip.invoice-print', compact('trip'))->render();
    }

}
