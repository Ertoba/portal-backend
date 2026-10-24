<?php

namespace Modules\Rental\Http\Controllers\Web\Provider;

use App\Rules\PhoneNumber;
use App\Rules\EmailAddress;
use App\CentralLogics\Helpers;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Maatwebsite\Excel\Facades\Excel;
use Modules\Rental\Entities\TripVehicleDetails;
use Modules\Rental\Entities\VehicleDriver;
use Modules\Rental\Exports\DriverTripExport;
use Modules\Rental\Exports\VehicleDriverExport;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use App\Support\Storage\FileStorage;

class DriverController extends Controller
{
    private VehicleDriver $driver;
    private Helpers $helpers;
    private TripVehicleDetails $tripVehicleDetails;

    public function __construct(VehicleDriver $driver, Helpers $helpers, TripVehicleDetails $tripVehicleDetails)
    {
        $this->driver = $driver;
        $this->helpers = $helpers;
        $this->tripVehicleDetails = $tripVehicleDetails;
    }

    /**
     * Attach the authenticated vendor's already-hydrated Store as a driver's `provider`.
     *
     * Guarded on the id rather than applied blindly: findOrFail() here is not scoped to
     * the current provider, so a driver from another provider must keep its own relation.
     */
    private function shareAuthStoreAsProvider(?VehicleDriver $driver): void
    {
        $store = Helpers::get_store_data();

        if ($driver && $store && (int) $driver->provider_id === (int) $store->id) {
            $driver->setRelation('provider', $store);
        }
    }

    /**
     * Show the form for creating a new resource.
     * @param Request $request
     * @return Renderable
     */
    public function list(Request $request): Renderable
    {
        $query = $this->driver
            ->when($request->has('search'), function ($query) use ($request) {
                $keys = explode(' ', $request['search'] ?? '');
                foreach ($keys as $key) {
                    $query->orWhere('first_name', 'LIKE', '%' . $key . '%')->orWhere('last_name', 'LIKE', '%' . $key . '%');
                }
            })
            ->where('provider_id', $this->helpers->get_store_id())
            ->latest();

            $activeDrivers = (clone $query)->ofStatus(1)->count();
            $inactiveDrivers = (clone $query)->ofStatus(0)->count();
            // list.blade.php shows three trip counts per row and uses them for nothing else.
            // `$driver->trips()->count()` built a fresh COUNT query for every row — one per
            // driver, which is what the N+1 detector flagged — while completedTrips and
            // canceledTrips each loaded a full collection just to be count()ed.
            $drivers = $query->latest()
                ->withCount(['trips', 'completedTrips', 'canceledTrips'])
                ->paginate(config('default_pagination'));
            // paginate() already ran this exact COUNT(*) to build the paginator; the
            // separate $query->count() was the same query a second time.
            $totalDrivers = $drivers->total();
        return view('rental::provider.driver.list', compact('drivers','totalDrivers','activeDrivers','inactiveDrivers'));
    }

    /**
     * Show the form for creating a new resource.
     * @return Renderable
     */
    public function create(): Renderable
    {
        return view('rental::provider.driver.create');
    }

    /**
     * Store a newly created resource in storage.
     * @param Request $request
     * @return RedirectResponse
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => EmailAddress::rules('required', 'vehicle_drivers,email'),
            'phone' => PhoneNumber::rules('required', 'vehicle_drivers,phone'),
            'first_name' => 'required',
            'last_name' => 'required',
            'identity_type' => 'required',
            'identity_number' => 'required',
        ]);

        if ($request->has('image')) {
            $imageName = FileStorage::upload('driver/', $request->file('image'));
        } else {
            $imageName = 'def.png';
        }

        $identityImageNames = [];
        if (!empty($request->file('identity_image'))) {
            foreach ($request->identity_image as $img) {
                $identityImage = FileStorage::upload('driver/', $img);
                $identityImageNames[] = ['img' => $identityImage, 'storage' => $this->helpers->getDisk()];
            }
            $identityImage = json_encode($identityImageNames);
        } else {
            $identityImage = json_encode([]);
        }

        $driver = $this->driver;
        $driver->provider_id = $this->helpers->get_store_id();
        $driver->first_name = $request->first_name;
        $driver->last_name = $request->last_name;
        $driver->email = $request->email;
        $driver->phone = $request->phone;
        $driver->identity_type = $request->identity_type;
        $driver->identity_number = $request->identity_number;
        $driver->image = $imageName;
        $driver->identity_image = $identityImage;
        $driver->save();

        Toastr::success(translate('Added successfully'));
        return back();

    }

    /**
     * Show the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function details($id): Renderable
    {
        // storage backs the driver's image accessors and provider is read directly, both by
        // details.blade.php. provider is eager-loaded rather than left to the sharing helper
        // below: that helper only covers the case where the driver belongs to the signed-in
        // vendor's own store, and a driver of any other store fell straight through it.
        // Everything details.blade.php reaches for: the driver's own images, and on the provider
        // card its logo, name, phone and the average of its vehicle reviews.
        $driver = $this->driver->with(['storage', 'provider.storage', 'provider.vehicle_reviews'])->findOrFail($id);
        // Where it IS the same row, hand over the Store the vendor already has hydrated rather
        // than paying for its translate and storage global scopes a second time.
        $this->shareAuthStoreAsProvider($driver);
        $driverTrips = $this->tripVehicleDetails
            // Everything the trips table reads per row: the trip, its customer, and the vehicle
            // it was assigned. Loaded together rather than one exception at a time -- the guard
            // reports only the first missing relation, so they surface one run at a time.
            // Everything the trips table reaches for per row. Listed in full rather than fixed one
            // exception at a time: the guard reports only the first relation missing, so each run
            // surfaces exactly one and the next is invisible until that one is loaded.
            //
            // tripVehicleDetails is the relation, not a withCount, because the view counts it as
            // a collection -- $detail->tripVehicleDetails->count().
            ->with([
                'trip.customer',
                'trip.assignedVehicle',
                'trip.trip_details.vehicle.storage',
                'trip.trip_details.tripVehicleDetails',
            ])
            ->where('vehicle_driver_id', $id)
            ->when(request('search'), function ($query) {
                return $query->whereHas('trip', function ($query) {
                    return $query->where('id', request('search'));
                });
            })
            ->latest()->paginate(config('default_pagination'));
        return view('rental::provider.driver.details', compact('driver', 'driverTrips'));
    }

    /**
     * Show the form for editing the specified resource.
     * @param int $id
     * @return Renderable
     */
    public function edit($id): Renderable
    {
        $driver = $this->driver->findOrFail($id);
        return view('rental::provider.driver.edit', compact('driver'));
    }

    /**
     * Update the specified resource in storage.
     * @param Request $request
     * @param int $id
     * @return RedirectResponse
     */
    public function update(Request $request, $id): RedirectResponse
    {
        $driver = $this->driver->findOrFail($id);

        $request->validate([
            'email' => EmailAddress::rules('required', 'vehicle_drivers,email,'.$id),
            'phone' => PhoneNumber::rules('required', 'vehicle_drivers,phone,'.$id),
            'first_name' => 'required',
            'last_name' => 'required',
            'identity_type' => 'required',
            'identity_number' => 'required',
        ]);

        if ($request->has('image')) {
            $imageName = FileStorage::update('driver/', $driver->image, $request->file('image'));
        } else {
            $imageName = $driver['image'];
        }

        if ($request->has('identity_image')){
            foreach (json_decode($driver['identity_image'], true) as $img) {

                FileStorage::delete('driver/' , $img);

            }
            $imgKeeper = [];
            foreach ($request->identity_image as $img) {
                $identityImage = FileStorage::upload('driver/', $img);
                $imgKeeper[] = ['img' => $identityImage, 'storage' => FileStorage::getDisk()];
            }
            $identityImage = json_encode($imgKeeper);
        } else {
            $identityImage = $driver['identity_image'];
        }

        $driver->first_name = $request->first_name;
        $driver->last_name = $request->last_name;
        $driver->email = $request->email;
        $driver->phone = $request->phone;
        $driver->identity_type = $request->identity_type;
        $driver->identity_number = $request->identity_number;
        $driver->image = $imageName;
        $driver->identity_image = $identityImage;
        $driver->save();

        Toastr::success(translate('Updated successfully'));
        return back();

    }


    /**
     * @param Request $request
     * @param $id
     * @return RedirectResponse
     */
    public function status(Request $request, $id): RedirectResponse
    {
        $driver = $this->driver->find($id);

        if (!$driver) {
            Toastr::error(translate('messages.driver_not_found'));
            return back();
        }

        $driver->update(['status' => !$driver->status]);

        Toastr::success(translate('Updated successfully'));
        return back();
    }


    /**
     * @param Request $request
     * @param $id
     * @return RedirectResponse
     */
    public function destroy(Request $request, $id): RedirectResponse
    {
        $driver = $this->driver->find($id);

        if (!$driver) {
            Toastr::error(translate('messages.Failed to delete driver'));
            return back();
        }

        if ($driver->image) {
            FileStorage::upload('driver/', $driver->image);
        }

        $driver->translations()->delete();
        $driver->delete();

        Toastr::success(translate('Deleted successfully'));

        if ($request['driver_list']){
            return redirect()->route('vendor.driver.list');
        }
        return back();
    }

    /**
     * @param Request $request
     * @return BinaryFileResponse
     */
    public function export(Request $request): BinaryFileResponse
    {
        $drivers = $this->driver
            ->where('provider_id', $this->helpers->get_store_id())
            ->when($request->has('search'), function ($query) use ($request) {
                $keys = explode(' ', $request['search'] ?? '');
                foreach ($keys as $key) {
                    $query->orWhere('first_name', 'LIKE', '%' . $key . '%');
                    $query->orWhere('last_name', 'LIKE', '%' . $key . '%');
                }
            })
            ->latest()->get();

        // driver-export.blade.php reads $driver->provider->name; every row here belongs to
        // the authenticated vendor's store, so hand it the instance already in memory.
        foreach ($drivers as $driver) {
            $this->shareAuthStoreAsProvider($driver);
        }

        $data = [
            'data' => $drivers,
            'search' => $request['search'] ?? null,
        ];

        if ($request['type'] == 'csv') {
            return Excel::download(new VehicleDriverExport($data), 'Drivers.csv');
        }
        return Excel::download(new VehicleDriverExport($data), 'Drivers.xlsx');
    }

    public function tripExport(Request $request): BinaryFileResponse
    {
        $driverTrips = $this->tripVehicleDetails
            ->where('vehicle_driver_id', $request->id)
            ->when(request('search'), function ($query) {
                return $query->whereHas('trip', function ($query) {
                    return $query->where('id', request('search'));
                });
            })
            ->latest()->get();

        $data = [
            'data' => $driverTrips,
            'search' => $request['search'] ?? null,
        ];

        if ($request['type'] == 'csv') {
            return Excel::download(new DriverTripExport($data), 'DriverTrips.csv');
        }
        return Excel::download(new DriverTripExport($data), 'DriversTrips.xlsx');
    }
}
