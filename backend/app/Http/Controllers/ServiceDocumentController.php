<?php

namespace App\Http\Controllers;

use App\Models\ServiceJob;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ServiceDocumentController extends Controller
{
    public function report(ServiceJob $serviceJob): Response
    {
        Gate::authorize('view', $serviceJob);
        $this->loadDocumentRelations($serviceJob);

        return Pdf::loadView('service-jobs.documents.report', $this->viewData($serviceJob))
            ->setPaper('a4')
            ->download($serviceJob->job_no.'-service-report.pdf');
    }

    public function proforma(ServiceJob $serviceJob): Response
    {
        Gate::authorize('view', $serviceJob);
        $this->loadDocumentRelations($serviceJob);

        return Pdf::loadView('service-jobs.documents.proforma', $this->viewData($serviceJob))
            ->setPaper('a4')
            ->download($serviceJob->job_no.'-proforma-invoice.pdf');
    }

    /**
     * @return array<string, mixed>
     */
    private function viewData(ServiceJob $serviceJob): array
    {
        $paths = $serviceJob->photos->pluck('path')
            ->merge($serviceJob->signatures->pluck('path'))
            ->filter()
            ->unique();
        $images = $paths->mapWithKeys(fn (string $path): array => [$path => $this->imageDataUri($path)])->all();

        return [
            'serviceJob' => $serviceJob,
            'company' => Setting::query()->whereIn('key', ['company_name', 'company_email', 'company_phone'])->pluck('value', 'key'),
            'images' => $images,
            'paidAmount' => (float) $serviceJob->payments->where('status', 'received')->sum('amount'),
        ];
    }

    private function loadDocumentRelations(ServiceJob $serviceJob): void
    {
        $serviceJob->load([
            'customer', 'branch', 'equipment', 'serviceType', 'technician',
            'inspections.items.condition', 'estimates.items', 'performedServices',
            'materials', 'photos', 'signatures', 'payments',
        ]);
    }

    private function imageDataUri(string $path): ?string
    {
        $absolutePath = storage_path('app/public/'.$path);
        if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
            return null;
        }

        $mimeType = mime_content_type($absolutePath) ?: 'image/jpeg';
        $contents = file_get_contents($absolutePath);

        return $contents === false ? null : 'data:'.$mimeType.';base64,'.base64_encode($contents);
    }
}
