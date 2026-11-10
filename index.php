<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/includes/functions_notifications.php';

$page = (string) ($_GET['page'] ?? 'home');
$publicPages = ['home', 'terrains', 'terrain', 'terrain_document', 'vendre', 'contact', 'services', 'about', 'avis', 'guide', 'alertes', 'login', 'logout', 'media', 'logo', 'visual', 'download', 'pdf', 'admin'];
if (!in_array($page, $publicPages, true)) {
    http_response_code(404);
    $page = '404';
}
if ($page === 'login') {
    redirect('admin/login.php');
}
if ($page === 'admin') {
    require __DIR__ . '/admin/check-auth.php';
}

if ($page === 'logout') {
    require_admin();
    verify_csrf();
    $_SESSION = [];
    session_destroy();
    redirect('?page=home');
}

if ($page === 'media') {
    serve_media();
}
if ($page === 'logo') {
    serve_logo();
}
if ($page === 'visual') {
    serve_home_visual();
}
if ($page === 'download') {
    serve_submission_file();
}
if ($page === 'terrain_document') {
    serve_property_document();
}
if ($page === 'alertes' && isset($_GET['unsubscribe'])) {
    $token=(string)$_GET['unsubscribe'];
    if (preg_match('/\A[a-f0-9]{64}\z/',$token)) {
        db()->prepare('UPDATE abonnes_alertes SET actif=0 WHERE token_desabonnement=:token')->execute(['token'=>$token]);
    }
    flash('success','Votre préférence de désabonnement a été prise en compte.');
    redirect('?page=alertes');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handle_post($page);
}

if ($page === 'pdf') {
    require __DIR__ . '/app/pdf.php';
    exit;
}
if ($page === 'guide' && isset($_GET['download'])) {
    require __DIR__ . '/app/guide_pdf.php';
    exit;
}

$titleMap = [
    'home' => 'Terrains & construction à Abidjan', 'terrains' => 'Terrains à vendre',
    'terrain' => 'Fiche terrain', 'vendre' => 'Proposer un bien', 'contact' => 'Contactez-nous',
    'services' => 'Nos services', 'about' => 'À propos', 'avis' => 'Témoignages',
    'guide' => 'Guide foncier', 'alertes'=>'Alertes terrains', 'login' => 'Administration', 'admin' => 'Espace administration',
    '404' => 'Page introuvable',
];
$title = $titleMap[$page] ?? 'SG Services International';
require __DIR__ . '/views/header.php';
if ($page === 'admin') {
    require_admin();
    if (isset($_GET['edit']) || isset($_GET['new'])) {
        require __DIR__ . '/admin/terrain-edit.php';
    } else {
        require __DIR__ . '/views/admin.php';
        require __DIR__ . '/views/admin_extensions.php';
        require __DIR__ . '/views/admin_home_media.php';
        require __DIR__ . '/views/admin_alerts.php';
    }
} else {
    $realisationReviewId = $page === 'avis' ? filter_input(INPUT_GET, 'realisation', FILTER_VALIDATE_INT) : null;
    if ($realisationReviewId) {
        require __DIR__ . '/views/avis_realisation.php';
    } else {
        $view = __DIR__ . '/views/' . $page . '.php';
        if (is_file($view)) {
            require $view;
        } else {
            require __DIR__ . '/views/info.php';
        }
        if ($page === 'terrain' && isset($land) && is_array($land)) {
            require __DIR__ . '/views/reviews_terrain.php';
        }
    }
}
require __DIR__ . '/views/footer.php';

function handle_post(string $page): never
{
    verify_csrf();
    if ($page === 'login') {
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        $ipHash = hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        $attemptQuery = db()->prepare('SELECT echecs FROM tentatives_connexion WHERE email=:email AND empreinte_ip=:ip AND derniere_tentative > (NOW() - INTERVAL 15 MINUTE)');
        $attemptQuery->execute(['email'=>$email, 'ip'=>$ipHash]);
        $attempts = (int) ($attemptQuery->fetchColumn() ?: 0);
        if ($attempts >= 5) {
            flash('error', 'Trop de tentatives. Réessayez dans quelques minutes.');
            redirect('?page=login');
        }
        $stmt = db()->prepare('SELECT id, nom, email, mot_de_passe, role FROM utilisateurs WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['mot_de_passe'])) {
            session_regenerate_id(true);
            unset($user['mot_de_passe']);
            $_SESSION['user'] = $user;
            db()->prepare('DELETE FROM tentatives_connexion WHERE email=:email AND empreinte_ip=:ip')->execute(['email'=>$email, 'ip'=>$ipHash]);
            redirect('?page=admin');
        }
        $recordAttempt = db()->prepare('INSERT INTO tentatives_connexion (email, empreinte_ip, echecs) VALUES (:email, :ip, 1) ON DUPLICATE KEY UPDATE echecs=IF(derniere_tentative > (NOW() - INTERVAL 15 MINUTE), echecs + 1, 1), derniere_tentative=NOW()');
        $recordAttempt->execute(['email'=>$email, 'ip'=>$ipHash]);
        flash('error', 'Identifiants incorrects.');
        redirect('?page=login');
    }
    if ($page === 'alertes' && ($_POST['action'] ?? '') === 'subscribe_alerts') {
        if (trim((string)($_POST['website'] ?? ''))!=='') { flash('success','Votre inscription a bien été prise en compte.'); redirect('?page=alertes'); }
        $name=trim((string)($_POST['nom'] ?? ''));
        $email=strtolower(trim((string)($_POST['email'] ?? '')));
        $phone=trim((string)($_POST['telephone'] ?? ''));
        $type=(string)($_POST['type_bien'] ?? '');
        $legal=(string)($_POST['statut_juridique'] ?? '');
        $commune=trim((string)($_POST['commune'] ?? ''));
        $budget=trim((string)($_POST['budget_max'] ?? ''));
        $area=trim((string)($_POST['superficie_min'] ?? ''));
        if (($_POST['consentement'] ?? '')!=='oui' || $name==='' || mb_strlen($name)>120 || !filter_var($email,FILTER_VALIDATE_EMAIL) || mb_strlen($email)>190 || mb_strlen($phone)>60 || ($type!=='' && !in_array($type,['terrain','maison','lot_loti'],true)) || ($legal!=='' && !in_array($legal,['Titre Foncier','ACD','Attestation Villageoise','Comprouis','À vérifier'],true)) || ($budget!=='' && (!is_numeric($budget) || !is_finite((float)$budget) || (float)$budget<0)) || ($area!=='' && (!is_numeric($area) || !is_finite((float)$area) || (float)$area<0)) || mb_strlen($commune)>120) {
            flash('error','Vérifiez votre nom, votre e-mail et vos critères de recherche.');
            redirect('?page=alertes');
        }
        $criteria=array_filter(['commune'=>$commune,'type_bien'=>$type,'statut_juridique'=>$legal,'budget_max'=>$budget!==''?(float)$budget:null,'superficie_min'=>$area!==''?(float)$area:null],static fn($value)=>$value!=='' && $value!==null);
        $token=bin2hex(random_bytes(32));
        $stmt=db()->prepare('INSERT INTO abonnes_alertes (nom,email,telephone,actif,criteres,token_desabonnement) VALUES (:nom,:email,:telephone,1,:criteres,:token) ON DUPLICATE KEY UPDATE nom=VALUES(nom),telephone=VALUES(telephone),actif=1,criteres=VALUES(criteres),token_desabonnement=VALUES(token_desabonnement)');
        $stmt->execute(['nom'=>$name,'email'=>$email,'telephone'=>$phone,'criteres'=>json_encode($criteria,JSON_THROW_ON_ERROR),'token'=>$token]);
        flash('success','Inscription confirmée. Vous recevrez un e-mail lors de la publication d’un terrain correspondant à vos critères.');
        redirect('?page=alertes');
    }

    if (in_array($page, ['terrain', 'avis'], true) && ($_POST['action'] ?? '') === 'submit_review') {
        save_review($page);
    }

    if ($page === 'contact' || $page === 'terrain') {
        $name = trim((string) ($_POST['nom'] ?? ''));
        $contact = trim((string) ($_POST['contact'] ?? ''));
        $message = trim((string) ($_POST['message'] ?? ''));
        $terrainId = $page === 'terrain' ? filter_input(INPUT_POST, 'terrain_id', FILTER_VALIDATE_INT) : null;
        if ($name === '' || $contact === '' || $message === '' || mb_strlen($message) > 4000) {
            flash('error', 'Veuillez vérifier les champs du formulaire.');
            redirect($page === 'terrain' ? '?page=terrain&id=' . (int) $terrainId : '?page=contact');
        }
        if ($terrainId) {
            $exists = db()->prepare('SELECT id FROM terrains WHERE id = :id');
            $exists->execute(['id' => $terrainId]);
            if (!$exists->fetch()) {
                http_response_code(404);
                exit('Terrain introuvable.');
            }
        }
        $stmt = db()->prepare('INSERT INTO demandes_clients (terrain_id, nom, contact, message) VALUES (:terrain_id, :nom, :contact, :message)');
        $stmt->execute(['terrain_id' => $terrainId ?: null, 'nom' => $name, 'contact' => $contact, 'message' => $message]);
        flash('success', 'Votre demande a bien été envoyée. Nous vous recontacterons rapidement.');
        redirect($page === 'terrain' ? '?page=terrain&id=' . (int) $terrainId : '?page=contact');
    }

    if ($page === 'vendre') {
        save_submission();
    }

    if ($page === 'admin') {
        require_admin();
        handle_admin_post();
    }
    http_response_code(405);
    exit('Action non autorisée.');
}

function save_submission(): never
{
    $name = trim((string) ($_POST['nom_proprietaire'] ?? ''));
    $phone = trim((string) ($_POST['contact_whatsapp'] ?? ''));
    $type = (string) ($_POST['type_bien'] ?? '');
    $description = trim((string) ($_POST['description'] ?? ''));
    $price = filter_input(INPUT_POST, 'prix_souhaite', FILTER_VALIDATE_FLOAT);
    $localisation = trim((string) ($_POST['localisation'] ?? ''));
    $area = filter_input(INPUT_POST, 'superficie', FILTER_VALIDATE_FLOAT);
    if ($name === '' || $phone === '' || !in_array($type, ['terrain', 'maison', 'lot_loti'], true) || $description === '' || mb_strlen($description) > 4000 || $localisation === '' || $price === false || $price === null || $price < 0 || $area === false || $area === null || $area < 0) {
        flash('error', 'Veuillez compléter les informations requises correctement.');
        redirect('?page=vendre');
    }
    $files = save_uploaded_files($_FILES['justificatifs'] ?? [], 'submission');
    try {
        $stmt = db()->prepare('INSERT INTO soumissions_vendeurs (nom_proprietaire, contact_whatsapp, type_bien, description, prix_souhaite, fichiers_justificatifs, localisation, superficie) VALUES (:nom, :contact, :type, :description, :prix, :files, :localisation, :superficie)');
        $stmt->execute(['nom' => $name, 'contact' => $phone, 'type' => $type, 'description' => $description, 'prix' => $price, 'files' => json_encode($files, JSON_THROW_ON_ERROR), 'localisation' => $localisation, 'superficie' => $area]);
    } catch (Throwable $exception) {
        foreach ($files as $file) {
            @unlink(STORAGE_PATH . '/submissions/' . $file);
        }
        throw $exception;
    }
    flash('success', 'Merci. Votre bien a été transmis à notre équipe pour étude.');
    redirect('?page=vendre');
}

function handle_admin_post(): never
{
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'save_land') {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: null;
        $title = trim((string) ($_POST['titre'] ?? ''));
        $propertyType = (string) ($_POST['type_bien'] ?? 'terrain');
        $district = trim((string)($_POST['quartier'] ?? ''));
        $allowedFeatures = ['eau','electricite','route','non_inondable','ecoles','transports','assainissement','lotissement_cloture'];
        $featuresInput = is_array($_POST['caracteristiques'] ?? null) ? array_map('strval',$_POST['caracteristiques']) : [];
        $features = array_values(array_intersect($allowedFeatures,$featuresInput));
        $description = trim((string) ($_POST['description'] ?? ''));
        $price = filter_input(INPUT_POST, 'prix', FILTER_VALIDATE_FLOAT);
        $area = filter_input(INPUT_POST, 'superficie', FILTER_VALIDATE_FLOAT);
        $status = (string) ($_POST['statut'] ?? 'disponible');
        $legal = (string) ($_POST['statut_juridique'] ?? '');
        $zone = trim((string) ($_POST['zone_commune'] ?? ''));
        $lat = filter_input(INPUT_POST, 'latitude', FILTER_VALIDATE_FLOAT);
        $lng = filter_input(INPUT_POST, 'longitude', FILTER_VALIDATE_FLOAT);
        if ($title === '' || !in_array($propertyType, ['terrain','maison','lot_loti'], true) || mb_strlen($district)>120 || $description === '' || mb_strlen($description)>12000 || $price === false || $price === null || $price < 0 || $area === false || $area === null || $area <= 0 || !in_array($status, ['disponible', 'reserve', 'vendu'], true) || $legal === '' || mb_strlen($legal)>100 || $zone === '' || $lat === false || $lat === null || $lng === false || $lng === null || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            flash('error', 'Vérifiez les données du terrain.');
            redirect('?page=admin');
        }
        $created=!$id;
        if ($id) {
            $stmt = db()->prepare('UPDATE terrains SET titre=:titre,type_bien=:type,description=:description,prix=:prix,superficie=:superficie,statut=:statut,statut_juridique=:legal,zone_commune=:zone,quartier=:quartier,caracteristiques=:features,latitude=:lat,longitude=:lng WHERE id=:id');
            $stmt->execute(['titre'=>$title,'type'=>$propertyType,'description'=>$description,'prix'=>$price,'superficie'=>$area,'statut'=>$status,'legal'=>$legal,'zone'=>$zone,'quartier'=>$district,'features'=>json_encode($features,JSON_THROW_ON_ERROR),'lat'=>$lat,'lng'=>$lng,'id'=>$id]);
        } else {
            $stmt = db()->prepare('INSERT INTO terrains (titre,type_bien,description,prix,superficie,statut,statut_juridique,zone_commune,quartier,caracteristiques,latitude,longitude) VALUES (:titre,:type,:description,:prix,:superficie,:statut,:legal,:zone,:quartier,:features,:lat,:lng)');
            $stmt->execute(['titre'=>$title,'type'=>$propertyType,'description'=>$description,'prix'=>$price,'superficie'=>$area,'statut'=>$status,'legal'=>$legal,'zone'=>$zone,'quartier'=>$district,'features'=>json_encode($features,JSON_THROW_ON_ERROR),'lat'=>$lat,'lng'=>$lng]);
            $id = (int) db()->lastInsertId();
            $reference='SG-TR-'.str_pad((string)$id,6,'0',STR_PAD_LEFT);
            db()->prepare('UPDATE terrains SET reference=:reference WHERE id=:id')->execute(['reference'=>$reference,'id'=>$id]);
        }
        $photos = save_uploaded_files($_FILES['photos'] ?? [], 'property');
        if ($photos) {
            $stmt = db()->prepare('INSERT INTO photos_terrains (terrain_id, chemin_fichier, type) VALUES (:id,:path,:type)');
            foreach ($photos as $photo) {
                $stmt->execute(['id'=>$id, 'path'=>$photo, 'type'=>'photo']);
            }
        }
        try {
            $documents=save_property_documents($_FILES['documents'] ?? []);
            if ($documents) {
                $documentStmt=db()->prepare('INSERT INTO documents_terrains (terrain_id,nom_fichier,chemin_fichier,taille_fichier) VALUES (:terrain_id,:nom,:chemin,:taille)');
                foreach ($documents as $document) {
                    $documentStmt->execute(['terrain_id'=>$id,'nom'=>$document['name'],'chemin'=>$document['path'],'taille'=>$document['size']]);
                }
            }
        } catch (RuntimeException $exception) {
            flash('error',$exception->getMessage());
            redirect('?page=admin&edit='.(int)$id);
        } catch (Throwable $exception) {
            foreach ($documents ?? [] as $document) { @unlink(STORAGE_PATH.'/property-documents/'.$document['path']); }
            throw $exception;
        }
        if ($created && $status==='disponible') {
            $notification=envoyerAlerteNouveauTerrain((int)$id);
            flash('success','La fiche terrain a été enregistrée. E-mails envoyés : '.(int)$notification['sent'].' ; échecs : '.(int)$notification['failed'].'.');
            redirect('?page=admin');
        }
            flash('success', 'La fiche terrain a été enregistrée.');
        redirect('?page=admin');
    }
    if ($action === 'add_property_documents') {
        $terrainId=filter_input(INPUT_POST,'terrain_id',FILTER_VALIDATE_INT);
        $check=db()->prepare('SELECT id FROM terrains WHERE id=:id');
        $check->execute(['id'=>$terrainId ?: 0]);
        if (!$terrainId || !$check->fetchColumn()) { http_response_code(404); exit('Terrain introuvable.'); }
        try {
            $documents=save_property_documents($_FILES['documents'] ?? []);
            if (!$documents) { flash('error','Sélectionnez au moins un document PDF.'); redirect('?page=admin&edit='.(int)$terrainId); }
            $insert=db()->prepare('INSERT INTO documents_terrains (terrain_id,nom_fichier,chemin_fichier,taille_fichier) VALUES (:terrain_id,:nom,:chemin,:taille)');
            foreach ($documents as $document) { $insert->execute(['terrain_id'=>$terrainId,'nom'=>$document['name'],'chemin'=>$document['path'],'taille'=>$document['size']]); }
        } catch (RuntimeException $exception) {
            flash('error',$exception->getMessage());
            redirect('?page=admin&edit='.(int)$terrainId);
        } catch (Throwable $exception) {
            foreach ($documents ?? [] as $document) { @unlink(STORAGE_PATH.'/property-documents/'.$document['path']); }
            error_log($exception->getMessage());
            flash('error','Les documents n’ont pas pu être enregistrés.');
            redirect('?page=admin&edit='.(int)$terrainId);
        }
        flash('success','Les documents PDF ont été ajoutés à la fiche.');
        redirect('?page=admin&edit='.(int)$terrainId);
    }
    if ($action === 'save_branding') {
        $oldLogo = site_setting('logo_path');
        try {
            $newLogo = save_logo_upload($_FILES['logo'] ?? []);
            db()->prepare('INSERT INTO parametres (cle,valeur) VALUES (:cle,:valeur) ON DUPLICATE KEY UPDATE valeur=VALUES(valeur)')->execute(['cle'=>'logo_path','valeur'=>$newLogo]);
        } catch (RuntimeException $exception) {
            flash('error', $exception->getMessage());
            redirect('?page=admin');
        } catch (Throwable $exception) {
            if (isset($newLogo)) {
                @unlink(STORAGE_PATH . '/branding/' . $newLogo);
            }
            error_log($exception->getMessage());
            flash('error', 'Le logo n’a pas pu être enregistré.');
            redirect('?page=admin');
        }
        if ($oldLogo && preg_match('/\A[a-f0-9]{32}\.(png|jpg|svg)\z/', $oldLogo) && $oldLogo !== $newLogo) {
            @unlink(STORAGE_PATH . '/branding/' . $oldLogo);
        }
        flash('success', 'Le logo du site et des nouvelles fiches PDF a été mis à jour.');
        redirect('?page=admin');
    }
    if ($action === 'save_hero_image') {
        $title = trim((string)($_POST['titre'] ?? ''));
        $alt = trim((string)($_POST['texte_alternatif'] ?? ''));
        if ($title === '' || mb_strlen($title) > 140 || $alt === '' || mb_strlen($alt) > 180) {
            flash('error', 'Renseignez le titre et le texte alternatif du visuel.');
            redirect('?page=admin#home-media');
        }
        try {
            $filename = save_home_image($_FILES['image'] ?? [], 'hero');
            $position = (int)db()->query('SELECT COALESCE(MAX(position),-1)+1 FROM images_hero')->fetchColumn();
            db()->prepare('INSERT INTO images_hero (titre,texte_alternatif,chemin_fichier,position) VALUES (:titre,:alt,:fichier,:position)')->execute(['titre'=>$title,'alt'=>$alt,'fichier'=>$filename,'position'=>$position]);
        } catch (RuntimeException $exception) {
            flash('error', $exception->getMessage());
            redirect('?page=admin#home-media');
        } catch (Throwable $exception) {
            if (isset($filename)) { remove_stored_image('hero', $filename); }
            error_log($exception->getMessage());
            flash('error', 'Le visuel hero n’a pas pu être enregistré.');
            redirect('?page=admin#home-media');
        }
        flash('success', 'Le visuel a été ajouté au diaporama de la page d’accueil.');
        redirect('?page=admin#home-media');
    }
    if ($action === 'hero_image_status' || $action === 'delete_hero_image') {
        $id = (int)($_POST['id'] ?? 0);
        if ($action === 'hero_image_status') {
            db()->prepare('UPDATE images_hero SET actif=1-actif WHERE id=:id')->execute(['id'=>$id]);
            flash('success', 'La visibilité du visuel a été modifiée.');
        } else {
            $stmt=db()->prepare('SELECT chemin_fichier FROM images_hero WHERE id=:id');
            $stmt->execute(['id'=>$id]);
            $filename=$stmt->fetchColumn();
            db()->prepare('DELETE FROM images_hero WHERE id=:id')->execute(['id'=>$id]);
            if (is_string($filename)) { remove_stored_image('hero',$filename); }
            flash('success', 'Le visuel hero a été supprimé.');
        }
        redirect('?page=admin#home-media');
    }
    if ($action === 'update_hero_image') {
        $id=(int)($_POST['id'] ?? 0);
        $title=trim((string)($_POST['titre'] ?? ''));
        $alt=trim((string)($_POST['texte_alternatif'] ?? ''));
        $position=filter_input(INPUT_POST,'position',FILTER_VALIDATE_INT);
        if ($title === '' || mb_strlen($title)>140 || $alt === '' || mb_strlen($alt)>180 || $position === false || $position === null || $position<0 || $position>999) {
            flash('error','Vérifiez le titre, le texte alternatif et l’ordre du visuel.');
            redirect('?page=admin#home-media');
        }
        db()->prepare('UPDATE images_hero SET titre=:titre,texte_alternatif=:alt,position=:position WHERE id=:id')->execute(['titre'=>$title,'alt'=>$alt,'position'=>$position,'id'=>$id]);
        flash('success','Les informations du visuel ont été modifiées.');
        redirect('?page=admin#home-media');
    }
    if ($action === 'replace_hero_image') {
        $id=(int)($_POST['id'] ?? 0);
        $stmt=db()->prepare('SELECT chemin_fichier FROM images_hero WHERE id=:id');
        $stmt->execute(['id'=>$id]);
        $oldFile=$stmt->fetchColumn();
        if (!is_string($oldFile)) {
            flash('error','Visuel introuvable.');
            redirect('?page=admin#home-media');
        }
        try {
            $newFile=save_home_image($_FILES['image'] ?? [],'hero');
            db()->prepare('UPDATE images_hero SET chemin_fichier=:file WHERE id=:id')->execute(['file'=>$newFile,'id'=>$id]);
        } catch (RuntimeException $exception) {
            flash('error',$exception->getMessage());
            redirect('?page=admin#home-media');
        } catch (Throwable $exception) {
            if (isset($newFile)) { remove_stored_image('hero',$newFile); }
            error_log($exception->getMessage());
            flash('error','Le fichier hero n’a pas pu être remplacé.');
            redirect('?page=admin#home-media');
        }
        remove_stored_image('hero',$oldFile);
        flash('success','L’image hero a été remplacée.');
        redirect('?page=admin#home-media');
    }
    if ($action === 'save_project') {
        $title=trim((string)($_POST['titre'] ?? ''));
        $category=trim((string)($_POST['categorie'] ?? ''));
        $description=trim((string)($_POST['description'] ?? ''));
        if ($title === '' || mb_strlen($title)>160 || $category === '' || mb_strlen($category)>120 || $description === '' || mb_strlen($description)>1200) {
            flash('error','Vérifiez le titre, la catégorie et la description de la réalisation.');
            redirect('?page=admin#home-media');
        }
        try {
            $filename=save_home_image($_FILES['image'] ?? [],'projects');
            db()->prepare('INSERT INTO realisations (titre,categorie,description,chemin_fichier) VALUES (:titre,:categorie,:description,:fichier)')->execute(['titre'=>$title,'categorie'=>$category,'description'=>$description,'fichier'=>$filename]);
        } catch (RuntimeException $exception) {
            flash('error',$exception->getMessage());
            redirect('?page=admin#home-media');
        } catch (Throwable $exception) {
            if (isset($filename)) { remove_stored_image('projects',$filename); }
            error_log($exception->getMessage());
            flash('error','La réalisation n’a pas pu être enregistrée.');
            redirect('?page=admin#home-media');
        }
        flash('success','La réalisation a été ajoutée au carrousel.');
        redirect('?page=admin#home-media');
    }
    if ($action === 'project_status' || $action === 'delete_project') {
        $id=(int)($_POST['id'] ?? 0);
        if ($action === 'project_status') {
            db()->prepare('UPDATE realisations SET actif=1-actif WHERE id=:id')->execute(['id'=>$id]);
            flash('success','La visibilité de la réalisation a été modifiée.');
        } else {
            $stmt=db()->prepare('SELECT chemin_fichier FROM realisations WHERE id=:id');
            $stmt->execute(['id'=>$id]);
            $filename=$stmt->fetchColumn();
            db()->prepare('DELETE FROM realisations WHERE id=:id')->execute(['id'=>$id]);
            if (is_string($filename)) { remove_stored_image('projects',$filename); }
            flash('success','La réalisation a été supprimée.');
        }
        redirect('?page=admin#home-media');
    }
    if ($action === 'update_land_type') {
        $type = (string) ($_POST['type_bien'] ?? '');
        if (!in_array($type, ['terrain','maison','lot_loti'], true)) {
            http_response_code(400);
            exit('Type de bien invalide.');
        }
        db()->prepare('UPDATE terrains SET type_bien=:type WHERE id=:id')->execute(['type'=>$type,'id'=>(int)($_POST['id'] ?? 0)]);
        flash('success', 'Type de bien mis à jour.');
        redirect('?page=admin');
    }
    if ($action === 'review_moderation') {
        $status = (string) ($_POST['status'] ?? '');
        if (!in_array($status, ['en_attente','publie','masque'], true)) {
            http_response_code(400);
            exit('Statut d’avis invalide.');
        }
        db()->prepare('UPDATE avis_terrains SET statut=:statut WHERE id=:id')->execute(['statut'=>$status,'id'=>(int)($_POST['id'] ?? 0)]);
        flash('success', 'La modération de l’avis a été enregistrée.');
        redirect('?page=admin#reviews');
    }
    if ($action === 'delete_review') {
        if (($_SESSION['user']['role'] ?? '') !== 'super_admin') {
            http_response_code(403);
            exit('Permission insuffisante.');
        }
        db()->prepare('DELETE FROM avis_terrains WHERE id=:id')->execute(['id'=>(int)($_POST['id'] ?? 0)]);
        flash('success', 'Avis supprimé.');
        redirect('?page=admin#reviews');
    }
    if ($action === 'delete_land') {
        if (($_SESSION['user']['role'] ?? '') !== 'super_admin') {
            http_response_code(403);
            exit('Permission insuffisante.');
        }
        $id=(int)($_POST['id'] ?? 0);
        $documentQuery=db()->prepare('SELECT chemin_fichier FROM documents_terrains WHERE terrain_id=:id');
        $documentQuery->execute(['id'=>$id]);
        $documentFiles=$documentQuery->fetchAll(PDO::FETCH_COLUMN);
        $photoQuery=db()->prepare('SELECT chemin_fichier FROM photos_terrains WHERE terrain_id=:id');
        $photoQuery->execute(['id'=>$id]);
        $photoFiles=$photoQuery->fetchAll(PDO::FETCH_COLUMN);
        db()->prepare('DELETE FROM terrains WHERE id=:id')->execute(['id'=>$id]);
        foreach ($documentFiles as $file) { if (preg_match('/\A[a-f0-9]{32}\.pdf\z',(string)$file)) { @unlink(STORAGE_PATH.'/property-documents/'.$file); } }
        foreach ($photoFiles as $file) { if (preg_match('/\A[a-f0-9]{32}\.(jpg|png|mp4)\z',(string)$file)) { @unlink(STORAGE_PATH.'/property/'.$file); } }
        flash('success', 'Terrain supprimé.');
        redirect('?page=admin');
    }
    if ($action === 'delete_property_document') {
        $documentId=(int)($_POST['document_id'] ?? 0);
        $terrainId=(int)($_POST['terrain_id'] ?? 0);
        $stmt=db()->prepare('SELECT chemin_fichier FROM documents_terrains WHERE id=:id AND terrain_id=:terrain_id');
        $stmt->execute(['id'=>$documentId,'terrain_id'=>$terrainId]);
        $filename=$stmt->fetchColumn();
        if (is_string($filename)) {
            db()->prepare('DELETE FROM documents_terrains WHERE id=:id AND terrain_id=:terrain_id')->execute(['id'=>$documentId,'terrain_id'=>$terrainId]);
            if (preg_match('/\A[a-f0-9]{32}\.pdf\z/',$filename)) { @unlink(STORAGE_PATH.'/property-documents/'.$filename); }
        }
        flash('success','Le document a été supprimé.');
        redirect('?page=admin&edit='.$terrainId);
    }
    if ($action === 'convert_submission') {
        $id = (int) ($_POST['id'] ?? 0);
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT * FROM soumissions_vendeurs WHERE id=:id FOR UPDATE');
            $stmt->execute(['id'=>$id]);
            $submission = $stmt->fetch();
            if (!$submission || $submission['statut_traitement'] === 'refuse' || $submission['terrain_id'] !== null) {
                throw new RuntimeException('Soumission introuvable, refusée ou déjà convertie.');
            }
            $stmt = $pdo->prepare('INSERT INTO terrains (titre,type_bien,description,prix,superficie,statut,statut_juridique,zone_commune,latitude,longitude) VALUES (:titre,:type,:description,:prix,:superficie,"disponible","À vérifier",:zone,5.3600,-4.0083)');
            $stmt->execute(['titre'=>ucfirst(str_replace('_', ' ', $submission['type_bien'])) . ' - ' . $submission['localisation'], 'type'=>$submission['type_bien'], 'description'=>$submission['description'], 'prix'=>$submission['prix_souhaite'], 'superficie'=>$submission['superficie'], 'zone'=>$submission['localisation']]);
            $terrainId = (int) $pdo->lastInsertId();
            $reference='SG-TR-'.str_pad((string)$terrainId,6,'0',STR_PAD_LEFT);
            $pdo->prepare('UPDATE terrains SET reference=:reference WHERE id=:id')->execute(['reference'=>$reference,'id'=>$terrainId]);
            $fileNames = json_decode((string) $submission['fichiers_justificatifs'], true) ?: [];
            $photoStmt = $pdo->prepare('INSERT INTO photos_terrains (terrain_id, chemin_fichier, type) VALUES (:id,:path,:type)');
            $copiedFiles = [];
            if (!is_dir(STORAGE_PATH . '/property') && !mkdir(STORAGE_PATH . '/property', 0700, true) && !is_dir(STORAGE_PATH . '/property')) {
                throw new RuntimeException('Le stockage des médias est indisponible.');
            }
            foreach ($fileNames as $fileName) {
                if (preg_match('/\A[a-f0-9]{32}\.(jpg|png|mp4)\z/', (string) $fileName)) {
                    $source = STORAGE_PATH . '/submissions/' . $fileName;
                    $destination = STORAGE_PATH . '/property/' . $fileName;
                    if (is_file($source) && (new finfo(FILEINFO_MIME_TYPE))->file($source) !== match (pathinfo($fileName, PATHINFO_EXTENSION)) {'jpg'=>'image/jpeg', 'png'=>'image/png', 'mp4'=>'video/mp4'}) {
                        continue;
                    }
                    if (is_file($source) && copy($source, $destination)) {
                        $copiedFiles[] = $destination;
                        $photoStmt->execute(['id'=>$terrainId, 'path'=>$fileName, 'type'=>str_ends_with($fileName, '.mp4') ? 'video' : 'photo']);
                    }
                }
            }
            $pdo->prepare('UPDATE soumissions_vendeurs SET statut_traitement="accepte", terrain_id=:terrain_id WHERE id=:id')->execute(['terrain_id'=>$terrainId, 'id'=>$id]);
            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            foreach ($copiedFiles ?? [] as $copiedFile) {
                @unlink($copiedFile);
            }
            throw $exception;
        }
        $notification=envoyerAlerteNouveauTerrain($terrainId);
        flash('success','La soumission est publiée. E-mails envoyés : '.(int)$notification['sent'].' ; échecs : '.(int)$notification['failed'].'.');
        redirect('?page=admin');
    }
    if ($action === 'submission_status') {
        $status = (string) ($_POST['status'] ?? 'nouveau');
        if (!in_array($status, ['nouveau', 'en_cours', 'accepte', 'refuse'], true)) {
            http_response_code(400);
            exit('Statut invalide.');
        }
        db()->prepare('UPDATE soumissions_vendeurs SET statut_traitement=:status WHERE id=:id')->execute(['status'=>$status, 'id'=>(int) ($_POST['id'] ?? 0)]);
        flash('success', 'Statut mis à jour.');
        redirect('?page=admin');
    }
    if ($action === 'request_status') {
        db()->prepare('UPDATE demandes_clients SET statut=:status WHERE id=:id')->execute(['status'=>($_POST['status'] ?? '') === 'lu' ? 'lu' : 'non_lu', 'id'=>(int) ($_POST['id'] ?? 0)]);
        flash('success', 'Demande mise à jour.');
        redirect('?page=admin');
    }
    http_response_code(400);
    exit('Action inconnue.');
}

function save_review(string $page): never
{
    if (trim((string) ($_POST['website'] ?? '')) !== '') {
        $check = db()->prepare('SELECT id FROM realisations WHERE id=:id AND actif=1');
        $check->execute(['id'=>$realisationId ?: 0]);
        if (!$check->fetchColumn()) {
            flash('error', 'Cette réalisation ne peut pas recevoir de nouvel avis.');
            redirect('?page=services');
        }
    } else {
        flash('error', 'Cible d’avis invalide.');
        redirect('?page=avis');
    }
    if ($name === '' || mb_strlen($name) > 120 || !is_int($rating) || $rating < 1 || $rating > 5 || $comment === '' || mb_strlen($comment) > 2000) {
        flash('error', 'Vérifiez votre nom, votre note et votre commentaire.');
        redirect($page === 'terrain' ? '?page=terrain&id=' . (int)$terrainId : ($targetType === 'realisation' ? '?page=avis&realisation=' . (int)$realisationId : '?page=avis'));
    }
    $ipHash = hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    $rateLimit = db()->prepare('SELECT COUNT(*) FROM avis_terrains WHERE empreinte_ip=:ip AND date_creation > (NOW() - INTERVAL 1 DAY)');
    $rateLimit->execute(['ip'=>$ipHash]);
    if ((int)$rateLimit->fetchColumn() >= 5) {
        flash('error', 'La limite quotidienne d’avis a été atteinte.');
        redirect($page === 'terrain' ? '?page=terrain&id=' . (int)$terrainId : ($targetType === 'realisation' ? '?page=avis&realisation=' . (int)$realisationId : '?page=avis'));
    }
    $stmt = db()->prepare('INSERT INTO avis_terrains (terrain_id,realisation_id,type_cible,cible_service,nom,note,commentaire,statut,empreinte_ip) VALUES (:terrain_id,:realisation_id,:type_cible,:service,:nom,:note,:commentaire,"en_attente",:ip)');
    $stmt->execute(['terrain_id'=>$terrainId,'realisation_id'=>$realisationId,'type_cible'=>$targetType,'service'=>$service,'nom'=>$name,'note'=>$rating,'commentaire'=>$comment,'ip'=>$ipHash]);
    flash('success', 'Merci pour votre avis. Il sera visible après modération.');
    redirect($page === 'terrain' ? '?page=terrain&id=' . (int)$terrainId . '#reviews' : ($targetType === 'realisation' ? '?page=avis&realisation=' . (int)$realisationId : '?page=avis'));
}

function remove_stored_image(string $bucket, string $filename): void
{
    if (in_array($bucket,['hero','projects'],true) && preg_match('/\A[a-f0-9]{32}\.(jpg|png)\z/',$filename)) {
        @unlink(STORAGE_PATH . '/' . $bucket . '/' . $filename);
    }
}