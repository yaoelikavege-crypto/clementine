# Déploiement sur InfinityFree — guide en 12 étapes

> Ce guide part du principe que vous avez **déjà** le dossier `clementine-birthday` décompressé sur votre ordinateur, et que vous savez utiliser un client FTP ou le gestionnaire de fichiers de VistaPanel.
> Temps total : **30 à 45 minutes** (hors envoi des médias).

---

## Récapitulatif express

| # | Étape | Durée |
| --- | --- | --- |
| 1 | Créer le compte d'hébergement | 5 min |
| 2 | Créer la base de données | 3 min |
| 3 | Choisir la version de PHP | 2 min |
| 4 | Envoyer les fichiers par FTP | 10 min |
| 5 | Créer `includes/config.php` | 5 min |
| 6 | Importer `sql/install.sql` (option A) | 3 min |
| 7 | Lancer l'assistant `/setup/index.php` | 5 min |
| 8 | Activer HTTPS | 5 min |
| 9 | Vérifier les droits d'écriture | 2 min |
| 10 | Se connecter et sécuriser | 5 min |
| 11 | Envoyer les médias et la lettre | variable |
| 12 | Contrôles finaux | 5 min |

---

## Étape 1 — Créer le compte d'hébergement

1. Ouvrez <https://www.infinityfree.com> et créez un compte (gratuit).
2. **Create Account** → choisissez un sous-domaine gratuit :
   - `clementine21.rf.gd` ou `clementine21.epizy.com` (ou un domaine personnalisé).
3. Choisissez le mot de passe de votre **compte client** (VistaPanel) et notez-le.
4. Attendez l'e-mail de confirmation (« Your hosting account is ready »), puis ouvrez **Control Panel**.

> Le panneau s'appelle **VistaPanel**. Les sections utiles : *Online File Manager*, *FTP Accounts*, *MySQL Databases*, *PHP Configuration*, *SSL/TLS*.

---

## Étape 2 — Créer la base de données

1. VistaPanel → **MySQL Databases** → *Create Database*.
2. Nom : `clementine` (le préfixe `if0_XXXXXXXX_` est ajouté automatiquement).
3. Notez précieusement :

```
Hôte MySQL   : sqlXXX.infinityfree.com     ← ou sqlXXX.epizy.com
Port         : 3306
Nom de la BD : if0_12345678_clementine
Utilisateur  : if0_12345678
Mot de passe : (celui que vous venez de définir)
```

4. Ouvrez **phpMyAdmin** depuis la même page et vérifiez que la base apparaît à gauche.

> Astuce : le bouton *phpMyAdmin* ne fonctionne parfois qu'après quelques minutes. Réessayez, ou passez par le lien « Admin » de la ligne de la base.

---

## Étape 3 — Choisir la version de PHP

1. VistaPanel → **PHP Configuration** (ou *Select PHP Version*).
2. Choisissez **PHP 8.1**, **8.2** ou **8.3** (évitez 7.4 : trop ancien).
3. Vérifiez que ces extensions sont cochées : `pdo_mysql`, `mbstring`, `gd`, `fileinfo`, `json`, `openssl`.
4. Validez. La modification prend effet en quelques minutes.

> L'assistant d'installation contrôlera ces points à l'étape 7 et affichera un voyant rouge si une extension manque.

---

## Étape 4 — Envoyer les fichiers

### Méthode A — Gestionnaire de fichiers (le plus simple)
1. VistaPanel → **Online File Manager**.
2. Ouvrez le dossier **`htdocs`** (la racine web).
3. Supprimez le fichier `default.php` ou `index2.html` s'il existe.
4. **Upload** → sélectionnez l'archive `clementine-birthday.zip` → une fois en place, utilisez **Extract**.
5. Déplacez le **contenu** du dossier extrait pour qu'il soit directement dans `htdocs` :

```
htdocs/
├── index.php
├── login.php
├── .htaccess        ← fichier caché, vérifiez qu'il est bien présent
├── admin/
├── includes/
└── …
```

### Méthode B — FileZilla (recommandé pour les médias)
1. VistaPanel → **FTP Accounts** : notez l'hôte (`ftpupload.net`), l'utilisateur (`if0_XXXXXXXX`) et le mot de passe.
2. FileZilla → *Fichier* → *Gestionnaire de sites* → **SFTP/FTPS non**, **FTP simple**, port **21**.
3. Glissez-déposez le **contenu** du projet dans `htdocs/`.
4. Vérifiez que les fichiers **cachés** `.htaccess` sont visibles : *Serveur* → *Forcer l'affichage des fichiers cachés*.

### Sous-dossier : c'est permis
Vous pouvez déposer le site **dans `htdocs`** ou **dans un sous-dossier** (`htdocs/clementine/`). Depuis la version du 21/10/2026, le préfixe d'URL est **détecté automatiquement** au chargement (`base_path()` dans `includes/helpers.php`) : CSS, JS, images, formulaires et redirections utilisent le bon chemin dans les deux cas. `app.base_url` peut rester vide.

### ⚠️ Trois pièges à éviter
- **Ne pas** oublier les `.htaccess` (fichiers cachés) : sans eux, `/uploads`, `/includes` et `/storage` deviennent lisibles.
- **Ne pas** envoyer `includes/config.php` avec des identifiants locaux : créez-le directement sur le serveur (étape 5).
- **Ne pas** renommer `setup/` : c'est l'adresse de l'assistant (`/setup/index.php`), et il se verrouille seul après usage.

### Limite de taille
Chaque fichier est limité à **10 Mo** (1 Mo pour les `.php`, 10 ko pour les `.htaccess`) : tout fichier plus lourd est **supprimé automatiquement** par le serveur. Le projet est très en dessous ; pour les médias, voir étape 11.

---

## Étape 5 — Créer `includes/config.php`

1. Dans `htdocs/includes/`, repérez `config.example.php`.
2. Ouvrez l'éditeur du gestionnaire de fichiers (clic droit → *Edit*).
3. Copiez tout son contenu, puis créez un **nouveau fichier** `includes/config.php` et collez-le.
4. Modifiez :

```php
'app' => [
    'name'        => 'Clémentine · 21 ans',
    'timezone'    => 'Africa/Lome',
    'reveal_at'   => '2026-11-23 00:00:00',   // NE PAS MODIFIER
    'base_url'    => '',                      // vide si le site est à la racine
    'debug'       => false,                   // impératif en production
    'install_key' => 'votre-cle-longue-et-aleatoire',
    'privacy_salt'=> 'votre-sel-long-et-aleatoire',
],

'db' => [
    'host'    => 'sqlXXX.infinityfree.com',
    'port'    => 3306,
    'name'    => 'if0_12345678_clementine',
    'user'    => 'if0_12345678',
    'pass'    => 'le-mot-de-passe-de-la-base',
    'charset' => 'utf8mb4',
],
```

5. Enregistrez. **Ne partagez jamais ce fichier** : il contient les accès à votre base.

**Générer deux chaînes solides** (sur votre ordinateur, si vous avez PHP) :
```bash
php -r "echo bin2hex(random_bytes(24)), PHP_EOL, bin2hex(random_bytes(32)), PHP_EOL;"
```
Sinon : 40 caractères aléatoires (lettres + chiffres) suffisent.

---

## Étape 6 — Importer la base de données (option A)

> **Option A** : vous importez le SQL maintenant, l'assistant ne créera que les comptes.
> **Option B** : vous sautez cette étape, l'assistant crée tout à l'étape 7.

1. phpMyAdmin → sélectionnez votre base `if0_12345678_clementine` à gauche.
2. Onglet **Importer**.
3. Fichier : `sql/install.sql` (téléversez-le depuis votre ordinateur).
4. Format : **SQL**, jeu de caractères : **utf-8**, cochez *Ne pas utiliser AUTO_INCREMENT* : **non**.
5. **Exécuter**.

Vous devez voir apparaître **12 tables** :

```
admins · audit_log · countdown_messages · login_attempts · love_letter · media
memories · playlist_tracks · playlists · site_settings · visitor_credentials · wishes
```

Et des contenus de départ : 4 playlists, ~20 réglages, 3 vœux, 3 messages d'attente, 1 lettre modèle.

> En cas d'erreur `#1044 Accès refusé`, c'est que vous êtes sur la base `information_schema` ou sur une autre base : recommencez en sélectionnant la bonne base **avant** l'import.
> En cas d'erreur `#1005 / errno 150` sur une clé étrangère : votre moteur n'est pas InnoDB. Chez InfinityFree, MariaDB est en InnoDB par défaut ; vérifiez que vous importez bien sur la bonne base.

---

## Étape 7 — Lancer l'assistant d'installation

1. Ouvrez `https://votre-sous-domaine.rf.gd/setup/index.php`.
2. **Autorisation** : saisissez la clé `install_key` définie à l'étape 5.
3. **Vérifications** : tout doit être vert :
   - PHP 8.0+ · `pdo_mysql` · `mbstring` · `gd` · `fileinfo` · `json` · `openssl`
   - dossier `uploads/` inscriptible
   - dossier `storage/` inscriptible
   - clé personnalisée · debug désactivé
   - HTTPS actif (si rouge, faites l'étape 8 d'abord, ce n'est pas bloquant)
4. **Base de données** : cliquez sur **« Créer les tables »**. L'assistant crée les 12 tables **et les trois comptes de première connexion** déclarés dans `includes/config.php` (`app.default_accounts`) :

   | Compte | Identifiant | Mot de passe |
   | --- | --- | --- |
   | Administrateur Joël | `joel` | `j123456789@` |
   | Administrateur KavegeElis | `elis` | `e123456789@` |
   | Visiteur (Clémentine) | *(aucun)* | `c123456789@` |

   Le message « Comptes par défaut créés : joel, elis + accès visiteur » confirme l'opération. **Cette étape ne peut plus vous bloquer** : aucune saisie n'est nécessaire, et si les comptes existent déjà ils sont simplement ignorés (la fonction est rejouable).
5. Si vous préférez des identifiants personnalisés, dépliez **« Créer un compte à la main »** (politique : 10 caractères minimum, lettres + chiffres) — ou modifiez `app.default_accounts` **avant** de cliquer.
6. **Finalisation** : l'assistant écrit `storage/installed.lock`.

> Si les comptes par défaut sont absents (par exemple après une suppression), un bouton **« Créer ces comptes maintenant »** apparaît à l'étape 3 et les recrée à partir du même fichier de configuration.

✅ Rechargez `/setup/index.php` : vous devez obtenir **« L'assistant est verrouillé »** (HTTP 403). C'est le comportement attendu.

---

## Étape 8 — Activer HTTPS

InfinityFree fournit un certificat SSL gratuit, mais il n'est actif qu'après autorisation :

1. VistaPanel → **SSL/TLS** → *Free SSL Certificates*.
2. Choisissez votre domaine → **Request Certificate**.
3. Attendez la validation (de quelques minutes à 24 h).
4. Une fois le cadenas visible, ouvrez `.htaccess` **à la racine** et décommentez le bloc :

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{HTTPS} off
    RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
</IfModule>
```

> ⚠️ Ne décommentez **pas** ce bloc avant que le certificat fonctionne : vous rendriez le site inaccessible. En attendant, reconnectez-vous en `https://` manuellement.

Le cookie de session devient automatiquement `Secure` lorsque HTTPS est actif.

---

## Étape 9 — Vérifier les droits d'écriture

1. Gestionnaire de fichiers → clic droit sur `uploads/` → **Permissions** : `755` (essayez `775` si un import échoue).
2. Idem pour `storage/` et `storage/logs/`.
3. Testez un import de photo depuis **Administration → Photos** :
   - succès : la photo apparaît dans la bibliothèque ;
   - échec : message « Le dossier de destination n'est pas accessible en écriture ».

---

## Étape 10 — Se connecter et sécuriser

1. `https://votre-domaine/login.php` → **Je suis administrateur** → `joel` / `j123456789@` (ou `elis` / `e123456789@`).
2. Un bandeau orange **« Mots de passe par défaut encore actifs »** s'affiche sur le tableau de bord : c'est normal, il liste les comptes à sécuriser.
3. Allez dans **Mots de passe** et traitez les trois blocs :
   - **Changer mon mot de passe** (10 caractères minimum, au moins une lettre et un chiffre, pas de mot évident) ;
   - **Compte administrateur → l'autre compte** : changez aussi son mot de passe depuis ce même écran, ou faites-le depuis sa propre session ;
   - **Mot de passe commun des visiteurs** : remplacez `c123456789@` par une phrase que vous transmettrez à Clémentine.
   - Vous pouvez aussi corriger votre **identifiant** et votre **nom affiché** dans « Mon profil ».
4. Quand les trois mots de passe sont remplacés, le bandeau disparaît : il est piloté par `site_settings.default_passwords_active`, recalculé à chaque changement.
5. Pour finir, mettez `seed_default_accounts` à `false` dans `includes/config.php` et effacez les mots de passe du bloc `default_accounts` : ils ne servent plus à rien.
3. Dans **Paramètres généraux**, vérifiez : titre du site, textes, fuseau `Africa/Lome`, date de révélation affichée en lecture seule.
4. Videz le journal d'activité si vous le souhaitez (les entrées de l'installation y figurent).

---

## Étape 11 — Envoyer les médias

### Photos (≤ 6 Mo)
**Administration → Photos → Importer**. L'image est ré-encodée, les métadonnées supprimées, la taille limitée à 2 200 px.

### Musiques (≤ 8 Mo)
**Bibliothèque musicale → Musiques**. Puis **Playlists** pour composer les 4 ambiances.

> **Cas particulier de la musique d'attente** : pour qu'elle soit audible **avant** le 23 novembre, décochez « Protéger jusqu'à la révélation » sur la piste. Une piste protégée n'est jamais diffusée avant minuit.

### Vidéos
- **Petite vidéo (< 8 Mo)** : import direct (MP4 H.264 recommandé, 720p, ~1 min).
- **Vidéo plus lourde** : hébergez-la sur **YouTube (non répertoriée)** ou **Vimeo**, puis dans l'admin choisissez **Lien externe** et collez l'URL.
  - YouTube : `https://www.youtube.com/watch?v=XXXXXXXXXXX`
  - Vimeo : `https://vimeo.com/123456789`
  - Seules les URL **HTTPS** de YouTube/Vimeo sont acceptées (liste blanche dans `config.php`).

### Miniature
Facultative pour les vidéos : une image JPG/PNG/WebP (≈ 1280×720) qui s'affiche dans la galerie.

---

## Étape 12 — Contrôles finaux

Cochez au fur et à mesure :

- [ ] `https://votre-domaine/` affiche le compte à rebours et le nom *Clémentine*.
- [ ] Le compte à rebours **défile** (les secondes changent).
- [ ] `https://votre-domaine/setup/index.php` renvoie bien une page 403 « verrouillé ».
- [ ] `https://votre-domaine/includes/config.php` ne montre **rien** (page blanche ou 403).
- [ ] `https://votre-domaine/uploads/images/` renvoie **403** ou **404** (jamais la liste des fichiers).
- [ ] Connexion administrateur : le tableau de bord s'ouvre ; les 10 menus fonctionnent.
- [ ] Connexion visiteur : redirection vers la salle d'attente avant minuit.
- [ ] Une photo importée s'affiche dans la galerie ; clic → visionneuse plein écran.
- [ ] La lettre est publiée (case cochée) et s'affiche.
- [ ] La bougie s'éteint au clic et « Joyeux anniversaire Clémentine » apparaît.
- [ ] Le son fonctionne après un clic sur le lecteur (ou sur « Activer la musique »).
- [ ] Sur téléphone : menu hamburger, galerie, finale — rien ne déborde horizontalement.
- [ ] Le pied de page affiche *Joël*, *KavegeElis*, *NovaTechX*.
- [ ] Le bouton WhatsApp ouvre bien `https://wa.me/message/P65Q2OW5AOTTP1`.
- [ ] `storage/logs/app.log` ne contient aucune erreur répétée.
- [ ] Sauvegarde faite : SQL exporté + `config.php` + `uploads/`.

---

## Après le déploiement

### Le jour J
Rien à faire : à **23 novembre 2026, 00 h 00 (Africa/Lome)**, le serveur considère la date comme atteinte. Les visiteurs connectés basculent automatiquement vers les souvenirs (la page se recharge seule dans les 60 secondes). Le compte à rebours affiche `00 00 00 00` et ne devient jamais négatif.

### En cas de problème

| Symptôme | Cause probable | Solution |
| --- | --- | --- |
| Page blanche | Erreur PHP masquée | Passer `debug` à `true` **temporairement**, consulter `storage/logs/app.log`, puis remettre `false` |
| « Connexion MySQL impossible » | Identifiants ou hôte erronés | Recopier exactement ceux de VistaPanel (l'hôte n'est pas `localhost`) |
| Redirection en boucle vers `/setup` | `storage/installed.lock` absent et `site_settings.installed` à 0 | Relancer l'assistant ou créer le verrou |
| 403 partout | `.htaccess` trop strict ou permissions `600` | Repasser les dossiers en `755`, les fichiers en `644` |
| Import de photo refusé | Dossier non inscriptible ou fichier > 6 Mo | Ajuster les permissions / compresser |
| Vidéo « supprimée » après envoi | Fichier > 10 Mo (suppression serveur) | Utiliser un lien YouTube/Vimeo |
| Musique inaudible avant minuit | Piste protégée | Décocher « Protéger jusqu'à la révélation » |
| Compte à rebours figé | JavaScript bloqué ou `api/time.php` inaccessible | Vérifier la console du navigateur, désactiver les bloqueurs |
| « Trop de tentatives » | 5 échecs en 15 min | Attendre 15 minutes, ou vider la table `login_attempts` |
| Message « Le site est en préparation » | Mode maintenance actif | Le désactiver dans **Paramètres généraux** |

### Mise à jour du site plus tard
1. Sauvegardez la base et `config.php`.
2. Remplacez uniquement les fichiers de code (`admin/`, `actions/`, `includes/` sauf `config.php`, `assets/`, pages PHP).
3. **Ne remplacez jamais** `uploads/`, `storage/`, ni `config.php`.
4. Testez en administrateur, puis en visiteur.

### Désinstaller
1. Supprimez tous les fichiers de `htdocs/`.
2. Supprimez la base via VistaPanel.
3. Le compte gratuit reste réutilisable pour un autre projet.

---

## Annexe — Configuration Nginx (si vous migrez ailleurs)

Apache n'est pas obligatoire. Sur Nginx, remplacez les `.htaccess` par :

```nginx
root /var/www/clementine;
index index.php;

# Fichiers et dossiers privés
location ~ ^/(uploads|storage|sql|tools|includes)/ {
    deny all;
    return 403;
}

location ~* \.(md|sql|log|lock|ini|sh|bak)$ {
    deny all;
    return 403;
}

location ~* ^/\. {
    deny all;
    return 403;
}

location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location ~ \.php$ {
    include fastcgi_params;
    fastcgi_pass unix:/run/php/php8.2-fpm.sock;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
}

error_page 403 /errors/403.php;
error_page 404 /errors/404.php;
error_page 500 /errors/500.php;
```

Les en-têtes de sécurité sont déjà envoyés par PHP : inutile de les dupliquer.

---

## Annexe — Serveur de développement local

```bash
# Depuis la racine du projet, avec PHP 8 installé
php -S 0.0.0.0:8080 router.php
```

`router.php` reproduit le comportement d'Apache (404 réels, dossiers privés refusés). Il n'est **pas** utilisé en production et peut être supprimé du serveur après déploiement.

---

**Besoin d'aide ?** Contactez le développeur : [WhatsApp — KavegeElis / NovaTechX](https://wa.me/message/P65Q2OW5AOTTP1).
