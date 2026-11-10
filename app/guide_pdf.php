<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$points = [
    'Demandez les originaux et vérifiez l’authenticité des documents auprès des services compétents.',
    'Faites confirmer la situation cadastrale, les limites de la parcelle et l’identité du propriétaire.',
    'Visitez le site et renseignez-vous sur les accès, les servitudes et les risques de la zone.',
    'Ne versez aucun acompte sans documents contractuels, reçu et identité clairement établie.',
    'Faites-vous accompagner par un notaire ou un conseil juridique indépendant avant de signer.',
    'Conservez une copie datée des documents, échanges et justificatifs relatifs à la transaction.',
];
$items = '';
$logoData = logo_data_uri();
$logoHtml = $logoData ? '<img src="' . $logoData . '" style="width:36px;height:36px;object-fit:contain;vertical-align:middle;margin-right:8px" alt="Logo">' : '';
foreach ($points as $index => $point) {
    $items .= '<li><b>0' . ($index + 1) . '</b><span>' . e($point) . '</span></li>';
}
$html = '<!doctype html><html lang="fr"><meta charset="utf-8"><style>body{font-family:DejaVu Sans,sans-serif;color:#17313a;padding:25px}header{border-bottom:2px solid #17745b;padding-bottom:15px}.brand{font-size:14px;font-weight:bold;color:#17745b}.eyebrow{font-size:9px;color:#987748;letter-spacing:1px;margin-top:30px}h1{font-size:28px;line-height:1.2;margin:9px 0 12px}p{color:#60716d;font-size:12px;line-height:1.7}ul{list-style:none;padding:0;margin-top:24px}li{display:flex;gap:14px;border-top:1px solid #dce3df;padding:15px 0;font-size:12px;line-height:1.6}b{color:#b9925d}footer{margin-top:30px;padding:13px;background:#f2eee5;font-size:9px;line-height:1.6;color:#596b66}</style><body><header><div class="brand">' . $logoHtml . 'SG SERVICES INTERNATIONAL SARL</div><div>225, Songon Diapoté, Abidjan · +225 07 11 85 0139</div></header><div class="eyebrow">GUIDE PRATIQUE · CÔTE D’IVOIRE</div><h1>Les points à vérifier avant d’acheter un terrain</h1><p>Un achat foncier est un engagement important. Cette liste de repères ne remplace pas l’examen du dossier par un professionnel indépendant.</p><ul>' . $items . '</ul><footer>Ce document est fourni à titre informatif et ne constitue pas un conseil juridique ni une garantie sur un bien. Vérifiez chaque situation auprès des autorités et professionnels compétents.</footer></body></html>';
$options = new Options();
$options->set('isRemoteEnabled', false);
$options->set('defaultFont', 'DejaVu Sans');
$pdf = new Dompdf($options);
$pdf->loadHtml($html, 'UTF-8');
$pdf->setPaper('A4', 'portrait');
$pdf->render();
$pdf->stream('guide-achat-terrain-cote-ivoire.pdf', ['Attachment'=>true]);