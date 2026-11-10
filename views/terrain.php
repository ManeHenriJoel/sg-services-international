<?php
$id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT);
$stmt=db()->prepare('SELECT * FROM terrains WHERE id=:id LIMIT 1');
$stmt->execute(['id'=>$id && $id>0 ? (int)$id : 0]);
$land=$stmt->fetch();
if (!$land) { http_response_code(404); echo '<section class="page-intro"><h1>Terrain introuvable</h1><a href="?page=terrains">Retour au catalogue</a></section>'; return; }
db()->prepare('UPDATE terrains SET vues=vues+1 WHERE id=:id')->execute(['id'=>(int)$land['id']]);
$mediaStmt=db()->prepare('SELECT id,type FROM photos_terrains WHERE terrain_id=:id ORDER BY id');
$mediaStmt->execute(['id'=>(int)$land['id']]);
$media=$mediaStmt->fetchAll();
$documentStmt=db()->prepare('SELECT id,nom_fichier,taille_fichier,date_ajout FROM documents_terrains WHERE terrain_id=:id ORDER BY date_ajout DESC');
$documentStmt->execute(['id'=>(int)$land['id']]);
$documents=$documentStmt->fetchAll();
$ratingStmt=db()->prepare("SELECT AVG(note) AS average,COUNT(*) AS total FROM avis_terrains WHERE terrain_id=:id AND statut='publie'");
$ratingStmt->execute(['id'=>(int)$land['id']]);
$rating=$ratingStmt->fetch();
$features=json_decode((string)($land['caracteristiques'] ?? '[]'),true);
$features=is_array($features)?$features:[];
$featureLabels=['eau'=>'Réseau d’eau','electricite'=>'Électricité','route'=>'Accès par route','non_inondable'=>'Zone déclarée non inondable','ecoles'=>'Écoles à proximité','transports'=>'Transports à proximité','assainissement'=>'Assainissement','lotissement_cloture'=>'Lotissement clôturé'];
$reference=$land['reference'] ?: 'SG-TR-'.str_pad((string)$land['id'],6,'0',STR_PAD_LEFT);
$baseUrl=rtrim((string)(getenv('APP_URL') ?: ''),'/');
if ($baseUrl==='') {
  $scheme=(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off')?'https':'http';
  $host=preg_replace('/[^a-zA-Z0-9.\-\[\]:]/','',(string)($_SERVER['HTTP_HOST'] ?? 'localhost')) ?: 'localhost';
  $basePath=rtrim(str_replace('\\','/',dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/index.php'))),'/');
  $baseUrl=$scheme.'://'.$host.$basePath;
}
$detailUrl=$baseUrl.'/terrain-detail.php?id='.(int)$land['id'];
$location=trim((string)($land['quartier'] ?? '').' / '.(string)$land['zone_commune'],' /');
$waMessage="Bonjour SG SERVICES INTERNATIONAL,\n\nJe suis intéressé(e) par le terrain suivant disponible sur votre site :\n- Titre : ".$land['titre']."\n- Référence : ".$reference."\n- Prix : ".number_format((float)$land['prix'],0,',',' ' )." FCFA\n- Localisation : ".$location."\n\nLien vers la fiche : ".$detailUrl."\n\nJ'aimerais obtenir plus d'informations / programmer une visite. Merci !";
$waText=rawurlencode($waMessage);
?>
<section class="terrain-detail-page">
  <div class="terrain-detail-top"><a class="back-link" href="?page=terrains">← Tous les terrains</a><span class="listing-reference">Référence <?= e($reference) ?></span></div>
  <div class="terrain-detail-heading"><div><span class="eyebrow"><?= e($land['zone_commune']) ?><?= $land['quartier']!==''?' · '.e($land['quartier']):'' ?> · ABIDJAN</span><h1><?= e($land['titre']) ?></h1><?= star_rating((float)($rating['average'] ?? 0),(int)$rating['total']) ?></div><span class="status-pill <?= e($land['statut']) ?>"><?= e(ucfirst($land['statut'])) ?></span></div>
  <section class="terrain-gallery" aria-label="Galerie photos et vidéos">
    <?php if ($media): ?><div class="terrain-gallery-tools"><?php if (count($media)>1): ?><button type="button" data-carousel-prev="terrain-gallery-track" aria-label="Média précédent">←</button><button type="button" data-carousel-next="terrain-gallery-track" aria-label="Média suivant">→</button><?php endif; ?><span><?= count($media) ?> média<?= count($media)>1?'s':'' ?></span></div><div class="terrain-gallery-track" data-carousel="terrain-gallery-track"><?php foreach ($media as $item): ?><figure class="terrain-gallery-item"><?php if ($item['type']==='video'): ?><video controls preload="metadata" src="?page=media&amp;id=<?= (int)$item['id'] ?>" aria-label="Vidéo de présentation de <?= e($land['titre']) ?>"></video><?php else: ?><img src="?page=media&amp;id=<?= (int)$item['id'] ?>" alt="<?= e($land['titre']) ?>, visuel <?= (int)$item['id'] ?>" loading="lazy"><?php endif; ?></figure><?php endforeach; ?></div><?php else: ?><div class="terrain-gallery-empty"><span class="image-placeholder"><span>SG</span></span><p>Visuels disponibles sur demande</p></div><?php endif; ?>
  </section>
  <div class="terrain-detail-layout">
    <article class="terrain-detail-main">
      <div class="terrain-key-facts"><div><span>Prix global</span><strong><?= number_format((float)$land['prix'],0,',',' ') ?> <small>FCFA</small></strong></div><div><span>Superficie exacte</span><strong><?= number_format((float)$land['superficie'],0,',',' ') ?> <small>m²</small></strong></div><div><span>Prix indicatif / m²</span><strong><?= number_format((float)$land['prix']/max((float)$land['superficie'],1),0,',',' ') ?> <small>FCFA</small></strong></div></div>
      <section class="detail-content-block"><span class="eyebrow">PRÉSENTATION</span><h2>À propos de ce bien</h2><p><?= nl2br(e($land['description'])) ?></p></section>
      <section class="detail-content-block"><span class="eyebrow">CONFORT & ENVIRONNEMENT</span><h2>Caractéristiques de la zone</h2><?php if ($features): ?><ul class="feature-check-list"><?php foreach ($features as $feature): if (isset($featureLabels[$feature])): ?><li><span aria-hidden="true">✓</span><?= e($featureLabels[$feature]) ?></li><?php endif; endforeach; ?></ul><?php else: ?><p class="muted-copy">Les caractéristiques de la zone seront précisées par notre équipe.</p><?php endif; ?></section>
      <section class="detail-content-block"><span class="eyebrow">DOCUMENTATION</span><h2>Situation juridique déclarée</h2><div class="legal-note"><strong><?= e($land['statut_juridique']) ?></strong><p>Cette information est déclarative et ne constitue pas une garantie juridique. Faites vérifier les pièces auprès des autorités et d’un professionnel indépendant avant tout paiement.</p></div></section>
      <section class="detail-content-block document-list-section"><span class="eyebrow">FICHIERS À CONSULTER</span><h2>Documents juridiques & fichiers complémentaires</h2><a class="document-download-card primary-document" href="?page=pdf&amp;id=<?= (int)$land['id'] ?>"><span class="pdf-icon">PDF</span><span><strong>Fiche terrain complète</strong><small>PDF · Générée à la demande · Photos, détails et QR code</small></span><b aria-hidden="true">↓</b></a><?php if ($documents): foreach ($documents as $document): ?><a class="document-download-card" href="?page=terrain_document&amp;id=<?= (int)$document['id'] ?>"><span class="pdf-icon">PDF</span><span><strong><?= e($document['nom_fichier']) ?></strong><small><?= number_format((int)$document['taille_fichier']/1024,0,',',' ') ?> Ko · Ajouté le <?= e(date('d/m/Y',strtotime($document['date_ajout']))) ?></small></span><b aria-hidden="true">↓</b></a><?php endforeach; else: ?><p class="muted-copy">Aucun document complémentaire n’est joint à cette fiche.</p><?php endif; ?></section>
      <section class="detail-content-block location-block"><span class="eyebrow">LOCALISATION EXACTE</span><h2><?= e($land['quartier'] ?: $land['zone_commune']) ?>, <?= e($land['zone_commune']) ?></h2><p>Coordonnées GPS : <?= e($land['latitude']) ?>, <?= e($land['longitude']) ?></p><div id="detail-map" class="property-map" data-lat="<?= e($land['latitude']) ?>" data-lng="<?= e($land['longitude']) ?>" data-title="<?= e($land['titre']) ?>"></div></section>
    </article>
    <aside class="terrain-contact-panel glass-filter"><span class="eyebrow">VOTRE PROJET</span><h2>Organisons une visite.</h2><p>Un conseiller SG Services vous recontactera au sujet du bien <strong><?= e($reference) ?></strong>.</p><form method="post" action="?page=terrain&amp;id=<?= (int)$land['id'] ?>"><?= csrf_field() ?><input type="hidden" name="terrain_id" value="<?= (int)$land['id'] ?>"><label>Votre nom<input name="nom" required maxlength="150" autocomplete="name"></label><label>Téléphone / WhatsApp<input name="contact" required maxlength="80" autocomplete="tel"></label><label>Votre message<textarea name="message" required maxlength="4000" rows="4">Je souhaite réserver une visite pour <?= e($land['titre']) ?> (<?= e($reference) ?>).</textarea></label><button class="button button-green full-width">Réserver une visite <span>↗</span></button></form><a class="button whatsapp-property" href="https://wa.me/2250711850139?text=<?= $waText ?>" target="_blank" rel="noopener noreferrer">Discuter sur WhatsApp <span>↗</span></a><p class="contact-reference">Référence : <?= e($reference) ?></p></aside>
  </div>
</section>