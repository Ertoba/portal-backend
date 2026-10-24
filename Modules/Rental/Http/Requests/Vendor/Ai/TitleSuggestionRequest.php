<?php

namespace Modules\Rental\Http\Requests\Vendor\Ai;

use App\Http\Requests\BaseRequest;

class TitleSuggestionRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'keywords' => 'required|string|max:255',
        ];
    }

    public function keywords(): array
    {
        return array_map('trim', explode(',', (string) $this->input('keywords')));
    }
}
