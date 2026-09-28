<?php

namespace App\Models;

use App\CentralLogics\Helpers;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use App\Traits\GeneratesSlug;

/**
 * Class Module
 *
 * @property int $id
 * @property string $module_name
 * @property string $module_type
 * @property string|null $thumbnail
 * @property bool $status
 * @property int $stores_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property string|null $icon
 * @property int $theme_id
 * @property string|null $description
 * @property bool $all_zone_service
 */
class Module extends Model
{
    use HasFactory, GeneratesSlug;
    protected $with = ['translations','storage'];
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'module_name',
        'module_type',
        'thumbnail',
        'status',
        'stores_count',
        'icon',
        'theme_id',
        'description',
        'short_description',
        'all_zone_service',
    ];


    /**
     * @var string[]
     */
    protected $casts = [
        'id'=>'integer',
        'stores_count'=>'integer',
        'theme_id'=>'integer',
        'status'=>'string',
        'all_zone_service'=>'integer'
    ];

    protected $appends = ['icon_full_url','thumbnail_full_url'];

    /**
     * @return HasMany
     */
    public function stores(): HasMany
    {
        return $this->hasMany(Store::class);
    }

    /**
     * @return HasMany
     */
    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    /**
     * @return MorphMany
     */
    public function translations(): MorphMany
    {
        return $this->morphMany(Translation::class, 'translationable');
    }

    /**
     * @param $value
     * @return mixed
     */
    public function getModuleNameAttribute($value): mixed
    {
        if (count($this->translations) > 0) {
            foreach ($this->translations as $translation) {
                if ($translation['key'] == 'module_name') {
                    return $translation['value'];
                }
            }
        }

        return $value;
    }

    /**
     * @param $value
     * @return mixed
     */
    public function getDescriptionAttribute($value): mixed
    {
        if (count($this->translations) > 0) {
            foreach ($this->translations as $translation) {
                if ($translation['key'] == 'description') {
                    return $translation['value'];
                }
            }
        }

        return $value;
    }

    public function getShortDescriptionAttribute($value): mixed
    {
        if (count($this->translations) > 0) {
            foreach ($this->translations as $translation) {
                if ($translation['key'] == 'short_description') {
                    return $translation['value'];
                }
            }
        }

        return $value;
    }


    /**
     * @param $query
     * @return mixed
     */
    public function scopeParcel($query): mixed
    {
        return $query->where('module_type', 'parcel');
    }

    /**
     * @param $query
     * @return mixed
     */
    public function scopeNotParcel($query): mixed
    {
        return $query->where('module_type', '!=' ,'parcel');
    }
    public function scopeNotRental($query): mixed
    {
        return $query->where('module_type', '!=' ,'rental');
    }
    public function scopeWithoutAdditionalModules($query): mixed
    {
        return $query->whereNotIn('module_type',  ['rental','ride-share','service']);
    }

    public function scopeNotServiceAndRideShare($query)
    {
        return $query->whereNotIn('module_type', ['service', 'ride-share']);
    }

    /**
     * @param $query
     * @return mixed
     */
    public function scopeActive($query): mixed
    {
        return $query->where('status', '=', 1);
    }

    /**
     * Back-compat shim. Earlier versions attached top_offer_value /
     * top_offer_type as scalar subqueries via selectSub, but the SQL
     * lateral-referenced `modules.id` from inside a derived table — a
     * pattern that only works on MySQL 8.0.14+ with implicit lateral and
     * fails on MariaDB and older MySQL ("Unknown column 'modules.id' in
     * WHERE"). The replacement is `Module::attachTopOffers($collection,
     * $zoneIds)` which is portable. This scope is now a no-op so existing
     * callers don't break; call attachTopOffers() after ->get().
     */
    public function scopeWithTopOffer($query, array $zoneIds = []): mixed
    {
        return $query;
    }

    /**
     * Populate `top_offer_value` and `top_offer_type` on each module in
     * the given collection, using a single UNION ALL query that never
     * cross-references the outer modules row. Works on every MySQL ≥ 5.7
     * and MariaDB ≥ 10.x.
     *
     * Result attribute semantics match the old scope: the highest discount
     * across (a) item-level discounts on active items in active stores,
     * (b) currently-active store-wide discounts, and (c) live flash sale
     * items — restricted to the supplied zones.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, self>  $modules
     * @param  int[]  $zoneIds
     */
    public static function attachTopOffers($modules, array $zoneIds = []): void
    {
        if ($modules->isEmpty()) {
            return;
        }

        $moduleIds = $modules->pluck('id')->filter()->map(fn ($id) => (int) $id)->all();
        if (empty($moduleIds)) {
            return;
        }

        $zonesCsv   = empty($zoneIds)
            ? null
            : implode(',', array_map('intval', $zoneIds));
        $modulesCsv = implode(',', $moduleIds);

        // Build the three legs separately so we can apply zone/module
        // filters cleanly without lateral correlation. Each leg returns
        // (module_id, discount, discount_type); the union is the candidate
        // pool of all live discount offers across the requested modules.
        $itemLeg = 'SELECT items.module_id AS module_id, items.discount AS discount, items.discount_type AS discount_type '
            .'FROM items '
            .'JOIN stores ON stores.id = items.store_id '
            .'WHERE items.status = 1 AND items.is_approved = 1 AND items.discount > 0 '
            .'AND stores.status = 1 '
            .'AND items.module_id IN ('.$modulesCsv.')'
            .($zonesCsv ? ' AND stores.zone_id IN ('.$zonesCsv.')' : '');

        $storeDiscountLeg = 'SELECT stores.module_id AS module_id, discounts.discount AS discount, '
            ."COALESCE(discounts.discount_type, 'percent') AS discount_type "
            .'FROM discounts '
            .'JOIN stores ON stores.id = discounts.store_id '
            .'WHERE stores.status = 1 '
            .'AND discounts.start_date <= CURDATE() AND discounts.end_date >= CURDATE() '
            .'AND discounts.start_time <= CURTIME() AND discounts.end_time >= CURTIME() '
            .'AND stores.module_id IN ('.$modulesCsv.')'
            .($zonesCsv ? ' AND stores.zone_id IN ('.$zonesCsv.')' : '');

        $flashLeg = 'SELECT items.module_id AS module_id, flash_sale_items.discount AS discount, flash_sale_items.discount_type AS discount_type '
            .'FROM flash_sale_items '
            .'JOIN flash_sales ON flash_sales.id = flash_sale_items.flash_sale_id '
            .'JOIN items ON items.id = flash_sale_items.item_id '
            .'JOIN stores ON stores.id = items.store_id '
            .'WHERE flash_sales.is_publish = 1 '
            .'AND flash_sales.start_date <= CURDATE() AND flash_sales.end_date >= CURDATE() '
            .'AND items.status = 1 AND items.is_approved = 1 AND stores.status = 1 '
            .'AND items.module_id IN ('.$modulesCsv.')'
            .($zonesCsv ? ' AND stores.zone_id IN ('.$zonesCsv.')' : '');

        $sql = $itemLeg.' UNION ALL '.$storeDiscountLeg.' UNION ALL '.$flashLeg;

        $rows = DB::select($sql);

        // Pick the max-discount row per module in PHP. Avoids window
        // functions (MySQL 8.0+ / MariaDB 10.2+) so this works everywhere.
        $byModule = [];
        foreach ($rows as $r) {
            $mid = (int) $r->module_id;
            $val = (float) $r->discount;
            if (! isset($byModule[$mid]) || $val > (float) $byModule[$mid]->discount) {
                $byModule[$mid] = $r;
            }
        }

        foreach ($modules as $module) {
            $entry = $byModule[(int) $module->id] ?? null;
            $module->top_offer_value = $entry?->discount;
            $module->top_offer_type  = $entry?->discount_type;
        }
    }

    public function getIconFullUrlAttribute(){
        $value = $this->icon;
        if (count($this->storage) > 0) {
            foreach ($this->storage as $storage) {
                if ($storage['key'] == 'icon') {
                    return Helpers::get_full_url('module',$value,$storage['value']);
                }
            }
        }

        return Helpers::get_full_url('module',$value,'public');
    }
    public function getThumbnailFullUrlAttribute(){
        $value = $this->thumbnail;
        if (count($this->storage) > 0) {
            foreach ($this->storage as $storage) {
                if ($storage['key'] == 'thumbnail') {
                    return Helpers::get_full_url('module',$value,$storage['value']);
                }
            }
        }

        return Helpers::get_full_url('module',$value,'public');
    }

    public function storage()
    {
        return $this->morphMany(Storage::class, 'data');
    }

    protected static function booted()
    {
        static::addGlobalScope('storage', function ($builder) {
            $builder->with('storage');
        });
        static::addGlobalScope('translate', function (Builder $builder) {
            $builder->with(['translations' => function($query){
                return $query->where('locale', app()->getLocale());
            }]);
        });
    }

    /**
     * @return BelongsToMany
     */
    public function zones(): BelongsToMany
    {
        return $this->belongsToMany(Zone::class)
            ->withPivot([
                'per_km_shipping_charge',
                'minimum_shipping_charge',
                'maximum_shipping_charge',
                'maximum_cod_order_amount',
                'delivery_charge_type',
                'fixed_shipping_charge',
                'additional_delivery_option_status',
                'minimum_delivery_time',
                'minimum_delivery_charge',
            ])
            ->using(ModuleZone::class);
    }

    public function zoneDeliveryOptions(): HasMany
    {
        return $this->hasMany(ModuleZoneDeliveryOption::class);
    }

    protected static function boot()
    {
        parent::boot();
        static::created(function ($item) {
            $item->slug = $item->generateSlug($item->module_name);
            $item->save();
        });
        static::saved(function ($model) {
            if($model->isDirty('icon')){
                $value = Helpers::getDisk();

                DB::table('storages')->updateOrInsert([
                    'data_type' => get_class($model),
                    'data_id' => $model->id,
                    'key' => 'icon',
                ], [
                    'value' => $value,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            if($model->isDirty('thumbnail')){
                $value = Helpers::getDisk();

                DB::table('storages')->updateOrInsert([
                    'data_type' => get_class($model),
                    'data_id' => $model->id,
                    'key' => 'thumbnail',
                ], [
                    'value' => $value,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

    }

    public static function regenerateSlugs($force = false)
    {
        static::chunkById(100, function ($modules) use ($force) {
            foreach ($modules as $module) {
                // Skip if slug already exists (unless forced)
                if (!$force && !empty($module->slug)) {
                    continue;
                }

                $module->slug = $module->generateSlug($module->module_name);
                $module->save();
            }
        });
    }
}
