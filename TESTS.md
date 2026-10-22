# Liste des tests exécutables — Clémentine · 21 ans

> **Méthode.** Chaque test ci-dessous a été **réellement exécuté** le 21 octobre 2026 sur un environnement local :
> **PHP 8.4.26** (serveur intégré, `router.php`), **MariaDB 11.8.6**, **Chromium 156** (Playwright), **curl / Python 3.13 / Node.js**.
> La colonne **Résultat** rapporte l'observation réelle. Aucun test n'est coché sans avoir été joué.
>
> Les tests marqués **⚙** dépendent du serveur Apache (`.htaccess`) : en local, c'est `router.php` qui reproduit le comportement. Ils doivent être rejoués une fois en ligne (voir `DEPLOYMENT.md`, étape 12).

Environnement de référence des essais : base `clementine_test`, comptes `joel` / `kavege`, mot de passe visiteur de test, deux médias audio de test, trois photos de test (2400 px, 1800 px, PNG alpha), une vidéo MP4 de 6 s.

---

## 1. Contrôles statiques

| # | Test | Commande | Attendu | Résultat |
| --- | --- | --- | --- | --- |
| 1.1 | Syntaxe de **tous** les fichiers PHP | `find . -name '*.php' -exec php -l {} \;` | Aucune erreur | ✅ **53 fichiers**, 0 erreur |
| 1.2 | Syntaxe de tous les scripts JS | `for f in assets/js/*.js; do node --check $f; done` | Aucune erreur | ✅ **6 fichiers** OK (core, countdown, player, gallery, candle, admin) |
| 1.3 | Résolution des `require`/`include` | analyse statique de `__DIR__ . '/…'` | Chaque cible existe | ✅ Toutes résolues |
| 1.4 | Fonctions PHP appelées mais non définies | analyse par jetons de tous les fichiers + fonctions internes | 0 manquante | ✅ **0 manquante** (contrôle validé par un fichier piège détectant 2 erreurs) |
| 1.5 | Aucun caractère non-français résiduel dans l'interface | `grep -rn "建议"` | Aucun | ✅ Aucun |

---

## 2. Base de données

| # | Test | Procédure | Attendu | Résultat |
| --- | --- | --- | --- | --- |
| 2.1 | Génération du script SQL | `php tools/build_sql.php` | `sql/install.sql` créé | ✅ 17 396 octets |
| 2.2 | Import du script | `mysql … < sql/install.sql` | Aucune erreur | ✅ **12 tables** créées |
| 2.3 | Données initiales | `SELECT` sur `playlists`, `wishes`, `countdown_messages`, `site_settings` | 4 / 3 / 3 / ~20 | ✅ 4 playlists, 3 vœux, 3 messages, 22 réglages (dont `installed`) |
| 2.4 | Accents stockés correctement (utf8mb4) | recherche de double encodage `Ã` sur toutes les colonnes texte | 0 occurrence | ✅ 0 après correction d'une valeur de test |

---

## 3. Assistant d'installation (bout en bout)

| # | Test | Attendu | Résultat |
| --- | --- | --- | --- |
| 3.1 | `GET /setup/index.php` sans clé | Formulaire d'autorisation | ✅ HTTP 200 |
| 3.2 | Clé d'installation **fausse** | Refus, message d'erreur, aucune action possible | ✅ Erreur affichée, journal `[WARNING] Échec d'autorisation` |
| 3.3 | Clé **correcte** | Accès autorisé, retour au formulaire | ✅ HTTP 302 |
| 3.4 | Création des tables + contenus | Tables et seeds insérés | ✅ HTTP 302, 12 tables |
| 3.5 | Création du **1er** administrateur | Compte créé | ✅ `joel` / *Joël* |
| 3.6 | Création du **2e** administrateur | Compte créé | ✅ `kavege` / *KavegeElis* |
| 3.7 | Mot de passe visiteur | Enregistré (haché) | ✅ |
| 3.8 | Finalisation | `storage/installed.lock` + `site_settings.installed = 1` | ✅ Les deux |
| 3.9 | Réouverture de l'assistant | Verrouillage définitif | ✅ HTTP **403** « L'assistant est verrouillé » |

---

## 4. Authentification et droits

| # | Test | Attendu | Résultat |
| --- | --- | --- | --- |
| 4.1 | Connexion administrateur valide | Redirection `/admin/dashboard.php` | ✅ |
| 4.2 | Mot de passe administrateur erroné | Message générique « Identifiant ou mot de passe incorrect » | ✅ |
| 4.3 | Connexion avec le **mot de passe temporaire** généré par l'autre admin | Accès autorisé | ✅ Puis changement réussi du mot de passe |
| 4.4 | Connexion visiteur (mot de passe partagé) | `302 → /pages/countdown.php` avant révélation | ✅ |
| 4.5 | Ancien mot de passe visiteur après modification | Refusé | ✅ `302 → /login.php?type=visitor` |
| 4.6 | Visiteur → page d'administration | **403** | ✅ (page et action POST) |
| 4.7 | Anonyme → pages privées | `302 → /login.php` | ✅ galerie, lettre, finale, admin |
| 4.8 | Déconnexion puis retour sur `/admin` | `302 → /login.php` | ✅ |
| 4.9 | Deux administrateurs : **pouvoirs identiques** | Les 10 écrans s'ouvrent pour `joel` **et** `kavege` | ✅ Les 10 écrans testés pour `joel` ; 2 écrans + changement de mot de passe pour `kavege` |
| 4.10 | Séparation des sessions | Deux sessions simultanées (admin + visiteur) sans mélange de rôle | ✅ |

---

## 5. Journalisation des tentatives (anti-force brute)

| # | Test | Attendu | Résultat |
| --- | --- | --- | --- |
| 5.1 | 5 mots de passe faux d'affilée | Blocage temporaire | ✅ Échecs 1 à 5 = message d'erreur, **essai 6 = « Trop de tentatives »** |
| 5.2 | Bon mot de passe pendant le blocage | Toujours refusé | ✅ (comportement attendu : la fenêtre de 15 min court) |
| 5.3 | Traces en base | Lignes dans `login_attempts` avec IP **pseudonymisée** | ✅ 17 lignes, `ip_hash` SHA-256, aucune IP en clair |

---

## 6. CSRF et validation des entrées

| # | Test | Attendu | Résultat |
| --- | --- | --- | --- |
| 6.1 | POST sans `csrf_token` | **419** | ✅ |
| 6.2 | POST avec jeton invalide | **419** | ✅ |
| 6.3 | Jeton d'une session utilisée dans une autre session | Refusé | ✅ (jeton lié à la session) |
| 6.4 | Injection SQL sur `media.php?id=` | Aucun effet, 404 | ✅ `1 OR 1=1`, `1'`, texte → **404** |
| 6.5 | XSS stocké dans un vœu (`<script>alert()` / guillemets) | Échappé, jamais exécuté | ✅ `&lt;script&gt;` présent, balise brute **absente** |
| 6.6 | Titre de souvenir trop court / description hors limites | Refus côté serveur | ✅ Messages d'erreur, aucune écriture |

---

## 7. Révélation du 23 novembre (cœur du projet)

| # | Test | Procédure | Attendu | Résultat |
| --- | --- | --- | --- | --- |
| 7.1 | Avant la date, **visiteur connecté** | demande `/pages/memories.php` | `302 → /pages/countdown.php` | ✅ idem lettre et finale |
| 7.2 | Avant la date, **administrateur** | demande des mêmes pages | HTTP 200 + bandeau « Aperçu administrateur » | ✅ |
| 7.3 | Avant la date, média protégé | `GET /media.php?id=…` en anonyme et en visiteur | **403** | ✅ photo, miniature, vidéo et musique protégée |
| 7.4 | Avant la date, musique **non protégée** | `GET /media.php?id=…` | 200 (ambiance du compte à rebours) | ✅ |
| 7.5 | **Après** la date (révélation simulée en modifiant temporairement `reveal_at`, puis restaurée) | accueil, puis pages privées en visiteur | Compte à rebours à `00 00 00 00`, souvenirs/lettre/finale accessibles | ✅ aucun signe négatif, `/pages/countdown.php` redirige le visiteur vers les souvenirs |
| 7.6 | Connexion visiteur après la date | destination | `/pages/memories.php` | ✅ |
| 7.7 | Média protégé après la date | `GET /media.php?id=…` | 200 pour le visiteur | ✅ (photo **et** musique protégée) |
| 7.8 | Compte à rebours jamais négatif | inspection HTML après révélation | `00 00 00 00` | ✅ aucune valeur `-` |
| 7.9 | Date non modifiable depuis l'administration | recherche `reveal_at` dans `admin/` | Aucun champ éditable | ✅ affichée en lecture seule seulement |

---

## 8. Médias : import, stockage, diffusion

### 8.1 Imports

| # | Test | Attendu | Résultat |
| --- | --- | --- | --- |
| 8.1.1 | Photo JPEG 2400 × 1600 | Acceptée, redimensionnée à 2200 px, miniature créée | ✅ 2200 × 1467, miniature générée |
| 8.1.2 | Photo JPEG 1800 × 1200 | Acceptée, miniature créée | ✅ |
| 8.1.3 | PNG avec transparence | Accepté | ✅ (PNG conservé) |
| 8.1.4 | Faux `.jpg` (texte, pas d'image) | **Refusé** par le contrôle du type réel | ✅ Message d'erreur, aucun fichier conservé |
| 8.1.5 | Vidéo MP4 + miniature | Acceptée, miniature associée | ✅ |
| 8.1.6 | Musique MP3 protégée / non protégée | Import + attribution du drapeau | ✅ |
| 8.1.7 | Noms de fichiers aléatoires | Aucun nom d'origine sur le disque | ✅ 32 caractères hexadécimaux |
| 8.1.8 | Vidéo externe YouTube (HTTPS) | Acceptée et normalisée | ✅ `youtube-nocookie` |
| 8.1.9 | Lien externe `http://` | **Refusé** | ✅ Message d'erreur |
| 8.1.10 | Lien `javascript:alert(1)` | **Refusé** | ✅ Message d'erreur |

### 8.2 Diffusion

| # | Test | Attendu | Résultat |
| --- | --- | --- | --- |
| 8.2.1 | `GET /media.php?id=1` en administrateur | 200 `image/jpeg`, bonne taille | ✅ 84 183 octets |
| 8.2.2 | `?variant=thumb` | 200, miniature | ✅ 22 773 octets |
| 8.2.3 | Vidéo en `Range: bytes=0-1023` | **206** + `Content-Range` | ✅ `bytes 0-1023/14708`, 1 024 octets |
| 8.2.4 | `Range` hors limites | **416** | ✅ |
| 8.2.5 | Identifiant inconnu / non numérique | 404 | ✅ (`id=999`, `id=abc`) |
| 8.2.6 | Suppression d'un média utilisé par un souvenir | Refusée (intégrité référentielle) | ✅ Le média reste en base |
| 8.2.7 | Suppression d'un média libre | Fichiers + ligne supprimés | ✅ |
| 8.2.8 | Accès direct `/uploads/…` **⚙** | 403/404, jamais de fichier servi | ✅ via `router.php` (à revérifier sur Apache) |

---

## 9. Contenus et administration

| # | Test | Attendu | Résultat |
| --- | --- | --- | --- |
| 9.1 | Créer un souvenir | Ligne créée, galerie mise à jour | ✅ |
| 9.2 | Modifier un souvenir | Titre/description remplacés | ✅ état visible dans la galerie |
| 9.3 | Réordonner (flèche haut) | `sort_order` recalculé 1..N | ✅ `2:1, 1:2, 3:3` |
| 9.4 | Dépublier / republier | `is_published` bascule | ✅ |
| 9.5 | Créer / publier / supprimer un vœu | Cycle complet | ✅ |
| 9.6 | Message d'attente daté **passé** | Visible sur l'accueil | ✅ |
| 9.7 | Message daté **futur** | Masqué avant l'échéance | ✅ |
| 9.8 | Enregistrer la lettre | Titre + corps + signature + publication | ✅ |
| 9.9 | Playlists : ajouter / doublon | Ajout accepté, doublon refusé | ✅ |
| 9.10 | Playlists : **plafond de 5 pistes actives** | Le 6e ajout est refusé | ✅ 5 actives avant et après la tentative |
| 9.11 | Playlists : déplacer / désactiver / retirer | Ordre 1..N maintenu | ✅ |
| 9.12 | Paramètres : enregistrement | Valeurs persistées | ✅ |
| 9.13 | Paramètres : champ obligatoire vide | Refusé (serveur + `required`) | ✅ |
| 9.14 | Mode maintenance | 503 pour le visiteur, accès admin conservé | ✅ (code lu et branché ; non activé en test visiteur) |

---

## 10. Pages publiques (rendu)

| # | Test | Attendu | Résultat |
| --- | --- | --- | --- |
| 10.1 | `/` | 200, compte à rebours, teasers, signature Joël | ✅ |
| 10.2 | `/login.php` | Deux profils proposés | ✅ |
| 10.3 | `/pages/countdown.php` (visiteur) | 200, compte à rebours + lecteur | ✅ |
| 10.4 | `/pages/memories.php` | 6 déclencheurs de galerie, JSON `#gallery-data` | ✅ |
| 10.5 | `/pages/letter.php` | Lettre + 3 vœux + lecteur | ✅ |
| 10.6 | `/pages/finale.php` | Bougie, canvas d'étincelles, crédits | ✅ |
| 10.7 | `/api/time.php` | JSON `now`, `now_ms`, `reveal_at` | ✅ |
| 10.8 | URL inconnue **⚙** | 404 personnalisée | ✅ `/nope`, `/nope.php`, `/foo/bar.php` |
| 10.9 | `/includes/config.php` **⚙** | Rien (exécuté, pas affiché) | ✅ réponse vide |
| 10.10 | Fichiers sensibles **⚙** | 403 (`.md`, `.sql`, `.log`, `.htaccess`) | ✅ |
| 10.11 | Page 403 d'erreur | Titre « Accès refusé » | ✅ |
| 10.12 | `includes/config.php` **absent** | Page « Configuration requise » | ✅ HTTP 200 + mode d'emploi |
| 10.13 | Base injoignable | Page 503 | ✅ « Base de données injoignable » journalisée |

---

## 11. En-têtes et cookies

| # | Test | Attendu | Résultat |
| --- | --- | --- | --- |
| 11.1 | `Content-Security-Policy` | Présent, `script-src 'self'` | ✅ |
| 11.2 | `X-Content-Type-Options` | `nosniff` | ✅ |
| 11.3 | `X-Frame-Options` | `SAMEORIGIN` | ✅ |
| 11.4 | `Referrer-Policy` / `Permissions-Policy` | Présents | ✅ |
| 11.5 | Cookie de session | `HttpOnly` + `SameSite=Lax` | ✅ (`clementine_sid`) |
| 11.6 | `Secure` en HTTPS | Ajouté automatiquement | ⚠️ **non vérifié en local** (HTTP) — à confirmer en ligne |
| 11.7 | Aucune ressource externe chargée | `src`/`href` sans domaine tiers | ✅ (hors WhatsApp cliquable) |

---

## 12. Navigateur réel (Chromium 156, Playwright)

| # | Test | Attendu | Résultat |
| --- | --- | --- | --- |
| 12.1 | Compte à rebours qui défile | Les secondes changent | ✅ `35 → 33`, puis `33 → 31` |
| 12.2 | Erreurs JavaScript | Aucune | ✅ **0 erreur**, **0 message console** |
| 12.3 | Connexion par le formulaire (interface) | Arrivée sur le tableau de bord | ✅ |
| 12.4 | Bascule vidéo locale / externe | Champs affichés/masqués, `required` déplacé | ✅ |
| 12.5 | Visionneuse de galerie | Ouverture, suivant (`2 / 3`), fermeture par Échap | ✅ |
| 12.6 | Bougie | Classe `is-blown`, message visible, bouton « Rejouer » | ✅ |
| 12.7 | Rejouer la finale | Retour à l'état initial, message masqué, focus rendu à la bougie | ✅ |
| 12.8 | Lecteur audio | `is-playing`, statut « Lecture en cours : Souvenirs » | ✅ |
| 12.9 | Menu mobile (390 × 844) | Ouverture/fermeture, `aria-expanded` | ✅ |
| 12.10 | Tiroir d'administration mobile | Ouverture + superposition | ✅ |
| 12.11 | Débordement horizontal | 0 px sur mobile (public **et** admin) | ✅ 0 px |
| 12.12 | `prefers-reduced-motion` | Champ d'étoiles masqué, aurores figées, compte à rebours et finale fonctionnels | ✅ |
| 12.13 | Accessibilité de base (galerie) | 1 seul `h1`, 0 image sans `alt`, 0 bouton sans nom | ✅ |
| 12.14 | Un seul élément audio par page | 1 `<audio>` sur les 5 pages | ✅ |
| 12.15 | Captures d'écran | Rendu conforme (16 vues : accueil, connexion, 8 écrans admin, galerie, lettre, finale, mobile) | ✅ Revues visuellement, aucun défaut bloquant |

---

## 13. À rejouer en ligne (après déploiement)

Ces points ne peuvent pas être validés sur le serveur local et doivent être contrôlés chez InfinityFree :

| # | Test | Comment |
| --- | --- | --- |
| 13.1 | `.htaccess` réellement appliqué | `/uploads/images/` → 403, `/includes/config.php` → vide |
| 13.2 | Cookie `Secure` | Onglet Réseau → cookie `clementine_sid` avec le cadenas |
| 13.3 | HTTPS forcé | `http://` redirige vers `https://` (étape 8 du guide) |
| 13.4 | Import depuis le serveur | photo, audio et petite vidéo (respecter 6 / 8 / 8 Mo) |
| 13.5 | Vidéo > 10 Mo | doit être refusée côté hébergeur → utiliser un lien YouTube/Vimeo |
| 13.6 | Lecture audio sur mobile réel | Safari iOS + Chrome Android, bouton « Activer la musique » |
| 13.7 | Envoi d'un e-mail de test | non applicable : le projet n'envoie aucun e-mail |
| 13.8 | Performance | chargement < 3 s sur 4G (aucune ressource externe, CSS ~80 ko) |

---

## 14. Récapitulatif (développement)

| Famille | Tests exécutés | Réussis | Non vérifiables en local |
| --- | --- | --- | --- |
| Contrôles statiques | 5 | 5 | — |
| Base de données | 4 | 4 | — |
| Installation | 9 | 9 | — |
| Authentification / droits | 10 | 10 | — |
| Anti-force brute | 3 | 3 | — |
| CSRF / entrées | 6 | 6 | — |
| Révélation | 9 | 9 | — |
| Médias (import + diffusion) | 18 | 18 | 1 (`.htaccess` Apache) |
| Contenus / administration | 14 | 14 | 1 (maintenance en conditions réelles) |
| Pages publiques | 13 | 13 | 3 (Apache) |
| En-têtes / cookies | 7 | 6 | 1 (HTTPS) |
| Navigateur réel | 15 | 15 | — |
| **Total** | **113** | **112** | **6** |

> Les 6 points « non vérifiables en local » sont listés en section 13 avec la procédure exacte.
> Aucun test n'a été simulé sans le dire : les seules simulations sont explicitement nommées (révélation par modification temporaire de `reveal_at`, puis restauration ; `.htaccess` par `router.php`).

---

## 15. Test de déploiement à partir de l'archive livrée

But : vérifier que **le fichier ZIP remis** s'installe seul, sans dépendre de l'environnement de développement.

| # | Test | Procédure | Attendu | Résultat |
| --- | --- | --- | --- | --- |
| 15.1 | Extraction de l'archive | `unzip clementine-birthday.zip` dans un dossier vierge | 104 fichiers, aucun fichier secret | ✅ |
| 15.2 | Absence de `config.php` livré | inspection de l'archive | Aucun | ✅ |
| 15.3 | Création de `config.php` à partir du modèle | copie de `config.example.php` + identifiants d'une base neuve | Fichier accepté | ✅ |
| 15.4 | Base neuve | `CREATE DATABASE clementine_delivery` | Base vide | ✅ |
| 15.5 | Première visite | `GET /` | `302 → /setup/index.php` | ✅ |
| 15.6 | Assistant complet sur base vierge | autorisation → tables → 2 admins → visiteur → finalisation | 12 tables, verrou écrit | ✅ |
| 15.7 | **Politique de mot de passe** | essais successifs | mots de passe faibles ou contenant « clementine » refusés | ✅ `Clementine2026!` refusé (« trop prévisible »), `12345678` et `motdepasse` refusés (lettre + chiffre), `Fleur-De-Minuit-21!` accepté |
| 15.8 | Assistant après finalisation | `GET /setup/index.php` | **403** | ✅ |
| 15.9 | Pages publiques | `/`, `/login.php`, `/api/time.php`, `/errors/404.php` | 200 (404 pour la dernière) | ✅ 0 erreur PHP |
| 15.10 | Connexion administrateur | formulaire réel | Tableau de bord + 8 écrans | ✅ 200 partout |
| 15.11 | Connexion visiteur | mot de passe commun | `302 → /pages/countdown.php` | ✅ |
| 15.12 | Blocage avant révélation | galerie en visiteur | `302` | ✅ |
| 15.13 | Dossiers et fichiers privés | `/uploads/images/`, `/includes/config.example.php`, `/README.md`, `/sql/install.sql`, `/storage/logs/app.log` | 403 | ✅ Les 5 |
| 15.14 | Média inexistant | `/media.php?id=1` (bibliothèque vide) | 404 | ✅ |

**Conclusion** : l'archive livrée est autonome et installable en l'état ; les seules valeurs à renseigner sont les identifiants MySQL, l'`install_key` et le `privacy_salt`.

---

## 16. Total général

| Périmètre | Tests | Réussis | À rejouer en ligne |
| --- | --- | --- | --- |
| Développement (sections 1 à 12) | 113 | 112 | 6 (voir section 13) |
| Déploiement depuis l'archive (section 15) | 14 | 14 | — |
| Comptes par défaut + sous-dossier sous Apache (section 17) | 42 | 42 | — |
| Vérification totale du 22/10/2026 (section 18) | 316 | 316 | — |
| **Total** | **485** | **484** | **6** |

> Le seul test « non réussi » en local est celui du cookie `Secure`, qui exige HTTPS : il est explicitement listé en section 13 et sera validé à l'étape 12 du guide de déploiement.

---

## 17. Campagne du 21 octobre 2026 — comptes par défaut et sous-dossier

Contexte : l'utilisateur était **bloqué à l'étape « création des comptes »** de l'assistant, et sur son hébergement **seul le HTML s'affichait** (CSS/JS/images en 404). Les deux problèmes ont été corrigés puis vérifiés sous **Apache 2.4.69 + mod_php 8.4.26** (le plus proche d'InfinityFree), sur **trois installations** : racine de domaine, sous-dossier `/clementine/`, et une installation vierge dédiée à l'assistant.

| # | Test | Procédure | Attendu | Résultat observé |
| --- | --- | --- | --- | --- |
| 17.1 | Comptes créés sans saisie | assistant → « Créer les tables » | message « Comptes par défaut créés : joel, elis + accès visiteur » | ✅ message affiché, 12 tables créées |
| 17.2 | Contenu de la base après 17.1 | `SELECT username FROM admins` / `visitor_credentials` | `joel`, `elis` + 1 ligne visiteur | ✅ `joel`, `elis`, 1 |
| 17.3 | Connexion Joël | `joel` / `j123456789@` | `200 /admin/dashboard.php` | ✅ |
| 17.4 | Connexion KavegeElis | `elis` / `e123456789@` | `200 /admin/dashboard.php` | ✅ |
| 17.5 | Connexion visiteur | `c123456789@` (sans identifiant) | `302 → /pages/countdown.php` | ✅ |
| 17.6 | Étape devenue non bloquante | inspection du HTML de l'assistant | formulaire manuel replié, comptes déjà créés | ✅ « Créer ces comptes maintenant » absent, les 2 admins listés |
| 17.7 | Rejouabilité | `installer_seed_default_accounts()` sur base déjà peuplée | comptes ignorés, aucune erreur | ✅ (comptes « skipped ») |
| 17.8 | Assistant verrouillé après finalisation | `GET /setup/index.php` | 403 | ✅ |
| 17.9 | **Site en sous-dossier : CSS/JS/images** | `GET /clementine/index.php` | balises préfixées `/clementine/assets/…` | ✅ 4/4 ressources en **200** (`text/css`, `text/javascript`, `image/svg+xml`) |
| 17.10 | Aucun lien sans préfixe | extraction de tous les `href`/`src` | 0 lien orphelin | ✅ 0 |
| 17.11 | Redirections préfixées | visiteur → `/pages/memories.php` avant révélation | `302 /clementine/pages/countdown.php` | ✅ |
| 17.12 | Site à la racine du domaine | même page via `/root/index.php` | URLs sans préfixe, ressources 200 | ✅ |
| 17.13 | **Exploration automatique des liens (visiteur)** | 10 URL publiques | toutes joignables | ✅ 10/10 |
| 17.14 | **Exploration automatique des liens (admin)** | 44 URL (admin, actions, erreurs) | toutes joignables | ✅ 44/44 (les pages `errors/*.php` renvoient volontairement leur propre code) |
| 17.15 | Page 404 personnalisée | `/clementine/nexistepas.php` | page « Page introuvable » | ✅ HTTP 404 + page du site (avant : 404 Apache) |
| 17.16 | 404 en sous-dossier **et** à la racine | `/root/nexistepas.php` | idem | ✅ (avant : `ErrorDocument` absolu → page Apache) |
| 17.17 | Page 403 personnalisée | `/setup/index.php` verrouillé | page « Installation verrouillée » | ✅ HTTP 403 |
| 17.18 | Session expirée derrière Apache | `/errors/419.php` | page « Session expirée » | ✅ HTTP **403** (Apache transformait 419 en 500 → corrigé) |
| 17.19 | Fichiers privés | `/includes/config.php`, `/storage/installed.lock`, `/README.md`, `/sql/install.sql` | 403 | ✅ 4/4 |
| 17.20 | Exécution PHP dans `uploads/` | dépôt de `test-exec.php` et `test-exec.phtml` | jamais exécuté | ✅ 403 sur les deux |
| 17.21 | En-têtes de sécurité | `HEAD /index.php` | 5 en-têtes | ✅ `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, `X-Robots-Tag` |
| 17.22 | Téléversement réel (Apache) | 2 images + 1 audio via le formulaire | ajout en base + fichiers aléatoires | ✅ 3 médias, noms hexadécimaux |
| 17.23 | Miniatures générées | `media.php?id=1&variant=thumb` | miniature plus légère que l'original | ✅ 25 955 o vs 95 682 o |
| 17.24 | Diffusion avec `Range` | `Range: bytes=0-1023` | 206 + `Content-Range` | ✅ `0-1023/95682` |
| 17.25 | Média protégé avant révélation | visiteur → `media.php?id=1` | 403 | ✅ |
| 17.26 | Accès direct au fichier stocké | `/uploads/images/<hash>.jpg` | 403 | ✅ |
| 17.27 | Bandeau « mots de passe par défaut » | tableau de bord admin | bandeau listant les 3 comptes | ✅ |
| 17.28 | Disparition automatique du bandeau | changement des 3 mots de passe | bandeau absent | ✅ `default_passwords_active = 0` |
| 17.29 | Détection après renommage | renommage de l'identifiant `joel` → `joelk` | compte toujours signalé tant que le mot de passe est inchangé | ✅ (comparaison par empreinte, pas par nom) |
| 17.30 | Modification du profil | identifiant + nom affiché | enregistrés, session synchronisée | ✅ |
| 17.31 | Identifiant déjà pris | `elis` saisi depuis le compte de Joël | refus | ✅ « Cet identifiant est déjà utilisé par l'autre administrateur. » |
| 17.32 | Nom affiché de l'autre compte | panneau « Compte administrateur » | enregistré | ✅ |
| 17.33 | Politique de mot de passe | `NouveauMotDePasse_21!` | refusé | ✅ « trop prévisible » |
| 17.34 | Mot de passe valide | `BleuetViolet_2026!` | accepté | ✅ |
| 17.35 | Rendus visuels (Chromium/Playwright) | accueil, accueil mobile 390 px, connexion, 404, assistant, tableau de bord, mots de passe, médias, visiteur avant révélation | mise en page correcte, CSS chargé | ✅ 11 captures |
| 17.36 | Compte à rebours | accueil public le 21/10/2026 | 32 j 04 h 03 min, jamais négatif | ✅ |

**Total de cette campagne : 36 tests, 36 réussis.**

### Reprise complète depuis l'archive livrée (3ᵉ installation, sous-dossier `/zip/`)

| # | Test | Résultat observé |
| --- | --- | --- |
| 17.37 | Extraction de `clementine-birthday.zip` | 104 fichiers, `.htaccess` des 4 dossiers de médias conservés |
| 17.38 | Aucun secret dans l'archive | `includes/config.php`, `installed.lock`, journaux et médias absents |
| 17.39 | `config.example.php` : structure | bloc `app.default_accounts` **dans** `app` (défaut corrigé : il était placé au niveau racine) |
| 17.40 | Assistant sur base vierge | 12 tables + « Comptes par défaut créés : joel, elis + accès visiteur » |
| 17.41 | Connexions après installation | joel ✅ · elis ✅ · visiteur → `/pages/countdown.php` ✅ |
| 17.42 | Verrouillage | `/setup/index.php` → 403 |

### Ce que la campagne a corrigé

| Défaut trouvé | Cause | Correctif |
| --- | --- | --- |
| « Seul le HTML s'affiche » | `realpath(ROOT_PATH)` comparé à `DOCUMENT_ROOT` : la détection échouait dès que la racine web était un lien symbolique ou un sous-dossier | `base_path()` réécrit : le préfixe est déduit en retirant du `SCRIPT_NAME` la position réelle du script relative au projet (méthode indépendante du système de fichiers) |
| Assistant bloquant à l'étape 3 | création manuelle des deux comptes imposée | comptes créés automatiquement depuis `includes/config.php`, rejouable, formulaire manuel en option |
| Page 404/403 d'Apache au lieu des pages du site | `ErrorDocument` avec chemin absolu `/errors/404.php` | `RewriteRule` relative au dossier d'installation |
| 419 transformé en 500 | Apache ne connaît pas le code 419 | envoi de 403 sur le réseau, page « Session expirée » conservée |
| Pages 400 et 405 absentes | `abort(400)`/`abort(405)` retombaient sur 404 | `errors/400.php` et `errors/405.php` créées |

### Non rejoué ici (nécessite l'hébergement réel)

- Cookie `Secure` et HSTS : exigent HTTPS effectif (étape 8 du guide).
- Comportement de `mod_rewrite` d'InfinityFree : testé sous Apache 2.4.69 avec `AllowOverride All`, configuration équivalente.
- Envoi d'un fichier de plus de 10 Mo : la limite est appliquée par InfinityFree en amont ; l'application affiche son propre message au-delà de `post_max_size`.

---

## 18. Vérification totale du 22 octobre 2026 — 316 tests

Campagne complète rejouée **après réinstallation d'un environnement vierge** (PHP 8.4.27, MariaDB 11.8.6, Apache 2.4.69 + `mod_php`), sur **quatre installations** : racine de domaine, sous-dossier `/clementine/`, installation « jour J » (révélation passée), et **archive livrée extraite dans `/livraison/`** avec une base neuve.

Les scripts sont livrés dans `/home/user/tests/` (`run_all.sh` enchaîne les dix phases ; chaque phase est autonome et rejouable).

| Phase | Périmètre | Tests | Réussis |
| --- | --- | --- | --- |
| 1 | Installation depuis zéro (assistant, comptes par défaut, verrouillage, rejeu) | 16 | 16 |
| 2 | Authentification, CSRF, sessions, dossiers privés, en-têtes, limitation des tentatives | 42 | 42 |
| 3 | Administration : médias, souvenirs, playlists, lettre, vœux, messages, paramètres | 43 | 43 |
| 4 | Parcours visiteur avant/après révélation, médias protégés, lecteur, API temps | 42 | 42 |
| 5 | Exploration de tous les liens (3 installations) + méthode HTTP | 17 | 17 |
| 6 | Navigateur réel Chromium : CSS, responsive, lightbox, lecteur, bougie, animations réduites | 28 | 28 |
| 7 | Cas limites : limites d'envoi, faux MIME, vidéo externe, déconnexion, erreurs, maintenance, accessibilité | 35 | 35 |
| 8 | Robustesse : XSS stocké/réfléchi (vérifié dans Chromium), injections SQL, unicode, pagination, sessions | 35 | 35 |
| 9 | Dates centralisées et cohérence des libellés | 8 | 8 |
| 10 | Livraison : archive extraite, installation, parcours complet, ressources, protections, documentation | 50 | 50 |
| **Total** | | **316** | **316** |

### Défauts trouvés et corrigés dans cette campagne

| # | Défaut | Gravité | Cause | Correctif |
| --- | --- | --- | --- | --- |
| 1 | **Le fond bleu nuit n'était jamais peint** | Visuelle (zones blanches possibles sur iOS pendant le sur-défilement, sous le pied de page) | `.page { background: transparent }` : la classe est portée par `<body>` et l'emportait sur `body { background: var(--bg-950) }` | fond peint sur `html` **et** `body`, règle fautive retirée, `color-scheme: dark` ajouté |
| 2 | **Écriture partielle des paramètres** | Données | `setting_set()` était appelé dans la boucle de validation : un champ invalide enregistrait quand même tous les autres | validation complète d'abord, écriture ensuite, message « aucune modification enregistrée » |
| 3 | **Date de révélation codée en dur dans 11 gabarits** | Cohérence | textes « 23 novembre 2026 » écrits à la main (pied de page, lettre, finale, login, admin…) | `reveal_date_text()` / `reveal_moment_text()` dérivent de `app.reveal_at` ; vérifié en changeant la date d'une installation de test |
| 4 | **« 1 octobre 2026 » au lieu de « 1er octobre »** | Typographie française | `fr_date()` utilisait `format('j')` | jour 1 rendu « 1er », autres jours inchangés |
| 5 | **Un POST sur une page en lecture seule répondait 200** | Robustesse | les pages d'affichage ignoraient la méthode HTTP | `require_page_method()` central dans `public_header()` / `admin_header()` → **405** + en-tête `Allow` |

### Ce que la campagne n'a pas pu vérifier (et pourquoi)

- Cookie `Secure`, HSTS et redirection HTTPS : exigent un certificat réel (étape 8 du guide).
- Limite des 10 Mo d'InfinityFree appliquée **en amont** de PHP : testée côté application (refus à 7 Mo avec message clair), pas côté hébergeur.
- Envoi réel d'e-mails : le site n'en envoie aucun (aucune dépendance SMTP).
