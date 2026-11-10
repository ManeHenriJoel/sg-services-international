<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
date_default_timezone_set('Africa/Abidjan');
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
if (getenv('APP_ENV') === 'production' && !$isHttps) {
    http_response_code(400);
    exit('HTTPS est obligatoire.');
}
if (getenv('APP_ENV') === 'production') {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params(['lifetime'=>0, 'path'=>'/', 'httponly'=>true, 'secure'=>$isHttps, 'samesite'=>'Strict']);
    session_start();
}
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
if ($isHttps) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}
header("Content-Security-Policy: default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; object-src 'none'; script-src 'self' https://unpkg.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://unpkg.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https://images.unsplash.com https://*.tile.openstreetmap.org; media-src 'self'; connect-src 'self'");

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function site_setting(string $key): ?string
{
    static $settings = [];
    if (!array_key_exists($key, $settings)) {
        $stmt = db()->prepare('SELECT valeur FROM parametres WHERE cle=:cle LIMIT 1');
        $stmt->execute(['cle'=>$key]);
        $value = $stmt->fetchColumn();
        $settings[$key] = is_string($value) ? $value : null;
    }
    return $settings[$key];
}

function star_rating(float $rating, int $count = 0): string
{
    $rounded = max(0, min(5, (int) round($rating)));
    return '<span class="star-rating" role="img" aria-label="Note ' . number_format($rating, 1, ',', '') . ' sur 5, ' . $count . ' avis"><span class="stars" aria-hidden="true">' . str_repeat('★', $rounded) . str_repeat('☆', 5 - $rounded) . '</span><span class="rating-number">' . number_format($rating, 1, ',', '') . '</span><span class="rating-count">(' . $count . ')</span></span>';
}

function sanitize_svg(string $contents): string
{
    if (strlen($contents) > 512 * 1024 || preg_match('/<!DOCTYPE|<!ENTITY/i', $contents)) {
        throw new RuntimeException('Le fichier SVG est trop volumineux ou contient des déclarations interdites.');
    }
    $previous = libxml_use_internal_errors(true);
    $document = new DOMDocument();
    $document->resolveExternals = false;
    $document->substituteEntities = false;
    $loaded = $document->loadXML($contents, LIBXML_NONET | LIBXML_NOBLANKS | LIBXML_COMPACT);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    $root = $document->documentElement;
    if (!$loaded || !$root || strtolower($root->localName) !== 'svg' || ($root->namespaceURI && $root->namespaceURI !== 'http://www.w3.org/2000/svg')) {
        throw new RuntimeException('Le SVG est invalide.');
    }
    $allowedElements = ['svg','g','path','rect','circle','ellipse','line','polyline','polygon'];
    $allowedAttributes = ['xmlns','viewbox','width','height','fill','stroke','stroke-width','stroke-linecap','stroke-linejoin','fill-rule','opacity','x','y','x1','y1','x2','y2','cx','cy','r','rx','ry','d','points','transform'];
    $clean = static function (DOMElement $element) use (&$clean, $allowedElements, $allowedAttributes): void {
        for ($index = $element->childNodes->length - 1; $index >= 0; $index--) {
            $child = $element->childNodes->item($index);
            if ($child instanceof DOMElement) {
                if (!in_array(strtolower($child->localName), $allowedElements, true) || ($child->namespaceURI && $child->namespaceURI !== 'http://www.w3.org/2000/svg')) {
                    $element->removeChild($child);
                    continue;
                }
                $clean($child);
            } elseif (!($child instanceof DOMText) || trim($child->textContent) !== '') {
                $element->removeChild($child);
            }
        }
        for ($index = $element->attributes->length - 1; $index >= 0; $index--) {
            $attribute = $element->attributes->item($index);
            $name = strtolower($attribute->localName);
            $value = trim($attribute->value);
            if (!in_array($name, $allowedAttributes, true) || preg_match('/url\s*\(|javascript:|data:|https?:|@import/i', $value)) {
                $element->removeAttributeNode($attribute);
                continue;
            }
            if (in_array($name, ['fill','stroke'], true) && !preg_match('/\A(?:none|currentColor|#[a-f0-9]{3,8}|[a-z]{3,20})\z/i', $value)) {
                $element->removeAttributeNode($attribute);
            }
        }
    };
    $clean($root);
    if (!$root->hasAttribute('xmlns')) {
        $root->setAttribute('xmlns', 'http://www.w3.org/2000/svg');
    }
    return (string) $document->saveXML($root);
}

function save_logo_upload(array $file): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '')) {
        throw new RuntimeException('Sélectionnez un logo valide.');
    }
    $temporaryPath = (string) $file['tmp_name'];
    $size = (int) filesize($temporaryPath);
    $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($temporaryPath);
    $mimeByExtension = ['png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','svg'=>'image/svg+xml'];
    if ($size < 1 || $size > 2 * 1024 * 1024 || !isset($mimeByExtension[$extension])) {
        throw new RuntimeException('Formats acceptés : PNG, JPG ou SVG, 2 Mo maximum.');
    }
    if ($extension === 'svg') {
        if (!in_array($mime, ['image/svg+xml','image/svg','application/xml','text/xml'], true)) {
            throw new RuntimeException('Le contenu du SVG est invalide.');
        }
        $contents = sanitize_svg((string) file_get_contents($temporaryPath));
    } else {
        $image = getimagesize($temporaryPath);
        if ($mime !== $mimeByExtension[$extension] || !$image || $image['mime'] !== $mime || $image[0] > 5000 || $image[1] > 5000) {
            throw new RuntimeException('Le contenu de l’image ne correspond pas à son extension.');
        }
        $contents = null;
    }
    $directory = STORAGE_PATH . '/branding';
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Le stockage du logo est indisponible.');
    }
    $filename = bin2hex(random_bytes(16)) . '.' . ($extension === 'jpeg' ? 'jpg' : $extension);
    $destination = $directory . '/' . $filename;
    $saved = $contents === null ? move_uploaded_file($temporaryPath, $destination) : file_put_contents($destination, $contents, LOCK_EX) !== false;
    if (!$saved) {
        throw new RuntimeException('Impossible d’enregistrer le logo.');
    }
    chmod($destination, 0600);
    return $filename;
}

function save_home_image(array $file, string $bucket): string
{
    if (!in_array($bucket, ['hero','projects'], true) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '')) {
        throw new RuntimeException('Sélectionnez une image JPG ou PNG valide.');
    }
    $temporaryPath = (string) $file['tmp_name'];
    $size = (int) filesize($temporaryPath);
    $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($temporaryPath);
    $valid = ['jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png'];
    $image = getimagesize($temporaryPath);
    if ($size < 1 || $size > 8 * 1024 * 1024 || !isset($valid[$extension]) || $mime !== $valid[$extension] || !$image || $image['mime'] !== $mime || $image[0] > 6000 || $image[1] > 6000) {
        throw new RuntimeException('Formats acceptés : JPG et PNG, 8 Mo maximum par image et 6000 px de côté.');
    }
    $directory = STORAGE_PATH . '/' . $bucket;
    if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Le stockage des images est indisponible.');
    }
    $filename = bin2hex(random_bytes(16)) . '.' . ($extension === 'jpeg' ? 'jpg' : $extension);
    if (!move_uploaded_file($temporaryPath, $directory . '/' . $filename)) {
        throw new RuntimeException('Impossible d’enregistrer l’image.');
    }
    chmod($directory . '/' . $filename, 0600);
    return $filename;
}

function logo_data_uri(): ?string
{
    $filename = site_setting('logo_path');
    if (!$filename || !preg_match('/\A[a-f0-9]{32}\.(png|jpg|svg)\z/', $filename)) {
        return null;
    }
    $path = STORAGE_PATH . '/branding/' . $filename;
    if (!is_file($path)) {
        return null;
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
    if (!in_array($mime, ['image/png','image/jpeg','image/svg+xml'], true)) {
        return null;
    }
    return 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($path));
}

function serve_logo(): never
{
    $filename = site_setting('logo_path');
    if (!$filename || !preg_match('/\A[a-f0-9]{32}\.(png|jpg|svg)\z/', $filename)) {
        http_response_code(404);
        exit;
    }
    $path = STORAGE_PATH . '/branding/' . $filename;
    if (!is_file($path)) {
        http_response_code(404);
        exit;
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
    if (!in_array($mime, ['image/png','image/jpeg','image/svg+xml'], true)) {
        http_response_code(415);
        exit;
    }
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . (string) filesize($path));
    header('Cache-Control: public, max-age=3600');
    header('X-Content-Type-Options: nosniff');
    if ($mime === 'image/svg+xml') {
        header("Content-Security-Policy: default-src 'none'; sandbox");
    }
    readfile($path);
    exit;
}

function serve_home_visual(): never
{
    $kind = (string) ($_GET['kind'] ?? '');
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$id || !in_array($kind, ['hero','project'], true)) {
        http_response_code(404);
        exit;
    }
    $allowInactive = isset($_SESSION['user']['role']) && in_array($_SESSION['user']['role'], ['admin','super_admin'], true);
    if ($kind === 'hero') {
        $stmt = db()->prepare('SELECT chemin_fichier FROM images_hero WHERE id=:id AND (actif=1 OR :allow_inactive=1)');
        $bucket = 'hero';
    } else {
        $stmt = db()->prepare('SELECT chemin_fichier FROM realisations WHERE id=:id AND (actif=1 OR :allow_inactive=1)');
        $bucket = 'projects';
    }
    $stmt->execute(['id'=>$id,'allow_inactive'=>$allowInactive ? 1 : 0]);
    $filename = $stmt->fetchColumn();
    if (!is_string($filename) || !preg_match('/\A[a-f0-9]{32}\.(jpg|png)\z/', $filename)) {
        http_response_code(404);
        exit;
    }
    $path = STORAGE_PATH . '/' . $bucket . '/' . $filename;
    if (!is_file($path)) {
        http_response_code(404);
        exit;
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
    if (!in_array($mime, ['image/jpeg','image/png'], true)) {
        http_response_code(415);
        exit;
    }
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . (string) filesize($path));
    header('Cache-Control: public, max-age=3600');
    header('X-Content-Type-Options: nosniff');
    readfile($path);
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $submitted = (string) ($_POST['csrf_token'] ?? '');
    if ($submitted === '' || !hash_equals((string) ($_SESSION['csrf'] ?? ''), $submitted)) {
        http_response_code(419);
        exit('Votre session a expiré. Rechargez la page et réessayez.');
    }
}

function require_admin(): void
{
    if (!isset($_SESSION['user']['id']) || !in_array($_SESSION['user']['role'] ?? '', ['admin', 'super_admin'], true)) {
        redirect('?page=login');
    }
}

function redirect(string $location): never
{
    header('Location: ' . $location, true, 303);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type'=>$type, 'message'=>$message];
}

function get_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return is_array($flash) ? $flash : null;
}

function save_uploaded_files(array $input, string $bucket): array
{
    if (!isset($input['name']) || !is_array($input['name'])) {
        return [];
    }
    $allowed = ['image/jpeg'=>'jpg', 'image/png'=>'png', 'video/mp4'=>'mp4', 'application/pdf'=>'pdf'];
    $maxEach = 8 * 1024 * 1024;
    if (!is_dir(STORAGE_PATH . '/' . $bucket) && !mkdir(STORAGE_PATH . '/' . $bucket, 0700, true) && !is_dir(STORAGE_PATH . '/' . $bucket)) {
        throw new RuntimeException('Le stockage des fichiers est indisponible.');
    }
    $saved = [];
    $total = 0;
    if (count($input['name']) > 10) {
        throw new RuntimeException('Dix fichiers maximum par soumission.');
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    try {
        foreach ($input['name'] as $index => $originalName) {
            $error = $input['error'][$index] ?? UPLOAD_ERR_NO_FILE;
            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($error !== UPLOAD_ERR_OK || !is_uploaded_file($input['tmp_name'][$index] ?? '')) {
                throw new RuntimeException('Un fichier n’a pas pu être téléversé.');
            }
            $size = (int) filesize((string) $input['tmp_name'][$index]);
            $total += $size;
            $tmp = (string) $input['tmp_name'][$index];
            $mime = $finfo->file($tmp);
            $extension = strtolower(pathinfo((string) $originalName, PATHINFO_EXTENSION));
            if ($size < 1 || $size > $maxEach || $total > 24 * 1024 * 1024 || !isset($allowed[$mime]) || $allowed[$mime] !== $extension) {
                throw new RuntimeException('Formats acceptés : JPG, PNG, MP4 ou PDF, 8 Mo maximum par fichier et 24 Mo au total.');
            }
            if ($bucket === 'property' && !in_array($mime, ['image/jpeg', 'image/png'], true)) {
                throw new RuntimeException('Les photos de terrain doivent être au format JPG ou PNG.');
            }
            $filename = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
            if (!move_uploaded_file($tmp, STORAGE_PATH . '/' . $bucket . '/' . $filename)) {
                throw new RuntimeException('Impossible d’enregistrer un fichier.');
            }
            $saved[] = $filename;
        }
    } catch (Throwable $exception) {
        foreach ($saved as $filename) {
            @unlink(STORAGE_PATH . '/' . $bucket . '/' . $filename);
        }
        throw $exception;
    }
    return $saved;
}

function save_property_documents(array $input): array
{
    if (!isset($input['name']) || !is_array($input['name'])) {
        return [];
    }
    if (count($input['name']) > 10) {
        throw new RuntimeException('Dix documents maximum par enregistrement.');
    }
    $directory = STORAGE_PATH . '/property-documents';
    if (!is_dir($directory) && !mkdir($directory,0700,true) && !is_dir($directory)) {
        throw new RuntimeException('Le stockage des documents est indisponible.');
    }
    $saved=[];
    $total=0;
    $finfo=new finfo(FILEINFO_MIME_TYPE);
    try {
        foreach ($input['name'] as $index=>$originalName) {
            $error=$input['error'][$index] ?? UPLOAD_ERR_NO_FILE;
            if ($error===UPLOAD_ERR_NO_FILE) { continue; }
            $temporary=(string)($input['tmp_name'][$index] ?? '');
            if ($error!==UPLOAD_ERR_OK || !is_uploaded_file($temporary)) {
                throw new RuntimeException('Un document n’a pas pu être téléversé.');
            }
            $size=(int)filesize($temporary);
            $extension=strtolower(pathinfo((string)$originalName,PATHINFO_EXTENSION));
            if ($size<1 || $size>8*1024*1024 || $total+$size>24*1024*1024 || $extension!=='pdf' || $finfo->file($temporary)!=='application/pdf') {
                throw new RuntimeException('Documents PDF uniquement · 8 Mo maximum par fichier et 24 Mo au total.');
            }
            $displayName=basename(str_replace('\\','/',(string)$originalName));
            $displayName=preg_replace('/[\x00-\x1F\x7F]/u','',$displayName) ?: 'Document.pdf';
            $displayName=mb_substr($displayName,0,180);
            $filename=bin2hex(random_bytes(16)).'.pdf';
            if (!move_uploaded_file($temporary,$directory.'/'.$filename)) {
                throw new RuntimeException('Impossible d’enregistrer un document.');
            }
            chmod($directory.'/'.$filename,0600);
            $saved[]=['name'=>$displayName,'path'=>$filename,'size'=>$size];
            $total+=$size;
        }
    } catch (Throwable $exception) {
        foreach ($saved as $document) { @unlink($directory.'/'.$document['path']); }
        throw $exception;
    }
    return $saved;
}

function serve_property_document(): never
{
    $documentId=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT);
    if (!$documentId || $documentId<1) { http_response_code(404); exit; }
    $stmt=db()->prepare('SELECT nom_fichier,chemin_fichier,taille_fichier FROM documents_terrains WHERE id=:id');
    $stmt->execute(['id'=>$documentId]);
    $document=$stmt->fetch();
    if (!$document || !preg_match('/\A[a-f0-9]{32}\.pdf\z/',$document['chemin_fichier'])) { http_response_code(404); exit; }
    $path=STORAGE_PATH.'/property-documents/'.$document['chemin_fichier'];
    if (!is_file($path) || (new finfo(FILEINFO_MIME_TYPE))->file($path)!=='application/pdf') { http_response_code(404); exit; }
    $safeName=preg_replace('/[^a-zA-Z0-9._-]/','_',pathinfo($document['nom_fichier'],PATHINFO_FILENAME)) ?: 'document';
    header('Content-Type: application/pdf');
    header('Content-Length: '.(string)filesize($path));
    header('Content-Disposition: attachment; filename="'.$safeName.'.pdf"');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');
    readfile($path);
    exit;
}

function serve_media(): never
{
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$id) {
        http_response_code(404);
        exit;
    }
    $stmt = db()->prepare('SELECT chemin_fichier, type FROM photos_terrains WHERE id=:id');
    $stmt->execute(['id'=>$id]);
    $media = $stmt->fetch();
    if (!$media || !preg_match('/\A[a-f0-9]{32}\.(jpg|png|mp4)\z/', $media['chemin_fichier'])) {
        http_response_code(404);
        exit;
    }
    $path = STORAGE_PATH . '/property/' . $media['chemin_fichier'];
    if (!is_file($path)) {
        http_response_code(404);
        exit;
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'video/mp4'], true)) {
        http_response_code(415);
        exit;
    }
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . (string) filesize($path));
    header('Cache-Control: public, max-age=86400');
    readfile($path);
    exit;
}

function serve_submission_file(): never
{
    require_admin();
    $submissionId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    $fileIndex = filter_input(INPUT_GET, 'file', FILTER_VALIDATE_INT);
    $stmt = db()->prepare('SELECT fichiers_justificatifs FROM soumissions_vendeurs WHERE id=:id');
    $stmt->execute(['id'=>$submissionId ?: 0]);
    $files = json_decode((string) $stmt->fetchColumn(), true);
    $filename = is_array($files) && $fileIndex !== false ? ($files[$fileIndex] ?? null) : null;
    if (!is_string($filename) || !preg_match('/\A[a-f0-9]{32}\.(jpg|png|mp4|pdf)\z/', $filename)) {
        http_response_code(404);
        exit;
    }
    $path = STORAGE_PATH . '/submissions/' . $filename;
    if (!is_file($path)) {
        http_response_code(404);
        exit;
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'video/mp4', 'application/pdf'], true)) {
        http_response_code(415);
        exit;
    }
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . (string) filesize($path));
    header('Content-Disposition: attachment; filename="justificatif-' . (int) $submissionId . '-' . (int) $fileIndex . '.' . pathinfo($filename, PATHINFO_EXTENSION) . '"');
    header('X-Content-Type-Options: nosniff');
    readfile($path);
    exit;
}