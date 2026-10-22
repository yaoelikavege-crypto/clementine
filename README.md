# Clémentine · 21 ans

> Site d'anniversaire privé, romantique et scénarisé, pour les 21 ans de **Clémentine** — dimanche **23 novembre 2026**, minuit, heure du Togo (`Africa/Lome`).
> Écrit et signé par **Joël**. Conception & développement : **KavegeElis** — marque **NovaTechX**.

![Statut](https://img.shields.io/badge/statut-prêt%20à%20déployer-7957FF) ![PHP](https://img.shields.io/badge/PHP-8.0%2B-FF6FB5) ![MySQL](https://img.shields.io/badge/MySQL%20%2F%20MariaDB-5.7%2B-C9B8FF) ![Dépendances](https://img.shields.io/badge/dépendances-aucune-4ADE80)

---

## 1. Ce que contient le projet

Une expérience en cinq temps, entièrement en français, sans framework, sans CDN, sans dépendance externe :

| Étape | Page | Ce que Clémentine découvre |
| --- | --- | --- |
| 1. L'attente | `index.php` | Compte à rebours géant, messages qui se dévoilent au fil des jours, musique d'ambiance |
| 2. La salle d'attente | `pages/countdown.php` | Le même compte à rebours, réservé aux personnes connectées avant minuit |
| 3. Les souvenirs | `pages/memories.php` | Galerie en cartes, visionneuse plein écran (photos, vidéos locales et YouTube/Vimeo) |
| 4. La lettre | `pages/letter.php` | Lettre d'amour mise en page, puis les vœux de ses proches |
| 5. La bougie | `pages/finale.php` | Une bougie « 21 » à souffler, pluie d'étincelles, « Joyeux anniversaire Clémentine » |

Et un espace d'administration complet (10 écrans) pour tout piloter sans toucher au code.

---

## 2. Fonctionnalités

### Côté public
- **Compte à rebours** synchronisé sur l'heure du serveur (jamais sur l'horloge du navigateur), avec resynchronisation toutes les 60 secondes et bascule automatique à minuit.
- **Musique** : lecteur unique par page, reprise du volume, bouton d'activation manuel si le navigateur bloque la lecture automatique, playlist propre à chaque étape.
- **Galerie** avec filtres par catégorie, visionneuse accessible au clavier (flèches, Échap) et balayage tactile sur mobile.
- **Lettre** et **vœux** avec mise en page typographique soignée.
- **Finale** : animation de bougie 100 % CSS/JS, étincelles sur `<canvas>`, respect de `prefers-reduced-motion`.
- **Design** : bleu nuit, violet, rose néon, lavande ; aurores animées, champ d'étoiles, verre dépoli ; responsive mobile / tablette / ordinateur.
- **Accessibilité** : lien d'évitement, contrastes vérifiés, focus visibles, libellés ARIA, textes alternatifs.

### Côté administration
| Écran | Rôle |
| --- | --- |
| `admin/dashboard.php` | Vue d'ensemble : état du site, J-xx, checklist de préparation, derniers ajouts, journal d'activité |
| `admin/memories.php` | Créer, modifier, ordonner, publier ou masquer les souvenirs |
| `admin/media.php` | Bibliothèque photos / vidéos / musiques : import, miniature, protection, suppression |
| `admin/playlists.php` | Composer les 4 ambiances (5 pistes actives maximum chacune) |
| `admin/letter.php` | Écrire la lettre, la publier ou la garder en brouillon |
| `admin/wishes.php` | Gérer les vœux d'anniversaire |
| `admin/countdown.php` | Programmer les messages et indices de l'attente |
| `admin/credentials.php` | Changer son mot de passe, réinitialiser l'autre administrateur, modifier l'accès visiteur |
| `admin/settings.php` | Textes du site, catégories, mode maintenance, limites d'hébergement |
| `admin/index.php` | Redirection vers le tableau de bord |

**Deux comptes administrateurs indépendants** (Joël et le développeur), **pouvoirs strictement identiques**, plus **un mot de passe commun** pour Clémentine et ses proches. Le rôle réel est décidé **par le serveur** : le choix « Administrateur / Visiteur » de la page de connexion n'est qu'une préférence d'affichage.

---

## 3. Règles de sécurité appliquées

- Date de révélation **fixe**, centralisée dans `includes/config.php` (`app.reveal_at`) et **non modifiable depuis le tableau de bord**, même par un administrateur.
- Avant minuit, un **visiteur conecté** ne peut ouvrir ni les souvenirs, ni la lettre, ni la finale : le serveur le redirige. Un **administrateur**, lui, peut tout préparer et prévisualiser (bandeau jaune « Aperçu administrateur »).
- **Médias protégés jamais servis en clair** : `/uploads` est fermé par `.htaccess` ; les fichiers passent par `media.php`, qui vérifie session + date + protection avant d'envoyer le flux (support des requêtes `Range` pour la vidéo).
- Mots de passe hachés avec `password_hash()` (bcrypt), vérifiés avec `password_verify()`, jamais journalisés.
- **CSRF** sur tous les formulaires POST (jeton par session, erreur 419 en cas d'absence).
- **Limitation des tentatives** : 5 échecs / 15 min pour l'administration, 8 / 15 min pour l'accès visiteur, par adresse IP pseudonymisée (`privacy_salt`).
- **Sessions** : cookie `HttpOnly`, `SameSite=Lax`, `Secure` automatique en HTTPS, régénération d'identifiant à la connexion, expiration après 45 min d'inactivité et 12 h maximum.
- **Uploads** : type MIME réel via `finfo`, images ré-encodées par GD (métadonnées EXIF supprimées, 2 200 px max), noms de fichiers aléatoires, répertoires en `engine off` (aucun PHP exécutable).
- **En-têtes** : CSP stricte, `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, `X-Robots-Tag: noindex`.
- **Aucun secret dans le code livré** : `includes/config.example.php` contient des marqueurs à remplacer, jamais de vrai mot de passe.

---

## 4. Prérequis

| Élément | Minimum | Recommandé |
| --- | --- | --- |
| PHP | 8.0 | 8.2 / 8.3 |
| Extensions | `pdo_mysql`, `mbstring`, `gd`, `fileinfo`, `json`, `openssl` | idem |
| Base de données | MySQL 5.7 / MariaDB 10.3 | MariaDB 11 |
| Espace disque | 60 Mo + médias | — |
| Serveur web | Apache (`.htaccess`) ou Nginx (bloc `location` fourni) | Apache |
| HTTPS | recommandé | obligatoire pour un envoi serein des mots de passe |

Aucune ligne de commande, aucun Composer, aucun Node.js n'est nécessaire en production.

---

## 5. Installation en 6 étapes

> Le détail pas à pas pour InfinityFree (avec captures d'écran mentales et pièges) se trouve dans **[DEPLOYMENT.md](DEPLOYMENT.md)**.

### 5.1 Décompresser
Envoyez le contenu de l'archive **à la racine du site** (`htdocs/` chez InfinityFree) **ou dans un sous-dossier** (par exemple `htdocs/clementine/`) : le site détecte tout seul son adresse d'installation.

> **Plus rien à régler à la main.** `base_path()` (`includes/helpers.php`) compare le chemin réel du projet à la racine web et en déduit le préfixe d'URL. C'est ce qui évite le symptôme « la page s'affiche mais sans CSS, sans JS, sans image », typique d'une installation en sous-dossier avec un `base_url` vide mal interprété. L'option `app.base_url` reste disponible si vous voulez forcer une valeur.

### 5.2 Créer le fichier de configuration
1. Dupliquez `includes/config.example.php` en `includes/config.php`.
2. Renseignez vos identifiants MySQL.
3. Remplacez `install_key` et `privacy_salt` par deux longues chaînes aléatoires (40 caractères ou plus).
4. Laissez `debug` à `false` et `reveal_at` à `2026-11-23 00:00:00`.

```bash
# Proposition de valeurs aléatoires (sur votre ordinateur, pas sur le serveur)
php -r "echo bin2hex(random_bytes(24)), PHP_EOL, bin2hex(random_bytes(32)), PHP_EOL;"
```

### 5.3 Importer la base
Deux méthodes, au choix :
- **A. phpMyAdmin** : importez `sql/install.sql` (structure + contenus de départ).
- **B. Assistant** : passez à l'étape 5.4, il crée lui-même les 12 tables.

### 5.4 Lancer l'assistant
Ouvrez `https://votre-domaine/setup/index.php` :

1. **Autorisation** — saisissez la clé `install_key`.
2. **Vérifications** — version de PHP, extensions, droits d'écriture, HTTPS. Corrigez tout point rouge bloquant.
3. **Base de données** — un clic crée les 12 tables **et, automatiquement, les comptes de première connexion** définis dans `includes/config.php` (`app.default_accounts`) :
   - **Joël** — identifiant `joel`, mot de passe `j123456789@`
   - **KavegeElis** — identifiant `elis`, mot de passe `e123456789@`
   - **Visiteur Clémentine** — mot de passe `c123456789@`

   Aucune saisie n'est demandée : cette étape ne peut donc plus bloquer. Le formulaire manuel reste disponible dans un repli « Créer un compte à la main ».
4. **Finalisation** — l'assistant écrit `storage/installed.lock` et bascule `site_settings.installed = 1`.

> **À faire dès la première connexion** : changer les trois mots de passe dans **Administration → Mots de passe**. Tant qu'un mot de passe par défaut est actif, un bandeau orange le rappelle sur le tableau de bord ; il disparaît tout seul une fois les trois changés.

Une fois verrouillé, l'assistant renvoie une page 403 : il ne peut plus être rejoué.

### 5.5 Vérifier les droits d'écriture
`uploads/` et `storage/` doivent être inscriptibles (`755` sur la plupart des hébergements, `775` si le serveur web n'est pas le propriétaire).

### 5.6 Se connecter et préparer
`https://votre-domaine/login.php` → onglet **Administrateur**. Importez photos, musiques et vidéos, composez les playlists, écrivez la lettre, publiez les vœux.

---

## 6. Comptes et accès

| Accès | Qui | Identifiant | Mot de passe de première connexion |
| --- | --- | --- | --- |
| Administrateur | Joël | `joel` | `j123456789@` |
| Administrateur | KavegeElis (développeur) | `elis` | `e123456789@` |
| Visiteur | Clémentine et ses proches | *(aucun)* | `c123456789@` |

Les deux comptes administrateurs ont **exactement les mêmes droits**. Les trois mots de passe sont **modifiables dans l'administration** (voir 6.1) et peuvent être remplacés d'avance dans `includes/config.php` avant l'installation.

### 6.1 Modifier les comptes depuis l'administration
Menu **Mots de passe** (`admin/credentials.php`) :

| Action | Effet |
| --- | --- |
| Mon profil | Change l'identifiant et le nom affiché du compte connecté |
| Changer mon mot de passe | Remplace le mot de passe (10 caractères min., lettres + chiffres, pas de mot évident) |
| Réinitialiser l'autre compte | Génère un mot de passe temporaire pour l'autre administrateur, affiché **une seule fois** |
| Compte administrateur (autre) | Change le nom affiché de l'autre administrateur |
| Mot de passe commun | Change le mot de passe unique des visiteurs |

Chaque enregistrement met à jour le drapeau `default_passwords_active` dans `site_settings` : le bandeau d'alerte du tableau de bord s'éteint dès que plus aucun mot de passe par défaut n'est utilisé.

- Un visiteur **avant** le 23 novembre 2026 à minuit : accès à la salle d'attente uniquement.
- Un visiteur **après** minuit : souvenirs, lettre, finale.
- Un administrateur : tout, tout le temps (prévisualisation).

---

## 7. Structure des dossiers

```
/
├── index.php                  Accueil public (compte à rebours, teasers)
├── login.php                  Connexion à deux profils
├── logout.php                 Déconnexion
├── media.php                  Diffuseur de médias protégés (session + date + Range)
├── router.php                 Routeur pour `php -S` (développement seulement)
├── robots.txt                 Indexation interdite
├── .htaccess                  Sécurité, erreurs, cache, compression
│
├── admin/                     10 écrans d'administration
├── actions/                   9 contrôleurs POST (CSRF + redirections + messages)
├── api/time.php               Heure serveur (synchronisation du compte à rebours)
├── assets/
│   ├── css/main.css           Feuille publique (tokens, héros, galerie, bougie…)
│   ├── css/admin.css          Feuille d'administration
│   ├── js/core.js             Navigation, confirmations, compteurs, étoiles
│   ├── js/countdown.js        Compte à rebours synchronisé
│   ├── js/player.js           Lecteur audio unique
│   ├── js/gallery.js          Visionneuse
│   ├── js/candle.js           Bougie et étincelles
│   ├── js/admin.js            Menu latéral mobile, bascule vidéo locale/externe
│   ├── images/favicon.svg     Icône du site
│   └── audio/README.txt       Aucune musique livrée (droits à vérifier)
├── errors/                    Pages 400, 403, 404, 405, 419, 429, 500, 503
├── includes/                  Cœur applicatif (14 fichiers PHP)
│   ├── config.example.php     Modèle de configuration
│   ├── schema.php             Source unique du schéma SQL
│   ├── auth.php  csrf.php  helpers.php  settings.php  database.php
│   ├── media_library.php  layout.php  admin_layout.php  icons.php
│   ├── installer.php  errors.php  bootstrap.php
│   └── .htaccess
├── pages/                     countdown.php · memories.php · letter.php · finale.php
├── setup/index.php            Assistant d'installation (verrouillable)
├── sql/install.sql            Script SQL généré depuis includes/schema.php
├── storage/                   Journaux + verrou d'installation (non public)
├── tools/build_sql.php        Régénère sql/install.sql (CLI)
├── uploads/                   Médias (aucun accès direct : .htaccess + engine off)
└── README.md · DEPLOYMENT.md · TESTS.md
```

---

## 8. Administration au quotidien

### Ajouter un souvenir
1. **Photos / Vidéos** → importez le fichier (titre obligatoire).
2. **Souvenirs & galerie** → « Créer un souvenir », choisissez le média, la catégorie et la date.
3. L'ordre d'affichage suit le champ « Ordre » (0 = en premier), ajustable aussi avec les flèches.

### Composer une ambiance
**Playlists** → choisissez la piste → « Ajouter ». Cinq pistes actives maximum par ambiance (limite volontaire, modifiable via `app.playlist_max_tracks`).
Une piste **protégée** ne sera pas diffusée avant la révélation : pour la musique d'attente, décochez « Protéger jusqu'à la révélation » sur le média.

### Programmer un message d'attente
**Messages & indices** → titre facultatif, texte, date de dévoilement (heure du Togo). Laissez la date vide pour un message visible immédiatement.

### Changer un mot de passe
**Mots de passe** → « Changer mon mot de passe » (l'ancien est demandé). En cas d'oubli de l'autre administrateur, utilisez « Réinitialiser l'autre compte » : un mot de passe temporaire s'affiche **une seule fois**.

### Mode maintenance
**Paramètres généraux** → cochez la case. Les visiteurs voient une page 503 expliquant que le site se prépare ; les administrateurs conservent l'accès.

---

## 9. Récupération d'accès (procédure d'urgence)

Si les deux mots de passe administrateur sont perdus :

1. Connectez-vous à **phpMyAdmin** (VistaPanel → MySQL Databases).
2. Générez un hachage bcrypt sur votre ordinateur :
   ```bash
   php -r "echo password_hash('MonNouveauMotDePasse!2026', PASSWORD_DEFAULT), PHP_EOL;"
   ```
3. Exécutez dans phpMyAdmin :
   ```sql
   UPDATE admins
   SET password_hash = '$2y$10$VOTRE_HACHAGE_ICI', is_active = 1
   WHERE username = 'joel';
   ```
4. Connectez-vous immédiatement, puis changez le mot de passe depuis **Mots de passe**.
5. Vérifiez le journal (`storage/logs/app.log`) et le tableau de bord (activité récente) pour détecter toute connexion suspecte.

> Ne stockez jamais le mot de passe en clair dans un fichier du site. Ne désactivez pas `password_verify()` pour « tester ».

---

## 10. Personnalisation

| Besoin | Où |
| --- | --- |
| Textes de l'accueil, titres, signatures | **Paramètres généraux** (base de données) |
| Date de révélation | `includes/config.php` → `app.reveal_at` (volontairement fixe) |
| Couleurs, rayons, ombres | `assets/css/main.css` → bloc `:root` (jetons de design) |
| Limites d'upload | `includes/config.php` → `media.max_*_mb` |
| Pistes actives par playlist | `includes/config.php` → `app.playlist_max_tracks` |
| Fuseau horaire | `includes/config.php` → `app.timezone` |
| WhatsApp du développeur | `includes/config.php` → `app.developer_whatsapp` |

---

## 11. Limites de l'hébergement gratuit (InfinityFree, constat 2026)

| Contrainte | Valeur | Conséquence sur le projet |
| --- | --- | --- |
| Taille par fichier | **10 Mo** | Configuré à 8 Mo pour la vidéo et l'audio, 6 Mo pour les photos |
| Fichiers HTML/PHP/JS | **1 Mo** | Aucun fichier du projet n'approche cette taille |
| `.htaccess` | 10 ko | Les nôtres font moins de 3 ko |
| `post_max_size` | ≈ 30 Mo | Un seul fichier par envoi : c'est le cas |
| Stockage | 5 Go | Largement suffisant |
| Inodes | 30 000 | Un média = 1 à 2 fichiers ; le projet en utilise ~90 |
| SSH | indisponible | Tout passe par FTP, phpMyAdmin et l'interface web |

**Une vidéo lourde ne doit pas être hébergée** : utilisez le champ « Lien externe » (YouTube non-cookie ou Vimeo). Le média est alors intégré ou remplacé par une miniature + lien direct si l'intégration est refusée.

---

## 12. Sauvegarde et restauration

**Sauvegarder**
1. `includes/config.php` (à conserver hors du serveur, c'est un secret).
2. Export SQL via phpMyAdmin (« Exporter » → SQL).
3. Dossier `uploads/` par FTP.

**Restaurer**
1. Remettre les fichiers en place.
2. Réimporter le SQL.
3. Rétablir `includes/config.php`.
4. Supprimer `storage/installed.lock` **uniquement** si vous restaurez une base vierge que vous voulez réinstaller.

---

## 13. Tests

- Liste complète des vérifications exécutables : **[TESTS.md](TESTS.md)**.
- **Dernière campagne : 316 tests, 316 réussis** (22 octobre 2026), sur **PHP 8.4.27 + MariaDB 11.8.6**, sous **Apache 2.4.69 avec `mod_php`** (le plus proche d'InfinityFree), avec **quatre installations** : racine du domaine, sous-dossier `/clementine/`, installation « jour J » (révélation passée) et archive livrée extraite dans une base neuve.
- Tous les liens internes (`href`, `src`, `action`) ont été visités automatiquement : aucune cible manquante, aucun chemin sans préfixe, sur les trois installations.
- Le parcours complet a été rejoué pour de vrai : téléversements, souvenirs, playlists, lettre, vœux, messages, paramètres, connexions des deux administrateurs et du visiteur, médias protégés, XSS stocké vérifié dans Chromium.
- Les scripts sont fournis dans `tests/` (`run_all.sh` enchaîne les dix phases) si vous voulez rejouer la vérification.
- Les résultats réellement observés sont consignés dans `TESTS.md` ; rien n'y est déclaré « testé » sans avoir été exécuté.

---

## 14. Dépannage express

| Symptôme | Cause probable | Correctif |
| --- | --- | --- |
| La page s'affiche **sans CSS, sans JS, sans images** | Préfixe d'URL mal détecté | Vérifiez que `includes/helpers.php` est bien la version livrée ; laissez `app.base_url` **vide**. En dernier recours, forcez `'base_url' => '/clementine'` (sans slash final). |
| `Erreur 403` sur `/setup/index.php` | Installation déjà verrouillée | Supprimez `storage/installed.lock` **et** passez `site_settings.installed` à `0` (phpMyAdmin) pour rejouer l'assistant. |
| « Erreur lors de la connexion à la base » | Identifiants MySQL | Hôte `sql211.infinityfree.com`, base `if0_43112497_clementine`, utilisateur `if0_43112497`, port `3306`. |
| Upload refusé au-delà d'1 Mo | Fichier PHP/HTML/JS, pas un média | Les médias acceptent jusqu'à 6 Mo (image), 8 Mo (vidéo/audio). Les fichiers `.php` de plus d'1 Mo sont refusés par InfinityFree lui-même. |
| Le compte à rebours affiche autre chose que minuit | Fuseau horaire | `app.timezone` doit rester `Africa/Lome` ; `reveal_at` ne doit jamais être modifié. |
| Page « Session expirée » alors que la session est neuve | Jetons CSRF d'un formulaire ouvert trop longtemps | Rechargez la page et recommencez l'action. |
| « Action non autorisée » (405) en ouvrant une page | Un formulaire a été renvoyé sur une page d'affichage (F5 après une action, ou lien marqué-page d'un POST) | Les pages en lecture seule n'acceptent que la consultation : repassez par le bouton du site. |

---

## 15. Crédits

- **Conception & développement** : KavegeElis — **NovaTechX**
- **Signature du site** : Joël, pour Clémentine
- **Contact développeur** : [WhatsApp](https://wa.me/message/P65Q2OW5AOTTP1)
- **Polices** : polices système uniquement (aucun appel réseau)
- **Images et musiques** : à fournir par les administrateurs (aucune œuvre tierce n'est livrée)

© 2026 · Projet privé, non indexé. Merci de ne pas partager ce site ni son accès en dehors de la famille et des amis proches de Clémentine.
