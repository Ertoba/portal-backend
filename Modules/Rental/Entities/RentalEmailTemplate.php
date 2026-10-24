<?php

namespace Modules\Rental\Entities;

use App\Traits\Model\HasEmailTemplateAttributes;
use App\Traits\Model\HasStorageTrait;
use App\Traits\Model\HasTranslationsTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RentalEmailTemplate extends Model
{
    use HasEmailTemplateAttributes, HasFactory, HasStorageTrait, HasTranslationsTrait;
}
