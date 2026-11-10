<?php
$heroImages = db()->query('SELECT id,titre,texte_alternatif,chemin_fichier,position,actif FROM images_hero ORDER BY position,id')->fetchAll();
$projects = db()->query("SELECT r.*,AVG(a.note) AS rating_avg,COUNT(a.id) AS rating_count FROM realisations r LEFT JOIN avis_terrains a ON a.realisation_id=r.id AND a.statut='publie' GROUP BY r.id ORDER BY r.date_creation DESC LIMIT 50")->fetchAll();
?>
<div class="admin-shell admin-addons" id="home-media">
  <section class="admin-section home-media-admin">
    <div class="admin-section-title"><div><span class="eyebrow">CONTENU DE LA PAGE D’ACCUEIL</span><h2>Diaporama hero</h2></div></div>
    <form class="media-upload-form" method="post" enctype="multipart/form-data">
      <?= csrf_field() ?><input type="hidden" name="action" value="save_hero_image">
      <label>Titre<input name="titre" maxlength="140" required></label>
      <label>Texte alternatif<input name="texte_alternatif" maxlength="180" required></label>
      <label>Image JPG / PNG<input type="file" name="image" accept="image/jpeg,image/png,.jpg,.jpeg,.png" required><small>8 Mo maximum · 6000 px par côté.</small></label>
      <button class="button button-green">Ajouter au hero <span>↗</span></button>
    </form>
    <?php if ($heroImages): ?><div class="home-media-grid"><?php foreach ($heroImages as $image): ?>
      <article class="home-media-card">
        <img src="?page=visual&amp;kind=hero&amp;id=<?= (int)$image['id'] ?>&amp;v=<?= e(substr($image['chemin_fichier'],0,8)) ?>" alt="<?= e($image['texte_alternatif']) ?>">
        <div><strong><?= e($image['titre']) ?></strong><span class="status-pill <?= $image['actif']?'disponible':'masque' ?>"><?= $image['actif']?'Affiché':'Masqué' ?></span></div>
        <form class="hero-edit-form" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="update_hero_image"><input type="hidden" name="id" value="<?= (int)$image['id'] ?>"><label>Titre<input name="titre" maxlength="140" required value="<?= e($image['titre']) ?>"></label><label>Texte alternatif<input name="texte_alternatif" maxlength="180" required value="<?= e($image['texte_alternatif']) ?>"></label><label>Ordre<input type="number" min="0" max="999" name="position" value="<?= (int)$image['position'] ?>" required></label><button class="button button-outline">Enregistrer</button></form>
        <form class="hero-replace-form" method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="action" value="replace_hero_image"><input type="hidden" name="id" value="<?= (int)$image['id'] ?>"><label>Remplacer l’image<input type="file" name="image" accept="image/jpeg,image/png,.jpg,.jpeg,.png" required></label><button class="button button-outline">Remplacer</button></form>
        <div class="home-media-actions"><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="hero_image_status"><input type="hidden" name="id" value="<?= (int)$image['id'] ?>"><button class="button button-outline"><?= $image['actif']?'Masquer':'Afficher' ?></button></form><form method="post" onsubmit="return confirm('Supprimer ce visuel ?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete_hero_image"><input type="hidden" name="id" value="<?= (int)$image['id'] ?>"><button class="danger-link">Supprimer</button></form></div>
      </article>
    <?php endforeach; ?></div><?php else: ?><p class="admin-empty">Aucun visuel personnalisé. Le hero garde son image de couverture intégrée.</p><?php endif; ?>
  </section>
  <section class="admin-section home-media-admin">
    <div class="admin-section-title"><div><span class="eyebrow">PORTFOLIO</span><h2>Projets réalisés</h2></div></div>
    <form class="media-upload-form project-upload-form" method="post" enctype="multipart/form-data">
      <?= csrf_field() ?><input type="hidden" name="action" value="save_project">
      <label>Titre<input name="titre" maxlength="160" required></label><label>Catégorie<input name="categorie" maxlength="120" placeholder="Ex. Construction résidentielle" required></label>
      <label class="media-span">Description<textarea name="description" maxlength="1200" rows="3" required></textarea></label>
      <label class="media-span">Image JPG / PNG<input type="file" name="image" accept="image/jpeg,image/png,.jpg,.jpeg,.png" required><small>8 Mo maximum · 6000 px par côté.</small></label>
      <button class="button button-green">Ajouter le projet <span>↗</span></button>
    </form>
    <?php if ($projects): ?><div class="home-media-grid project-admin-grid"><?php foreach ($projects as $project): ?>
      <article class="home-media-card"><img src="?page=visual&amp;kind=project&amp;id=<?= (int)$project['id'] ?>" alt="<?= e($project['titre']) ?>"><div><strong><?= e($project['titre']) ?></strong><small><?= e($project['categorie']) ?></small><?= star_rating((float)($project['rating_avg'] ?? 0),(int)$project['rating_count']) ?><span class="status-pill <?= $project['actif']?'disponible':'masque' ?>"><?= $project['actif']?'Affiché':'Masqué' ?></span></div><div class="home-media-actions"><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="project_status"><input type="hidden" name="id" value="<?= (int)$project['id'] ?>"><button class="button button-outline"><?= $project['actif']?'Masquer':'Afficher' ?></button></form><form method="post" onsubmit="return confirm('Supprimer ce projet ?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete_project"><input type="hidden" name="id" value="<?= (int)$project['id'] ?>"><button class="danger-link">Supprimer</button></form></div></article>
    <?php endforeach; ?></div><?php else: ?><p class="admin-empty">Aucune réalisation publiée. Ajoutez vos projets pour les présenter sur la page d’accueil.</p><?php endif; ?>
  </section>
</div>