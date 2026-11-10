# SG Services International

Plateforme PHP vanilla / MySQL pour la publication de terrains, les demandes clients et les propositions de vendeurs.

## Arborescence

```text
premierApp/
├── app/                 # Contrôles communs, sécurité, PDF
├── assets/              # CSS et JavaScript
├── config/              # Connexion PDO
├── database/schema.sql  # Schéma MySQL
├── scripts/             # Outils CLI d’administration
├── views/               # Vues publiques et administration
├── index.php            # Routeur léger
└── composer.json        # Dompdf et QR code
```

Les médias sont enregistrés par défaut dans `C:\wamp64\premierapp-storage`, hors de la racine web. En production, définissez `APP_STORAGE_PATH` vers un répertoire non servi par le serveur web et protégé par des sauvegardes.

## Installation locale (WAMP)

1. Démarrez Apache et MySQL, puis importez `database/schema.sql` dans phpMyAdmin. Pour une base créée avant les évolutions, exécutez une seule fois `database/migrations/20261110_type_bien.sql`, `database/migrations/20261110_home_visuals.sql`, `database/migrations/20261110_terrain_documents.sql` et `database/migrations/20261110_alert_subscribers.sql`. La configuration locale par défaut utilise `127.0.0.1`, base `sg_services`, utilisateur `root` sans mot de passe.
2. Installez Composer, puis lancez `composer install` à la racine du projet pour activer le PDF Dompdf et son QR code.
3. Créez le premier compte depuis un terminal dans le projet : `C:\wamp64\bin\php\php8.2.29\php.exe scripts\create_admin.php`. Par défaut, le compte est `admin@sgservices.ci`; le script demande un nom et un mot de passe (12 caractères minimum), qui est haché avec `password_hash()`. Aucun mot de passe par défaut ni formulaire d’inscription admin n’est fourni. Pour personnaliser l’adresse et le nom, passez-les en arguments.
4. Ouvrez `http://localhost/premierApp/`. Modifiez les coordonnées MySQL avec les variables d’environnement `DB_HOST`, `DB_NAME`, `DB_USER` et `DB_PASSWORD` si nécessaire.

Sur Apache, activez les extensions PHP `pdo_mysql`, `fileinfo`, `mbstring` et `gd`. Ajustez `upload_max_filesize` à au moins `8M` et `post_max_size` à `25M`. Pour les URL du QR code, configurez `APP_URL` (par exemple `http://localhost/premierApp`); elle est obligatoire en production afin d’éviter la confiance dans l’en-tête Host entrant. Le `.htaccess` suppose Apache avec `AllowOverride All`; si ce n'est pas activé, définissez les refus d'accès aux répertoires privés dans la configuration du VirtualHost.

Les alertes e-mail utilisent `mail()` et `ALERT_FROM_EMAIL` (par exemple `notifications@votredomaine.ci`). Configurez un relais SMTP fiable côté serveur pour la production. La fonction renvoie les compteurs d’envoi/échec; un message WhatsApp prérempli est proposé au tableau de bord sans envoi massif automatique.

## Sécurité et limites de déploiement

- Les pages utilisent PDO préparé, échappement HTML, jetons CSRF, contrôle de rôle, limitation simple des essais de connexion et validation MIME des fichiers.
- En production, imposez HTTPS, configurez les variables d’environnement côté serveur, désactivez l’affichage des erreurs PHP et choisissez un stockage média hors racine web. La configuration Secure du cookie s’active automatiquement lorsque la requête est HTTPS.
- Les fichiers envoyés par des vendeurs restent dans le répertoire privé `submissions`. Les données de contact doivent être protégées et conservées selon la réglementation applicable.
- Les images du logo, du hero et des réalisations sont conservées dans `branding`, `hero` et `projects` hors racine web. Seuls les visuels actifs sont servis publiquement; l’administration peut prévisualiser les visuels masqués.
- Les informations de titre foncier sont des déclarations et ne valent jamais certification. Les points GPS de conversion d’une soumission sont provisoires et doivent être remplacés par des coordonnées vérifiées avant publication.
- Avant ouverture au public, ajoutez des sauvegardes testées, une supervision, des tests d’intégration, une politique de confidentialité et une protection anti-spam adaptée. Aucun logiciel ne peut être garanti « invulnérable ».

Les tuiles de carte, Leaflet et les polices web sont chargées depuis des fournisseurs externes. Prévoir une politique de confidentialité et une configuration CSP adaptée au domaine de déploiement.