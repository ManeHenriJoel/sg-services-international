<?php
$activeSubscribers=(int)db()->query('SELECT COUNT(*) FROM abonnes_alertes WHERE actif=1')->fetchColumn();
$latestPublished=db()->query("SELECT id,reference,titre,prix,statut_juridique,zone_commune,quartier FROM terrains WHERE statut='disponible' ORDER BY date_publication DESC LIMIT 1")->fetch();
$whatsappUrl=null;
if ($latestPublished) {
    $landReference=$latestPublished['reference'] ?: 'SG-TR-'.str_pad((string)$latestPublished['id'],6,'0',STR_PAD_LEFT);
    $landLocation=trim((string)$latestPublished['quartier'].' / '.(string)$latestPublished['zone_commune'],' /');
    $url=notification_base_url().'/terrain-detail.php?id='.(int)$latestPublished['id'];
    $shareMessage="Bonjour SG SERVICES INTERNATIONAL,\n\nNouveau terrain disponible :\n- Titre : ".$latestPublished['titre']."\n- Référence : ".$landReference."\n- Prix : ".number_format((float)$latestPublished['prix'],0,',',' ' )." FCFA\n- Localisation : ".$landLocation."\n\nFiche détaillée : ".$url;
    $whatsappUrl='https://wa.me/?text='.rawurlencode($shareMessage);
}
?>
<div class="admin-shell alerts-admin"><section class="admin-section"><div class="admin-section-title"><div><span class="eyebrow">DIFFUSION & ABONNÉS</span><h2>Alertes nouveaux terrains</h2></div><div class="subscriber-count"><strong><?= $activeSubscribers ?></strong><span>abonnés actifs</span></div></div><p class="muted-copy">Les e-mails partent après publication d’un terrain disponible. Les préférences de recherche sont appliquées. WhatsApp reste une action manuelle afin que l’administrateur choisisse un destinataire ou une liste de diffusion.</p><?php if ($latestPublished && $whatsappUrl): ?><div class="whatsapp-broadcast"><div><strong>Message prêt à partager</strong><small><?= e($latestPublished['titre']) ?> · <?= e($latestPublished['reference'] ?: 'SG-TR-'.str_pad((string)$latestPublished['id'],6,'0',STR_PAD_LEFT)) ?></small></div><a class="button button-green" href="<?= e($whatsappUrl) ?>" target="_blank" rel="noopener noreferrer">Ouvrir WhatsApp <span>↗</span></a></div><?php else: ?><p class="admin-empty">Publiez un terrain disponible pour préparer un message WhatsApp.</p><?php endif; ?><a class="text-link" href="?page=alertes">Voir le formulaire public d’inscription →</a></section></div>