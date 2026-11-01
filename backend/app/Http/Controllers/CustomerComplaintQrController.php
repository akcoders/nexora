<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Support\RecordVisibility;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class CustomerComplaintQrController extends Controller
{
    public function show(Request $request, Customer $customer): View
    {
        abort_unless(RecordVisibility::allows($request->user(), $customer, 'customers', false), 403);

        return view('customers.complaint-qr', ['customer' => $customer]);
    }

    public function start(): View
    {
        return view('complaints.public-start');
    }
}
