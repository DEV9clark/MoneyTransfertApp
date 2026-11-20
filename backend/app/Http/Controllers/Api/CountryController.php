<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Country;

class CountryController extends Controller
{
    public function index()
    {
        return response()->json(Country::orderBy('name')->get(['id', 'name', 'code', 'currency', 'phone_code']));
    }
}
