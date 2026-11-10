<?php
declare(strict_types=1);

function notification_criteria_match(array $criteria, array $land): bool
{
    $commune=trim((string)($criteria['commune'] ?? ''));
    if ($commune!=='' && stripos((string)$land['zone_commune'],$commune)===false && stripos((string)$land['quartier'],$commune)===false) { return false; }
    $type=(string)($criteria['type_bien'] ?? '');
    if ($type!=='' && $type!==(string)$land['type_bien']) { return false; }
    $budget=$criteria['budget_max'] ?? null;
    if (is_numeric($budget) && (float)$budget>0 && (float)$land['prix']>(float)$budget) { return false; }
    $area=$criteria['superficie_min'] ?? null;
    if (is_numeric($area) && (float)$area>0 && (float)$land['superficie']<(float)$area) { return false; }
    $legal=(string)($criteria['statut_juridique'] ?? '');
    if ($legal!=='' && $legal!==(string)$land['statut_juridique']) { return false; }
    return true;
}

function notification_base_url(): string
{
    $base=rtrim((string)(getenv('APP_URL') ?: ''),'/');
    if ($base!=='') { return $base; }
    $scheme=(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off')?'https':'http';
    $host=preg_replace('/[^a-zA-Z0-9.\-\[\]:]/','',(string)($_SERVER['HTTP_HOST'] ?? 'localhost')) ?: 'localhost';
    $scriptName=(string)($_SERVER['SCRIPT_NAME'] ?? '');
    $path=$scriptName!=='' ? rtrim(str_replace('\\','/',dirname($scriptName)),'/') : '';
    if ($path==='.' || $path==='/') { $path=''; }
    return $scheme.'://'.$host.$path;
}

function envoyerAlerteNouveauTerrain(int $terrain_id): array
{
    $result=['terrain_id'=>$terrain_id,'attempted'=>0,'sent'=>0,'failed'=>0,'skipped'=>0,'whatsapp_url'=>null];
    try {
        $terrainQuery=db()->prepare('SELECT id,reference,titre,type_bien,prix,superficie,statut,statut_juridique,zone_commune,quartier,description FROM terrains WHERE id=:id AND statut="disponible" LIMIT 1');
        $terrainQuery->execute(['id'=>$terrain_id]);
        $land=$terrainQuery->fetch();
        if (!$land) { return $result; }
        $subscribers=db()->query('SELECT id,nom,email,telephone,criteres,token_desabonnement FROM abonnes_alertes WHERE actif=1 ORDER BY id')->fetchAll();
    } catch (Throwable $exception) {
        error_log('Notifications terrains: lecture des données impossible. '.$exception->getMessage());
        $result['failed']++;
        return $result;
    }

    $baseUrl=notification_base_url();
    $detailUrl=$baseUrl.'/terrain-detail.php?id='.(int)$land['id'];
    $location=trim((string)$land['quartier'].' / '.(string)$land['zone_commune'],' /');
    $reference=(string)($land['reference'] ?: 'SG-TR-'.str_pad((string)$land['id'],6,'0',STR_PAD_LEFT));
    $price=number_format((float)$land['prix'],0,',',' ').' FCFA';
    $whatsAppMessage="Bonjour SG SERVICES INTERNATIONAL,\n\nNouveau terrain disponible :\n- Titre : ".$land['titre']."\n- Référence : ".$reference."\n- Prix : ".$price."\n- Localisation : ".$location."\n\nFiche détaillée : ".$detailUrl;
    $result['whatsapp_url']='https://wa.me/?text='.rawurlencode($whatsAppMessage);

    $fromEmail=(string)(getenv('ALERT_FROM_EMAIL') ?: 'no-reply@sgservices.ci');
    if (!filter_var($fromEmail,FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/',$fromEmail)) {
        error_log('Notifications terrains: ALERT_FROM_EMAIL invalide.');
        $result['failed']++;
        return $result;
    }
    $safeTitle=e($land['titre']);
    $safeReference=e($reference);
    $safePrice=e($price);
    $safeLocation=e($location);
    $safeLegal=e($land['statut_juridique']);
    $safeDescription=nl2br(e(mb_substr((string)$land['description'],0,1500)));
    $subject='Nouveau terrain : '.preg_replace('/[\r\n]+/',' ',(string)$land['titre']);
    if (function_exists('mb_encode_mimeheader')) { $subject=mb_encode_mimeheader($subject,'UTF-8'); }
    foreach ($subscribers as $subscriber) {
        $criteria=json_decode((string)($subscriber['criteres'] ?? '{}'),true);
        if (!notification_criteria_match(is_array($criteria)?$criteria:[],$land)) { $result['skipped']++; continue; }
        $result['attempted']++;
        $unsubscribeUrl=$baseUrl.'/?page=alertes&unsubscribe='.rawurlencode((string)$subscriber['token_desabonnement']);
        $headers=[
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: SG Services International <'.$fromEmail.'>',
            'List-Unsubscribe: <'.$unsubscribeUrl.'>',
        ];
        $headersString=implode("\r\n",$headers);
        $safeName=e($subscriber['nom']);
        $safeUnsubscribe=e($unsubscribeUrl);
        $html='<!doctype html><html lang="fr"><body style="margin:0;background:#f6f8f6;font-family:Arial,sans-serif;color:#17313a"><div style="max-width:620px;margin:24px auto;padding:28px;background:#fff;border:1px solid #e1e8e3;border-radius:12px"><p style="color:#16745c;font-weight:bold">SG SERVICES INTERNATIONAL</p><h1 style="font-size:24px">Bonjour '.$safeName.', un nouveau terrain vous attend.</h1><p>Une nouvelle offre vient d’être publiée sur notre site.</p><div style="padding:18px;background:#f4f7f4;border-radius:9px"><h2 style="margin:0 0 12px;font-size:19px">'.$safeTitle.'</h2><p><b>Référence :</b> '.$safeReference.'</p><p><b>Prix :</b> '.$safePrice.'</p><p><b>Localisation :</b> '.$safeLocation.'</p><p><b>Statut juridique déclaré :</b> '.$safeLegal.'</p><p>'.$safeDescription.'</p></div><p style="margin:24px 0"><a href="'.e($detailUrl).'" style="display:inline-block;padding:13px 20px;background:#16745c;color:#fff;text-decoration:none;border-radius:7px;font-weight:bold">Voir la fiche terrain</a></p><p style="font-size:11px;color:#71817c">Les informations juridiques sont déclaratives et doivent être vérifiées avant toute transaction.</p><hr style="border:0;border-top:1px solid #e1e8e3"><p style="font-size:10px;color:#71817c">Vous recevez cette alerte car vous êtes inscrit(e) aux alertes foncières. <a href="'.$safeUnsubscribe.'">Me désabonner</a></p></div></body></html>';
        try {
            if (@mail((string)$subscriber['email'],$subject,$html,$headersString)) { $result['sent']++; }
            else { $result['failed']++; }
        } catch (Throwable $exception) {
            $result['failed']++;
            error_log('Notifications terrains: échec mail pour abonné #'.(int)$subscriber['id'].'. '.$exception->getMessage());
        }
    }
    return $result;
}