<?php

namespace App\Services\Reports;

use App\Models\Report;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class ReportGenerator
{
    public function generate($user, string $type, array $data): Report
    {
        $view = "reports.$type";
        $fileName = "reports/{$type}_{$user->id}_" . time() . ".pdf";

        $pdf = Pdf::loadView($view, $data);

        Storage::put($fileName, $pdf->output());

        return Report::create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => ucfirst($type) . ' Report',
            'file_path' => $fileName
        ]);
    }
}