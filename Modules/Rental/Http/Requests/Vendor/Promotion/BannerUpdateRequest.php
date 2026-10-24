<?php

namespace Modules\Rental\Http\Requests\Vendor\Promotion;

use App\Http\Requests\Vendor\Promotion\BannerAddRequest;

class BannerUpdateRequest extends BannerAddRequest
{
    public function rules(): array
    {
        return [
            'image' => $this->imageRule(),
        ];
    }
}
