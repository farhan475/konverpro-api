<?php

namespace App\Http\Controllers\Api\PublicMarketplace;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class HomeController extends Controller
{
    public function campuses(): JsonResponse
    {
        return response()->json([]);
    }
}
