<?php

namespace Modules\Rental\Http\Requests\Vendor\Ai;

use App\Http\Requests\BaseRequest;

class ImageAnalysisRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'image' => $this->imageRule('required'),
        ];
    }

    public function imageDataUri(): string
    {
        $file = $this->file('image');

        return 'data:' . ($file->getMimeType() ?: 'image/jpeg')
            . ';base64,' . base64_encode((string) file_get_contents($file->getRealPath()));
    }
}
