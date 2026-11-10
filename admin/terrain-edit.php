<?php
require __DIR__ . '/check-auth.php';
$editId=isset($_GET['new'])?null:filter_input(INPUT_GET,'edit',FILTER_VALIDATE_INT);
$land=null;
if ($editId) {
    $landStmt=db()->prepare('SELECT * FROM terrains WHERE id=:id');
    $landStmt->execute(['id'=>(int)$editId]);
    $land=$landStmt->fetch();
    if (!$land) { http_response_code(404); echo '<section class="page-intro"><h1>Terrain introuvable</h1><a href="?page=admin">Retour à l’administration</a></section>'; return; }
}
$documents=[];
if ($land) {
    $documentStmt=db()->prepare('SELECT id,nom_fichier,taille_fichier,date_ajout FROM documents_terrains WHERE terrain_id=:id ORDER BY date_ajout DESC');
    $documentStmt->execute(['id'=>$land['id']]);
    $documents=$documentStmt->fetchAll();
}
$selectedFeatures=json_decode((string)($land['caracteristiques'] ?? '[]'),true);
$selectedFeatures=is_array($selectedFeatures)?$selectedFeatures:[];
$featureOptions=['eau'=>'Eau','electricite'=>'Électricité','route'=>'Accès route','non_inondable'=>'Zone non inondable','ecoles'=>'Proximité écoles','transports'=>'Proximité transports','assainissement'=>'Assainissement','lotissement_cloture'=>'Lotissement clôturé'];
?>
<section class="admin-shell terrain-edit-shell">
  <div class="admin-head"><div><span class="eyebrow">CATALOGUE · ÉDITION DE FICHE</span><h1><?= $land?'Modifier le terrain':'Ajouter un terrain' ?></h1><p>Les documents PDF restent privés et ne sont accessibles que depuis la fiche concernée.</p></div><a class="button button-outline" href="?page=admin">← Retour au tableau de bord</a></div>
  <form class="terrain-edit-form" action="?page=admin" method="post" enctype="multipart/form-data">
    <?= csrf_field() ?><input type="hidden" name="action" value="save_land"><input type="hidden" name="id" value="<?= (int)($land['id'] ?? 0) ?>">
    <section class="admin-section"><h2>Informations du bien</h2><div class="terrain-form-grid">
      <label>Référence unique<input value="<?= e($land['reference'] ?? 'Générée à la création') ?>" readonly></label>
      <label>Titre<input name="titre" maxlength="180" required value="<?= e($land['titre'] ?? '') ?>"></label>
      <label>Type<select name="type_bien"><option value="terrain" <?= ($land['type_bien'] ?? 'terrain')==='terrain'?'selected':'' ?>>Terrain</option><option value="maison" <?= ($land['type_bien'] ?? '')==='maison'?'selected':'' ?>>Maison</option><option value="lot_loti" <?= ($land['type_bien'] ?? '')==='lot_loti'?'selected':'' ?>>Lot loti</option></select></label>
      <label>Commune<input name="zone_commune" maxlength="120" required value="<?= e($land['zone_commune'] ?? '') ?>"></label>
      <label>Quartier<input name="quartier" maxlength="120" value="<?= e($land['quartier'] ?? '') ?>"></label>
      <label>Prix global (FCFA)<input type="number" min="0" step="1" name="prix" required value="<?= e($land['prix'] ?? '') ?>"></label>
      <label>Superficie exacte (m²)<input type="number" min="0.01" step="0.01" name="superficie" required value="<?= e($land['superficie'] ?? '') ?>"></label>
      <label>Statut<select name="statut"><option value="disponible" <?= ($land['statut'] ?? 'disponible')==='disponible'?'selected':'' ?>>Disponible</option><option value="reserve" <?= ($land['statut'] ?? '')==='reserve'?'selected':'' ?>>Réservé</option><option value="vendu" <?= ($land['statut'] ?? '')==='vendu'?'selected':'' ?>>Vendu</option></select></label>
      <label>Statut juridique<input name="statut_juridique" list="legal-types" maxlength="100" required value="<?= e($land['statut_juridique'] ?? 'À vérifier') ?>"><datalist id="legal-types"><option value="Titre Foncier"><option value="ACD"><option value="Attestation Villageoise"><option value="Comprouis"><option value="À vérifier"></datalist></label>
      <label>Latitude<input type="number" step="0.0000001" min="-90" max="90" name="latitude" required value="<?= e($land['latitude'] ?? '5.3600') ?>"></label>
      <label>Longitude<input type="number" step="0.0000001" min="-180" max="180" name="longitude" required value="<?= e($land['longitude'] ?? '-4.0083') ?>"></label>
      <fieldset class="feature-checkboxes"><legend>Caractéristiques et avantages</legend><?php foreach ($featureOptions as $key=>$label): ?><label><input type="checkbox" name="caracteristiques[]" value="<?= e($key) ?>" <?= in_array($key,$selectedFeatures,true)?'checked':'' ?>><?= e($label) ?></label><?php endforeach; ?></fieldset>
      <label class="form-wide">Description détaillée<textarea name="description" maxlength="12000" rows="7" required><?= e($land['description'] ?? '') ?></textarea></label>
      <label class="form-wide">Ajouter des photos / vidéos<input type="file" name="photos[]" accept=".jpg,.jpeg,.png,.mp4" multiple><small>JPG, PNG ou MP4, 8 Mo maximum par fichier.</small></label>
    </div></section>
    <div class="terrain-edit-actions"><button class="button button-green"><?= $land?'Enregistrer les modifications':'Créer la fiche terrain' ?> <span>↗</span></button><a class="button button-outline" href="?page=admin">Annuler</a></div>
  </form>
  <?php if ($land): ?><section class="admin-section"><h2>Documents juridiques & fichiers complémentaires</h2><?php if ($documents): ?><div class="admin-document-list"><?php foreach ($documents as $document): ?><div class="admin-document-row"><span class="pdf-icon">PDF</span><span><strong><?= e($document['nom_fichier']) ?></strong><small><?= number_format((int)$document['taille_fichier']/1024,0,',',' ') ?> Ko · <?= e(date('d/m/Y',strtotime($document['date_ajout']))) ?></small></span><a href="?page=terrain_document&amp;id=<?= (int)$document['id'] ?>">Télécharger</a><form method="post" action="?page=admin" onsubmit="return confirm('Supprimer ce document ?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete_property_document"><input type="hidden" name="terrain_id" value="<?= (int)$land['id'] ?>"><input type="hidden" name="document_id" value="<?= (int)$document['id'] ?>"><button class="danger-link">Supprimer</button></form></div><?php endforeach; ?></div><?php else: ?><p class="muted-copy">Aucun document complémentaire ajouté.</p><?php endif; ?>
    <form class="document-upload-label" method="post" action="?page=admin" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="action" value="add_property_documents"><input type="hidden" name="terrain_id" value="<?= (int)$land['id'] ?>"><label>Téléverser plusieurs PDF<input type="file" name="documents[]" accept="application/pdf,.pdf" multiple required></label><small>PDF uniquement · 8 Mo maximum par fichier · 24 Mo par envoi. Le nom lisible est repris du nom du fichier choisi.</small><button class="button button-green">Ajouter les documents</button></form>
  </section><?php endif; ?>
</section>