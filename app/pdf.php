<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$stmt = db()->prepare('SELECT * FROM terrains WHERE id=:id');
$stmt->execute(['id'=>$id ?: 0]);
$land = $stmt->fetch();
if (!$land) {
    http_response_code(404);
    exit('Fiche terrain introuvable.');
}

$baseUrl = rtrim(getenv('APP_URL') ?: '', '/');
if (getenv('APP_ENV') === 'production' && $baseUrl === '') {
    http_response_code(503);
    exit('APP_URL doit être configurée pour générer les QR codes.');
}
if ($baseUrl === '') {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $baseUrl = $https . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\');
}
$propertyUrl = $baseUrl . '/?page=terrain&id=' . (int) $land['id'];
$qrData = (new PngWriter())->write(QrCode::create($propertyUrl)->setSize(180)->setMargin(8))->getDataUri();
$mediaStmt = db()->prepare("SELECT chemin_fichier FROM photos_terrains WHERE terrain_id=:id AND type='photo' ORDER BY id LIMIT 3");
$mediaStmt->execute(['id'=>$land['id']]);
$images = [];
foreach ($mediaStmt->fetchAll() as $item) {
    $path = STORAGE_PATH . '/property/' . $item['chemin_fichier'];
    if (is_file($path)) {
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
        if (in_array($mime, ['image/jpeg', 'image/png'], true)) {
            $images[] = 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($path));
        }
    }
}

$imageHtml = '';
$logoData = logo_data_uri();
$logoHtml = $logoData ? '<img src="' . $logoData . '" style="width:44px;height:44px;object-fit:contain;vertical-align:middle;margin-right:10px" alt="Logo">' : '';
foreach ($images as $image) {
    $imageHtml .= '<img class="property-photo" src="' . $image . '" alt="Photo du terrain">';
}
$html = '<!doctype html><html lang="fr"><meta charset="utf-8"><style>
body{font-family:DejaVu Sans,sans-serif;color:#17313a;font-size:12px;line-height:1.55}header{border-bottom:2px solid #17745b;padding-bottom:15px;margin-bottom:24px}.brand{font-size:20px;font-weight:bold;color:#17313a}.brand span{color:#17745b}small,.muted{color:#687975}.headline{font-size:25px;margin:6px 0}.location{color:#687975}.price{font-size:22px;font-weight:bold;color:#17745b;margin:20px 0}.facts{width:100%;border-collapse:collapse;margin:15px 0}.facts td{border:1px solid #dce3df;padding:11px;width:50%}.label{display:block;color:#687975;font-size:9px;text-transform:uppercase}.property-photo{width:31%;height:150px;object-fit:cover;margin:0 1% 12px 0}.description{white-space:pre-wrap}.legal{background:#f2eee5;padding:12px;border-left:3px solid #bd9560;margin:20px 0}.bottom{border-top:1px solid #dce3df;padding-top:14px;margin-top:28px}.qr{width:94px;height:94px;float:right}.footer{font-size:9px;color:#687975}
</style><body><header><div class="brand">' . $logoHtml . '<span>SG</span> SERVICES INTERNATIONAL SARL</div><small>225, Songon Diapoté, Abidjan · +225 07 11 85 0139</small></header><div class="location">' . e($land['zone_commune']) . ($land['quartier'] ? ' · '.e($land['quartier']) : '') . ' · CÔTE D’IVOIRE</div><div class="headline">' . e($land['titre']) . '</div><div class="muted">Référence ' . e($land['reference'] ?: 'SG-TR-'.str_pad((string)$land['id'],6,'0',STR_PAD_LEFT)) . '</div><div class="price">' . number_format((float)$land['prix'], 0, ',', ' ') . ' FCFA</div><table class="facts"><tr><td><span class="label">Superficie</span><b>' . number_format((float)$land['superficie'], 0, ',', ' ') . ' m²</b></td><td><span class="label">Prix indicatif au m²</span><b>' . number_format((float)$land['prix'] / max((float)$land['superficie'], 1), 0, ',', ' ') . ' FCFA</b></td></tr><tr><td><span class="label">Statut</span>' . e(ucfirst($land['statut'])) . '</td><td><span class="label">Document déclaré</span>' . e($land['statut_juridique']) . '</td></tr><tr><td><span class="label">Coordonnées GPS</span>' . e($land['latitude']) . ', ' . e($land['longitude']) . '</td><td><span class="label">Date de publication</span>' . e(date('d/m/Y', strtotime($land['date_publication']))) . '</td></tr></table>' . $imageHtml . '<h3>Description</h3><div class="description">' . nl2br(e($land['description'])) . '</div><div class="legal"><b>À vérifier avant tout engagement</b><br>Le statut juridique indiqué est déclaratif et ne constitue pas une garantie. Faites vérifier les pièces auprès des autorités et d’un professionnel indépendant avant toute transaction.</div><div class="bottom"><img class="qr" src="' . $qrData . '" alt="QR code"><b>Visitez la fiche en ligne</b><br><span class="muted">Scannez le QR code pour consulter les informations actualisées.</span><p class="footer">Document généré automatiquement par SG Services International SARL · ' . e(date('d/m/Y')) . '</p></div></body></html>';

$featureNames = ['eau'=>'Réseau d’eau','electricite'=>'Électricité','route'=>'Accès route','non_inondable'=>'Zone déclarée non inondable','ecoles'=>'Écoles à proximité','transports'=>'Transports à proximité','assainissement'=>'Assainissement','lotissement_cloture'=>'Lotissement clôturé'];
$landFeatures = json_decode((string)($land['caracteristiques'] ?? '[]'),true);
$featureLabels = [];
foreach (is_array($landFeatures) ? $landFeatures : [] as $feature) {
    if (isset($featureNames[$feature])) { $featureLabels[] = e($featureNames[$feature]); }
}
if ($featureLabels) {
    $html = str_replace('<h3>Description</h3>', '<h3>Caractéristiques de la zone</h3><div>' . implode(' · ', $featureLabels) . '</div><h3>Description</h3>', $html);
}

$options = new Options();
$options->set('isRemoteEnabled', false);
$options->set('defaultFont', 'DejaVu Sans');
$pdf = new Dompdf($options);
$pdf->loadHtml($html, 'UTF-8');
$pdf->setPaper('A4', 'portrait');
$pdf->render();
$pdf->stream('fiche-terrain-' . (int) $land['id'] . '.pdf', ['Attachment'=>true]);