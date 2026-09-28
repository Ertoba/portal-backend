<?php

namespace Modules\Rental\Services\AI;

class VehicleAiPrompts
{
    public function titleSuggestions(array $keywords, string $langCode = 'en'): string
    {
        $keywordList = implode(', ', array_filter(array_map('trim', $keywords)));

        return "You are naming vehicles for an online car/bike rental marketplace listing.\n"
            . "Based on these keywords: \"{$keywordList}\", suggest exactly 4 distinct, attractive vehicle listing names.\n"
            . "Rules:\n"
            . "- Each name must be between 20 and 60 characters.\n"
            . "- Use a real-sounding brand/model style (e.g. \"Toyota Corolla Altis 2023\").\n"
            . "- Do not add quotes, numbering, emojis or extra commentary.\n"
            . "- Write the names in the language with ISO code \"{$langCode}\".\n"
            . "Return ONLY valid minified JSON in exactly this format, with no markdown fences:\n"
            . '{"titles":["Name 1","Name 2","Name 3","Name 4"]}';
    }

    public function description(string $name, string $langCode = 'en'): string
    {
        return "Write a short, appealing rental listing description for the vehicle named \"{$name}\".\n"
            . "Rules:\n"
            . "- 2 to 3 sentences, 200 to 400 characters total.\n"
            . "- Highlight comfort, performance and why a customer would want to rent it.\n"
            . "- Plain text only: no markdown, no HTML, no bullet points, no quotes around the text.\n"
            . "- Write it in the language with ISO code \"{$langCode}\".\n"
            . "Return ONLY valid minified JSON in exactly this format, with no markdown fences:\n"
            . '{"description":"..."}';
    }

    public function title(string $name, string $langCode = 'en'): string
    {
        return "Improve this rental vehicle listing name into one polished, attractive title: \"{$name}\".\n"
            . "Rules:\n"
            . "- 20 to 60 characters.\n"
            . "- Keep the brand/model if present.\n"
            . "- Plain text, no quotes, no extra commentary.\n"
            . "- Write it in the language with ISO code \"{$langCode}\".\n"
            . "Return ONLY valid minified JSON in exactly this format, with no markdown fences:\n"
            . '{"title":"..."}';
    }

    public function vehicleInfo(string $name, ?string $description, string $langCode, array $allowed): string
    {
        $types = implode(', ', $allowed['types'] ?? []);
        $fuels = implode(', ', $allowed['fuel_types'] ?? []);
        $trans = implode(', ', $allowed['transmissions'] ?? []);
        $brands = implode(', ', $allowed['brands'] ?? []);
        $categories = implode(', ', $allowed['categories'] ?? []);

        return "For the rental vehicle named \"{$name}\"" . ($description ? " (description: \"{$description}\")" : '') . ", infer its specifications.\n"
            . "Rules — every field must respect these constraints:\n"
            . "- \"model\": a plausible model/trim name string.\n"
            . "- \"type\": EXACTLY one of [{$types}].\n"
            . "- \"fuel_type\": EXACTLY one of [{$fuels}].\n"
            . "- \"transmission_type\": EXACTLY one of [{$trans}].\n"
            . "- \"seating_capacity\": an integer between 1 and 60.\n"
            . "- \"engine_capacity\": engine displacement in cc, a positive integer.\n"
            . "- \"engine_power\": engine power in hp, a positive integer.\n"
            . "- \"air_condition\": 1 if it has AC, otherwise 0.\n"
            . "- \"brand\": pick the closest match from [{$brands}] or empty string if none fits.\n"
            . "- \"category\": pick the closest match from [{$categories}] or empty string if none fits.\n"
            . "- \"tags\": array of 3 to 6 short search keywords, in the language \"{$langCode}\".\n"
            . "Return ONLY valid minified JSON in exactly this format, with no markdown fences:\n"
            . '{"model":"...","type":"...","fuel_type":"...","transmission_type":"...","seating_capacity":0,"engine_capacity":0,"engine_power":0,"air_condition":0,"brand":"...","category":"...","tags":["..."]}';
    }

    public function pricing(string $name, ?string $description, string $langCode = 'en'): string
    {
        return "Suggest realistic rental pricing for the vehicle named \"{$name}\"" . ($description ? " (description: \"{$description}\")" : '') . ".\n"
            . "Rules:\n"
            . "- \"hourly_price\": positive number (price per hour).\n"
            . "- \"day_wise_price\": positive number (price per day), typically 6 to 10 times the hourly price.\n"
            . "- \"distance_price\": positive number (price per km).\n"
            . "- \"discount_price\": a number 0 to 15 (interpreted as a percentage).\n"
            . "- All numbers with at most 2 decimals, no currency symbols.\n"
            . "Return ONLY valid minified JSON in exactly this format, with no markdown fences:\n"
            . '{"hourly_price":0,"day_wise_price":0,"distance_price":0,"discount_price":0}';
    }

    public function tags(string $name, ?string $description, string $langCode = 'en'): string
    {
        return "Generate 5 to 8 short search tags for the rental vehicle named \"{$name}\"" . ($description ? " (description: \"{$description}\")" : '') . ".\n"
            . "Rules:\n"
            . "- Each tag is 1 to 3 words, lowercase, no hashtags.\n"
            . "- Useful for customers searching this vehicle.\n"
            . "- Write them in the language with ISO code \"{$langCode}\".\n"
            . "Return ONLY valid minified JSON in exactly this format, with no markdown fences:\n"
            . '{"tags":["tag1","tag2","tag3","tag4","tag5"]}';
    }

    public function seo(string $name, ?string $description, string $langCode = 'en'): string
    {
        return "Write SEO meta content for the rental vehicle listing named \"{$name}\"" . ($description ? " (description: \"{$description}\")" : '') . ".\n"
            . "Rules:\n"
            . "- \"meta_title\": up to 60 characters, compelling and keyword-rich.\n"
            . "- \"meta_description\": up to 160 characters, a concise search-result summary.\n"
            . "- Plain text only, no markdown, no quotes around the values.\n"
            . "- Write both in the language with ISO code \"{$langCode}\".\n"
            . "Return ONLY valid minified JSON in exactly this format, with no markdown fences:\n"
            . '{"meta_title":"...","meta_description":"..."}';
    }

    public function imageAnalysis(string $langCode = 'en'): string
    {
        return "Look at the attached vehicle image and identify it.\n"
            . "Produce a rental listing name and a short description for it.\n"
            . "Rules:\n"
            . "- \"name\": 20 to 60 characters, brand/model style if identifiable, otherwise a descriptive name.\n"
            . "- \"description\": 2 to 3 sentences, 200 to 400 characters, appealing rental copy.\n"
            . "- Plain text only, no markdown or HTML.\n"
            . "- Write both fields in the language with ISO code \"{$langCode}\".\n"
            . "Return ONLY valid minified JSON in exactly this format, with no markdown fences:\n"
            . '{"name":"...","description":"..."}';
    }
}
