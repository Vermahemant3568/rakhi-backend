<?php

namespace App\Services\PDF;

use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class PDFService
{
    // Generate any plan PDF
    public function generate(
        string $view,
        array $data,
        string $filename
    ): string {
        $pdf = Pdf::loadView($view, $data)
                  ->setPaper('a4', 'portrait')
                  ->setOptions([
                      'isHtml5ParserEnabled' => true,
                      'isRemoteEnabled'      => true,
                      'defaultFont'          => 'sans-serif',
                  ]);

        $path = "plans/{$filename}";
        Storage::disk('public')->put($path, $pdf->output());

        return Storage::disk('public')->url($path);
    }

    // Generate diet plan PDF
    public function generateDietPlan(User $user, array $plan): string
    {
        return $this->generate(
            view: 'pdfs.diet-plan',
            data: [
                'user' => $user,
                'plan' => $plan,
                'date' => now()->format('d M Y'),
            ],
            filename: "diet-plan-{$user->id}-" . time() . ".pdf"
        );
    }

    // Generate fitness plan PDF
    public function generateFitnessPlan(User $user, array $plan): string
    {
        return $this->generate(
            view: 'pdfs.fitness-plan',
            data: [
                'user' => $user,
                'plan' => $plan,
                'date' => now()->format('d M Y'),
            ],
            filename: "fitness-plan-{$user->id}-" . time() . ".pdf"
        );
    }

    // Generate consultation report PDF
    public function generateConsultationReport(
        User $user,
        array $report
    ): string {
        return $this->generate(
            view: 'pdfs.consultation-report',
            data: [
                'user'   => $user,
                'report' => $report,
                'date'   => now()->format('d M Y'),
            ],
            filename: "consultation-{$user->id}-" . time() . ".pdf"
        );
    }
}
