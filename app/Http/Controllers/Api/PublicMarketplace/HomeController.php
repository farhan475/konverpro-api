<?php

namespace App\Http\Controllers\Api\PublicMarketplace;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function campuses()
    {
        return response()->json([]);
    }
}
