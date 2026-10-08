<?php

namespace App\Http\Controllers;

use App\Models\Premises;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PremisesController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatePremises($request);
        $technicians = $data['technicians'] ?? [];
        unset($data['technicians']);
        $premises = Premises::create($data);
        $premises->users()->sync($technicians);

        return back()->with('success', 'Premises created and assigned.');
    }

    public function update(Request $request, Premises $premises): RedirectResponse
    {
        $data = $this->validatePremises($request);
        $technicians = $data['technicians'] ?? [];
        unset($data['technicians']);
        $premises->update($data);
        $premises->users()->sync($technicians);

        return back()->with('success', 'Premises location updated.');
    }

    private function validatePremises(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'radius_meters' => ['required', 'integer', 'min:5', 'max:5000'],
            'shift_start' => ['nullable', 'date_format:H:i'],
            'shift_end' => ['nullable', 'date_format:H:i'],
            'active' => ['nullable', 'boolean'],
            'technicians' => ['nullable', 'array'],
            'technicians.*' => ['exists:users,id'],
        ]);
    }
}
