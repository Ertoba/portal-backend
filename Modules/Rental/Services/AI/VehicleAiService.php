<?php

namespace Modules\Rental\Services\AI;

use Modules\AI\app\Core\Constants\AIEngineNames;
use Modules\AI\app\Core\Factory\AIEngineFactory;
use Modules\Rental\Entities\VehicleBrand;
use Modules\Rental\Entities\VehicleCategory;

class VehicleAiService
{
    public const TYPES = ['family', 'luxury', 'affordable', 'executives', 'compact', 'full_size'];
    public const FUEL_TYPES = ['octan', 'diesel', 'CNG', 'petrol', 'electric', 'jet_fuel'];
    public const TRANSMISSIONS = ['automatic', 'manual', 'continuously_variable', 'dual_clutch', 'semi_automatic'];

    private VehicleAiPrompts $prompts;

    public function __construct()
    {
        $this->prompts = new VehicleAiPrompts();
    }

    public function titleSuggestions(array $keywords, string $lang): array
    {
        $titles = array_values(array_filter(array_map('trim', (array) ($this->decode($this->engine()->core($this->prompts->titleSuggestions($keywords, $lang)))['titles'] ?? []))));

        return array_slice($titles, 0, 4);
    }

    public function title(string $name, string $lang): ?string
    {
        $title = trim((string) ($this->decode($this->engine()->core($this->prompts->title($name, $lang)))['title'] ?? ''));

        return $title !== '' ? $title : null;
    }

    public function description(string $name, string $lang): ?string
    {
        $description = trim((string) ($this->decode($this->engine()->core($this->prompts->description($name, $lang)))['description'] ?? ''));

        return $description !== '' ? $description : null;
    }

    public function vehicleInfo(string $name, ?string $desc, string $lang): ?array
    {
        $brands = VehicleBrand::ofStatus(1)->pluck('name', 'id');
        $categories = VehicleCategory::ofStatus(1)->pluck('name', 'id');

        $parsed = $this->decode($this->engine()->core($this->prompts->vehicleInfo($name, $desc, $lang, [
            'types' => self::TYPES,
            'fuel_types' => self::FUEL_TYPES,
            'transmissions' => self::TRANSMISSIONS,
            'brands' => $brands->values()->all(),
            'categories' => $categories->values()->all(),
        ])));

        $brandMatch = $this->matchOption($parsed['brand'] ?? null, $brands);
        $categoryMatch = $this->matchOption($parsed['category'] ?? null, $categories);

        $data = [
            'model' => trim((string) ($parsed['model'] ?? '')),
            'type' => $this->allowedOr($parsed['type'] ?? null, self::TYPES),
            'fuel_type' => $this->allowedOr($parsed['fuel_type'] ?? null, self::FUEL_TYPES),
            'transmission_type' => $this->allowedOr($parsed['transmission_type'] ?? null, self::TRANSMISSIONS),
            'seating_capacity' => $this->positiveInt($parsed['seating_capacity'] ?? null),
            'engine_capacity' => $this->positiveInt($parsed['engine_capacity'] ?? null),
            'engine_power' => $this->positiveInt($parsed['engine_power'] ?? null),
            'air_condition' => (isset($parsed['air_condition']) && (int) $parsed['air_condition'] === 1) ? 1 : 0,
            'brand_id' => $brandMatch['id'],
            'brand_name' => $brandMatch['name'],
            'category_id' => $categoryMatch['id'],
            'category_name' => $categoryMatch['name'],
            'tags' => array_values(array_filter(array_map('trim', (array) ($parsed['tags'] ?? [])))),
        ];

        return array_filter($data, fn ($v) => $v !== null && $v !== '') ? $data : null;
    }

    public function pricing(string $name, ?string $desc, string $lang): ?array
    {
        $parsed = $this->decode($this->engine()->core($this->prompts->pricing($name, $desc, $lang)));

        $data = [
            'hourly_price' => $this->positiveFloat($parsed['hourly_price'] ?? null),
            'day_wise_price' => $this->positiveFloat($parsed['day_wise_price'] ?? null),
            'distance_price' => $this->positiveFloat($parsed['distance_price'] ?? null),
            'discount_price' => $this->boundedFloat($parsed['discount_price'] ?? null, 0, 100),
        ];

        return array_filter($data, fn ($v) => $v !== null) ? $data : null;
    }

    public function tags(string $name, ?string $desc, string $lang): array
    {
        $tags = array_values(array_filter(array_map('trim', (array) ($this->decode($this->engine()->core($this->prompts->tags($name, $desc, $lang)))['tags'] ?? []))));

        return array_slice($tags, 0, 8);
    }

    public function seo(string $name, ?string $desc, string $lang): ?array
    {
        $parsed = $this->decode($this->engine()->core($this->prompts->seo($name, $desc, $lang)));

        $data = [
            'meta_title' => trim((string) ($parsed['meta_title'] ?? '')),
            'meta_description' => trim((string) ($parsed['meta_description'] ?? '')),
        ];

        return ($data['meta_title'] !== '' || $data['meta_description'] !== '') ? $data : null;
    }

    public function imageAnalysis(string $dataUri, string $lang): ?array
    {
        $parsed = $this->decode($this->engine()->core($this->prompts->imageAnalysis($lang), $dataUri));
        $name = trim((string) ($parsed['name'] ?? ''));
        $description = trim((string) ($parsed['description'] ?? ''));

        return ($name !== '' || $description !== '') ? ['name' => $name, 'description' => $description] : null;
    }

    private function engine()
    {
        return AIEngineFactory::create(AIEngineNames::getDefault());
    }

    private function decode(?string $raw): array
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $clean = preg_replace('/```(json)?/i', '', $raw);
        $start = strpos($clean, '{');
        $end = strrpos($clean, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $decoded = json_decode(substr($clean, $start, $end - $start + 1), true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    private function allowedOr($value, array $allowed): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        foreach ($allowed as $option) {
            if (strcasecmp($option, $value) === 0) {
                return $option;
            }
        }

        return null;
    }

    private function matchOption($value, $collection): array
    {
        $value = trim((string) $value);
        if ($value !== '') {
            foreach ($collection as $id => $optionName) {
                if (strcasecmp((string) $optionName, $value) === 0) {
                    return ['id' => (int) $id, 'name' => $optionName];
                }
            }
        }

        return ['id' => null, 'name' => null];
    }

    private function positiveInt($value): ?int
    {
        $int = (int) round((float) $value);

        return $int > 0 ? $int : null;
    }

    private function positiveFloat($value): ?float
    {
        $float = round((float) $value, 2);

        return $float > 0 ? $float : null;
    }

    private function boundedFloat($value, float $min, float $max): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        $float = round((float) $value, 2);

        return max($min, min($max, $float));
    }
}
