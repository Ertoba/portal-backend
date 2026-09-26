<?php

namespace App\Http\Controllers\Admin;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AddonActivationController extends Controller
{
    private const FEATURES = [
        'vendor_app' => 'addon_activation_vendor_app',
        'deliveryman_app' => 'addon_activation_delivery_man_app',
        'react_web' => 'addon_activation_react',
    ];

    public function index()
    {
        return view('admin-views.addon-activation.index');
    }
}
