<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\PayrollEntry;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class SalarySlipController extends Controller
{
    public function __invoke(PayrollEntry $payrollEntry): Response
    {
        $payrollEntry->load(['payrollRun', 'user.employeeProfile']);
        $company = Company::query()
            ->where('company_type', 'head_office')
            ->where('status', 'active')
            ->first();

        return Pdf::loadView('payroll.salary-slip', [
            'entry' => $payrollEntry,
            'company' => $company,
            'logo' => $this->logoDataUri($company?->logo_path),
        ])->setPaper('a4')->download('salary-slip-'.$payrollEntry->payrollRun->payroll_month.'-'.$payrollEntry->user->employee_code.'.pdf');
    }

    private function logoDataUri(?string $path): ?string
    {
        $absolutePath = match (true) {
            is_string($path) && str_starts_with($path, 'images/') => public_path($path),
            filled($path) => storage_path('app/public/'.$path),
            default => public_path('images/brand/classic-logo.jpeg'),
        };

        if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
            return null;
        }

        $contents = file_get_contents($absolutePath);

        return $contents === false ? null : 'data:'.(mime_content_type($absolutePath) ?: 'image/jpeg').';base64,'.base64_encode($contents);
    }
}
