<?php

namespace Modules\Rental\Services\Vehicle;

use App\Services\BaseService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Modules\Rental\Entities\VehicleDriver;
use App\Support\Storage\FileStorage;

class VehicleDriverService extends BaseService
{

    private const IMAGE_DIR = 'driver/';

    private const DEFAULT_IMAGE = 'def.png';

    private const TRIP_COUNTS = [
        'trips',
        'completedTrips as total_trip_completed',
        'canceledTrips as total_trip_canceled',
        'ongoingTrips as total_trip_ongoing',
    ];

    public function getList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        return VehicleDriver::withStorage()
            ->where('provider_id', $filters['provider_id'] ?? null)
            ->withCount(self::TRIP_COUNTS)
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(
                fn ($group) => $this->matchDriver($group, $search)
            ))
            ->latest()
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }

    public function findById(mixed $id, array $options = []): ?VehicleDriver
    {
        return VehicleDriver::when($options['storage'] ?? false, fn ($query) => $query->withStorage())
            ->when($options['provider_id'] ?? null, fn ($query, $providerId) => $query->where('provider_id', $providerId))
            ->withCount($options['counts'] ?? [])
            ->with($options['relations'] ?? [])
            ->find($id);
    }

    public function find(mixed $id, mixed $providerId): ?VehicleDriver
    {
        return $this->findById($id, [
            'storage' => true,
            'provider_id' => $providerId,
            'counts' => self::TRIP_COUNTS,
            'relations' => ['trips.trip'],
        ]);
    }

    public function findForProvider(mixed $id, mixed $providerId): ?VehicleDriver
    {
        return $this->findById($id, ['provider_id' => $providerId]);
    }

    public function create(array $data): VehicleDriver
    {
        return DB::transaction(function () use ($data) {
            $driver = new VehicleDriver();
            $driver->provider_id = $data['provider_id'];
            $this->fill($driver, $data);
            $driver->image = $data['image_file']
                ? FileStorage::upload(self::IMAGE_DIR, $data['image_file'])
                : self::DEFAULT_IMAGE;
            $driver->identity_image = json_encode($this->uploadIdentityImages($data['identity_files'] ?? []));
            $driver->save();

            return $driver;
        });
    }

    public function update(VehicleDriver $driver, array $data): VehicleDriver
    {
        $image = $data['image_file']
            ? FileStorage::update(self::IMAGE_DIR, $driver->image, $data['image_file'])
            : $driver->image;
        $identityImage = $data['identity_files']
            ? json_encode($this->replaceIdentityImages($driver, $data['identity_files']))
            : $driver->identity_image;

        return DB::transaction(function () use ($driver, $data, $image, $identityImage) {
            $this->fill($driver, $data);
            $driver->image = $image;
            $driver->identity_image = $identityImage;
            $driver->save();

            return $driver;
        });
    }

    public function updateStatus(VehicleDriver $driver): VehicleDriver
    {
        $driver->update(['status' => ! $driver->status]);

        return $driver;
    }

    public function delete(VehicleDriver $driver): bool
    {
        $image = $driver->image;

        $deleted = DB::transaction(function () use ($driver) {
            $driver->translations()->delete();

            return (bool) $driver->delete();
        });

        if ($deleted && $image) {
            FileStorage::delete(self::IMAGE_DIR, $image);
        }

        return $deleted;
    }

    private function fill(VehicleDriver $driver, array $data): void
    {
        $driver->first_name = $data['first_name'];
        $driver->last_name = $data['last_name'];
        $driver->email = $data['email'];
        $driver->phone = $data['phone'];
        $driver->identity_type = $data['identity_type'];
        $driver->identity_number = $data['identity_number'];
    }

    private function uploadIdentityImages(array $files): array
    {
        $rows = [];

        foreach (array_filter($files) as $file) {
            $rows[] = ['img' => FileStorage::upload(self::IMAGE_DIR, $file), 'storage' => FileStorage::getDisk()];
        }

        return $rows;
    }

    private function replaceIdentityImages(VehicleDriver $driver, array $files): array
    {
        foreach ($this->identityImageNames($driver) as $name) {
            FileStorage::delete(self::IMAGE_DIR, $name);
        }

        return $this->uploadIdentityImages($files);
    }

    private function identityImageNames(VehicleDriver $driver): array
    {
        $stored = is_array($driver->identity_image)
            ? $driver->identity_image
            : json_decode((string) $driver->identity_image, true);

        return collect($stored ?: [])
            ->map(fn ($row) => is_array($row) ? ($row['img'] ?? null) : $row)
            ->filter()
            ->all();
    }

    private function matchDriver(mixed $group, string $search): void
    {
        foreach (array_filter(explode(' ', $search), fn ($token) => $token !== '') as $token) {
            $group->orWhere('first_name', 'like', "%{$token}%")
                ->orWhere('last_name', 'like', "%{$token}%")
                ->orWhere('email', 'like', "%{$token}%")
                ->orWhere('phone', 'like', "%{$token}%");
        }
    }
}
