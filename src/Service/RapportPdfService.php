<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * Service de génération de rapport PDF pour un employé.
 * Utilise Dompdf (dompdf/dompdf) — à installer via composer.
 */
class RapportPdfService
{
    public function __construct(private Environment $twig) {}

    public function generer(array $data): Response
    {
        $html = $this->twig->render('rssi/employes/rapport_pdf.html.twig', $data);

        // Dompdf
        $options = new \Dompdf\Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $employe  = $data['employe'];
        $filename = sprintf(
            'rapport-securite-%s-%s-%s.pdf',
            strtolower($employe->getPrenom()),
            strtolower($employe->getNom()),
            (new \DateTime())->format('Y-m-d')
        );

        return new Response(
            $dompdf->output(),
            200,
            [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]
        );
    }
}