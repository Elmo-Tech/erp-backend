<?php

namespace App\Modules\Lead\Actions;

use App\Enums\ProposalTemplate;
use App\Modules\Lead\Models\Lead;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class GenerateLeadProposalPdfAction
{
    /**
     * @return array{file_path: string, file_name: string, file_mime_type: string, file_size: int}
     */
    public function execute(Lead $lead, ProposalTemplate $template, string $validityDate, ?string $notes): array
    {
        $pdfBytes = $this->renderPdf($lead, $template, $validityDate, $notes);
        $fileName = $this->fileName($lead);
        $path = "leads/{$lead->id}/sales-documents/proposal/".Str::uuid().'.pdf';

        if (! Storage::disk('local')->put($path, $pdfBytes)) {
            throw new RuntimeException('Unable to store the generated proposal.');
        }

        return [
            'file_path' => $path,
            'file_name' => $fileName,
            'file_mime_type' => 'application/pdf',
            'file_size' => strlen($pdfBytes),
        ];
    }

    private function renderPdf(Lead $lead, ProposalTemplate $template, string $validityDate, ?string $notes): string
    {
        $options = new Options;
        $options->setDefaultFont('DejaVu Sans');
        $options->setIsRemoteEnabled(false);

        $renderer = new Dompdf($options);
        $renderer->loadHtml(view('proposals.generated', [
            'lead' => $lead,
            'template' => $template,
            'validUntil' => Carbon::parse($validityDate),
            'notes' => $notes,
        ])->render(), 'UTF-8');
        $renderer->setPaper('a4');
        $renderer->render();

        return $renderer->output();
    }

    private function fileName(Lead $lead): string
    {
        $leadSlug = Str::slug($lead->title) ?: "lead-{$lead->id}";

        return "{$leadSlug}-proposal.pdf";
    }
}
