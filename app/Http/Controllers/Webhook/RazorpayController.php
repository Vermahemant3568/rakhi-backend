<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class RazorpayController extends Controller
{
    public function handle(Request $request)
    {
        // TODO: implement Razorpay webhook handling
        return response()->json(['success' => true]);
    }
}
