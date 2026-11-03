<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\School\DigitalBook;
use App\Services\School\FinancialDashboard;
use Illuminate\Http\Request;

class FinancialDashboardController extends Controller
{
    public function index(Request $r, FinancialDashboard $service)
    {
        return response()->json(['data' => $service->aggregate($r)]);
    }

    public function book(Request $r, DigitalBook $service)
    {
        return response($service->pdf($r), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="eightracer-buku-alumni.pdf"']);
    }
}
