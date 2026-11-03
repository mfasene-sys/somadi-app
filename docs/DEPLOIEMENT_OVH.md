# Déploiement sur l'hébergement OVH Pro

Ce guide installe la nouvelle application SOMADI **à côté** de l'ancienne, sur la **même base MySQL**.
L'ancienne application continue de fonctionner pendant toute la transition : la nouvelle ne crée que des tables préfixées `app_` et ne modifie ni la structure ni les données des tables existantes.

**Configuration de référence** (votre hébergement) :

| Élément | Valeur |
|---|---|
| PHP | 8.4 |
| Dossier de l'application | `/home/somadix` |
| Dossier racine du site (multisite) | `/home/somadix/public` |

Durée estimée : 30 à 45 minutes la première fois.

---

## 0. Ce qu'il vous faut

- L'accès à l'espace client OVH (hébergement Pro).
- Les identifiants FTP/SSH de l'hébergement (l'offre Pro inclut l'accès SSH).
- Les identifiants de la base MySQL utilisée par l'ancienne application (serveur `xxxxx.mysql.db`, nom de base, utilisateur, mot de passe). On les trouve dans le fichier de configuration de l'ancienne application (`includes/config.php` ou équivalent) ou dans l'espace client, rubrique **Bases de données**.
- L'archive `somadi-lot1.zip` livrée. Elle contient déjà toutes les dépendances : aucune commande `composer` n'est nécessaire sur le serveur.

## 1. Sauvegarder la base (obligatoire)

Avant toute installation, faites une sauvegarde de la base de production :

- Espace client OVH → **Hébergements** → votre hébergement → onglet **Bases de données** → `…` à côté de la base → **Créer une sauvegarde**.
- Ou en SSH : `mysqldump -h xxxxx.mysql.db -u UTILISATEUR -p NOM_BASE > ~/sauvegarde_avant_lot1.sql`

## 2. Vérifier le domaine et le dossier racine

Recommandé : un sous-domaine dédié, par exemple `app.votre-domaine.fr`. L'ancienne application reste sur son adresse actuelle.

Espace client → votre hébergement → onglet **Multisite** :

1. Le domaine ou sous-domaine de la nouvelle application doit avoir pour **dossier racine** `public`, c'est-à-dire `/home/somadix/public`.
2. **SSL** doit être activé (certificat HTTPS, nécessaire pour l'installation sur téléphone).

Le code de l'application est placé **au-dessus** de ce dossier, dans `/home/somadix`. Seul le contenu de `public` est accessible depuis Internet ; le code, le fichier `.env` et les journaux ne le sont pas.

## 3. Vérifier la version de PHP

PHP 8.4 est déjà en place sur votre hébergement : rien à changer. L'application fonctionne avec PHP 8.3 ou plus récent.

> **Ne remplacez pas** le fichier `/home/somadix/.ovhconfig` existant : il règle PHP pour tout l'hébergement, y compris l'ancienne application. Le fichier `ovh/ovhconfig-reference` de l'archive n'est fourni qu'à titre d'exemple.

## 4. Envoyer les fichiers

L'archive contient un dossier `somadi`. C'est **son contenu** qu'il faut placer dans `/home/somadix`, pas le dossier lui-même.

1. Décompressez `somadi-lot1.zip` sur votre ordinateur.
2. Vérifiez que `/home/somadix/public` est vide sur le serveur (ou qu'il ne contient rien d'utile) : il va recevoir les fichiers publics de l'application.
3. Par FTP (FileZilla) ou SFTP, envoyez **le contenu** du dossier `somadi` dans `/home/somadix`.

Vous devez obtenir :

```
/home/somadix/
├── app/
├── artisan
├── bootstrap/
├── config/
├── public/          ← dossier racine du site
│   ├── index.php
│   ├── .htaccess
│   ├── css/ js/ icons/
│   └── sw.js, manifest.webmanifest
├── storage/
├── vendor/
├── .env.example
└── …
```

Les dossiers déjà présents dans `/home/somadix` (par exemple `www/` pour l'ancienne application) ne sont pas concernés : ne les supprimez pas.

> Pensez à afficher les fichiers cachés dans FileZilla (menu **Serveur** → **Forcer l'affichage des fichiers cachés**) pour envoyer aussi `public/.htaccess` et `.env.example`.

## 5. Configurer l'application

En SSH :

```bash
ssh UTILISATEUR@ssh.clusterXXX.hosting.ovh.net
cd /home/somadix
php -v          # doit afficher 8.4 (sinon : /usr/local/php8.4/bin/php à la place de php)
cp .env.example .env
```

Éditez `.env` (avec `nano .env`) et renseignez :

| Clé | Valeur |
|---|---|
| `APP_URL` | `https://app.votre-domaine.fr` |
| `APP_TIMEZONE` | `Africa/Dakar` (GMT) |
| `DB_TIMEZONE` | `+00:00` (heure des sessions MySQL, alignée sur Dakar) |
| `DB_HOST` | `xxxxx.mysql.db` |
| `DB_DATABASE` | nom de la base de l'ancienne application |
| `DB_USERNAME` / `DB_PASSWORD` | identifiants de cette base |

Puis générez la clé de chiffrement de l'application :

```bash
php artisan key:generate
```

Si la commande `php` en SSH n'est pas en 8.4, remplacez `php` par `/usr/local/php8.4/bin/php` dans toutes les commandes de ce guide.

## 6. Installer

```bash
php artisan somadi:installer --force
```

Cette commande :
- crée uniquement les tables `app_migrations`, `app_permissions`, `app_journal`, `app_requetes` et `app_lots_options` ;
- charge la matrice des droits par défaut (modifiable ensuite à l'écran) ;
- lance un contrôle de la base, **en lecture seule**, qui signale :
  - les tables qui ne sont pas en InnoDB (écart EC23) ;
  - le nombre de déclencheurs présents (50 attendus) ;
  - les utilisateurs dont le rôle est inconnu ;
  - l'absence d'administrateur actif ;
  - l'absence des lignes de verrou utilisées par les déclencheurs (`modInfra_verrou`, `modCompta_exercice_lock`).

S'il n'existe aucun administrateur actif, créez-en un :

```bash
php artisan somadi:creer-admin votre.adresse@exemple.fr --nom=Nom --prenom=Prénom
```

Enfin, optimisez pour la production :

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## 7. Vérifier

1. Ouvrez `https://app.votre-domaine.fr` : la page de connexion s'affiche.
2. Connectez-vous avec un compte existant de l'ancienne application : **les mots de passe actuels fonctionnent**.
3. Vérifiez que les noms accentués (é, è, ç…) s'affichent correctement dans **Utilisateurs**. Si vous voyez des caractères comme `Ã©`, ne modifiez rien et signalez-le : cela indique un encodage hérité à traiter avant les lots suivants.
4. Vérifiez que `https://app.votre-domaine.fr/.env` renvoie une erreur 404 : le fichier de configuration ne doit pas être accessible.
5. Sur téléphone, ouvrez l'adresse dans Chrome (Android) ou Safari (iPhone), puis **Ajouter à l'écran d'accueil** pour installer l'application.

En cas de page blanche ou d'erreur 500, consultez `/home/somadix/storage/logs/` et vérifiez que le dossier `storage/` est accessible en écriture.

## 8. Passer du lot 1 au lot 2

Si le lot 1 est déjà installé :

1. Sauvegarde de la base (étape 1).
2. `php artisan down`
3. Envoyez le contenu de la nouvelle archive dans `/home/somadix` en **remplaçant** les fichiers existants, mais **sans toucher** à `.env` ni au dossier `storage/`. Le plus sûr : envoyez `somadi-lot2.zip`, puis en SSH :
   ```bash
   cd /home/somadix
   unzip -q -o somadi-lot2.zip
   rm -rf vendor app resources/views
   cp -a somadi/. /home/somadix/   # .env n'est pas dans l'archive ; storage/ n'y contient que des dossiers vides
   rm -rf somadi somadi-lot2.zip
   ```
4. Dans `.env`, vérifiez ou ajoutez :
   ```
   APP_TIMEZONE=Africa/Dakar
   DB_TIMEZONE=+00:00
   ```
5. `php artisan somadi:installer --force` (crée `app_requetes` et `app_lots_options`, sans toucher au reste), puis :
   ```bash
   php artisan optimize:clear
   php artisan config:cache && php artisan route:cache && php artisan view:cache
   php artisan up
   ```
6. Sur les téléphones, l'application installée se met à jour seule au prochain chargement.

## 9. Passer du lot 2 au lot 3

Même méthode qu'au point 8, avec `somadi-lot3.zip` :

1. **Sauvegarde de la base** (étape 1) : le lot 3 écrit dans les ventes et la caisse.
2. En SSH :
   ```bash
   cd /home/somadix
   php artisan down
   unzip -q -o somadi-lot3.zip
   rm -rf vendor app resources/views
   cp -a somadi/. /home/somadix/
   rm -rf somadi somadi-lot3.zip
   php artisan somadi:installer --force
   php artisan optimize:clear
   php artisan config:cache && php artisan route:cache && php artisan view:cache
   php artisan somadi:verifier
   php artisan up
   ```
   Aucune nouvelle table : le lot 3 utilise les tables de ventes, de trésorerie, de réservations et de clôture déjà présentes. `somadi:verifier` doit afficher la ligne de verrou comptable et la date jusqu'à laquelle les exercices sont clôturés.
3. Contrôles rapides après mise en ligne :
   - **Ventes** : la liste affiche vos ventes existantes, avec leur reste à encaisser.
   - **Rapprochements** : la liste montre les ventes dont le reste ne correspond pas aux règlements liés. Il est normal d'en trouver dans l'historique ; elles se corrigent une par une, avec un motif.
   - **Bilan et clôture** : choisissez l'année précédente. Les contrôles indiquent ce qui empêcherait la clôture. **Ne clôturez qu'après avoir vérifié le bilan** : une clôture est définitive.
4. La clôture produit un PDF stocké dans la base (quelques centaines de Ko). Si elle échoue avec un message sur la taille des paquets MySQL, signalez-le : l'offre Pro autorise normalement 16 Mo.

## 10. Passer du lot 3 au lot 4

Même méthode, avec `somadi-lot4.zip` :

1. **Sauvegarde de la base** (étape 1).
2. En SSH :
   ```bash
   cd /home/somadix
   php artisan down
   unzip -q -o somadi-lot4.zip
   rm -rf vendor app resources/views
   cp -a somadi/. /home/somadix/
   rm -rf somadi somadi-lot4.zip
   mkdir -p storage/app/medias-ovins && chmod 775 storage/app/medias-ovins
   php artisan somadi:installer --force
   php artisan optimize:clear
   php artisan config:cache && php artisan route:cache && php artisan view:cache
   php artisan somadi:verifier
   php artisan up
   ```
   Aucune nouvelle table : le lot 4 utilise les tables `OVINS_*` existantes.
3. **Photos et vidéos des ovins.** Les médias déjà enregistrés par l'ancienne application sont relus dans son dossier. Si l'ancienne application n'est pas dans `/home/somadix/www`, indiquez son dossier dans `.env` :
   ```
   SOMADI_RACINE_ANCIENNE=/home/somadix/www
   ```
   puis relancez `php artisan config:cache`. Les nouveaux médias sont rangés dans `/home/somadix/storage/app/medias-ovins` (à inclure dans vos sauvegardes de fichiers).
4. **Taille des vidéos.** L'application accepte des vidéos jusqu'à 50 Mo, mais PHP peut fixer une limite plus basse. Si l'envoi d'une vidéo échoue, vérifiez `upload_max_filesize` et `post_max_size` dans la configuration PHP de l'hébergement (espace client OVH).
5. Contrôles rapides : **Ovins → Troupeau** affiche vos animaux ; **Lots** montre l'écart éventuel entre l'effectif de chaque lot et le nombre de fiches ; **Transferts et sorties** signale, pour l'administrateur, les anciennes ventes ovines à rapprocher.

## 11. Passer du lot 4 au lot 5

Même méthode, avec `somadi-lot5.zip` :

1. **Sauvegarde de la base** (étape 1).
2. En SSH :
   ```bash
   cd /home/somadix
   php artisan down
   unzip -q -o somadi-lot5.zip
   rm -rf vendor app resources/views
   cp -a somadi/. /home/somadix/
   rm -rf somadi somadi-lot5.zip
   mkdir -p storage/app/marketing && chmod 775 storage/app/marketing
   php artisan somadi:installer --force
   php artisan optimize:clear
   php artisan config:cache && php artisan route:cache && php artisan view:cache
   php artisan somadi:verifier
   php artisan up
   ```
   `somadi:installer` crée **une seule nouvelle table**, `app_infra_planning` : le planning des emplacements (EC01), car les tables `modInfra_planning` attendues par l'ancien code n'existent pas dans la base. Les autres modules utilisent les tables existantes (`modInfra_*`, `modAct_*`, `rh_*`, `modMkt_*`).
3. Contrôles rapides :
   - **Bâtiments et parcs** : les bâtiments existants s'affichent ; ceux marqués « À configurer » doivent recevoir un code, un site et une famille avant de pouvoir accueillir des sujets.
   - **Activités** : vos tâches, équipes et routines existantes apparaissent.
   - **Personnel et congés** : les employés et leurs soldes s'affichent.
   - **Marketing WhatsApp** : les modèles et campagnes existants apparaissent.
4. Les images des modèles marketing de l'ancienne application sont relues dans son dossier (`SOMADI_RACINE_ANCIENNE`, comme pour les photos d'ovins) ; les nouvelles sont rangées dans `storage/app/marketing`.

## 12. Authentification renforcée (code par e-mail, empreinte)

Après cette mise à jour, **le mot de passe seul ne suffit plus** : un code à 6 chiffres est envoyé par e-mail (PC de bureau, ou téléphone sans empreinte). Sur téléphone, l'empreinte peut remplacer mot de passe et code. Détails : [`AUTHENTIFICATION.md`](AUTHENTIFICATION.md).

**Avant de mettre en ligne, l'envoi d'e-mails doit fonctionner**, sinon personne ne pourra se connecter.

1. L'hébergement mutualisé refuse les connexions SMTP sortantes (`Connection refused` sur `ssl0.ovh.net:465`, constaté le 03/11/2026), et l'appel direct à `sendmail` aussi. Les codes partent donc par la fonction PHP `mail()` (`MAIL_MAILER=phpmail`). La console SSH n'a pas le droit d'envoyer : `somadi:tester-email` y échoue, testez depuis le site (Administration → Utilisateurs → « Tester l'envoi d'e-mail »). Dans l'espace client, onglet **Scripts e-mail** de l'hébergement, vérifiez qu'il est actif. L'expéditeur doit être une adresse de votre domaine, par exemple `no-reply@votre-domaine`.
2. Dans `.env`, ajoutez ou complétez :
   ```ini
   MAIL_MAILER=phpmail
   MAIL_FROM_ADDRESS="no-reply@votre-domaine"
   MAIL_FROM_NAME="SOMADI Farming"
   SOMADI_PIN_EMAIL=true
   SOMADI_EMPREINTE=true
   ```
   `APP_URL` doit être l'adresse exacte du site en `https://` : les empreintes sont rattachées à ce domaine.
3. **Sauvegarde de la base** (étape 1). L'archive `somadi-authentification-diff.zip` est **différentielle** : elle ne contient que les fichiers ajoutés ou modifiés depuis le lot 5 (liste dans `LISTE_FICHIERS.txt`). Aucun fichier n'est supprimé. Le dossier `vendor` existant est conservé, seule la bibliothèque `vendor/lbuchs/webauthn` y est ajoutée. En SSH :
   ```bash
   cd /home/somadix
   unzip -q -o somadi-authentification-diff.zip            # crée le dossier maj-auth/
   # Copie de sécurité des fichiers qui vont être remplacés (pour revenir en arrière)
   tar -czf sauvegarde-avant-auth.tgz --ignore-failed-read $(cat maj-auth/LISTE_FICHIERS.txt) 2>/dev/null
   # Facultatif : vérifier que l'archive est intacte
   (cd maj-auth/fichiers && sha256sum -c --quiet ../SHA256SUMS.txt) && echo "Archive intacte"
   php artisan down
   cp -a maj-auth/fichiers/. /home/somadix/
   php artisan somadi:installer --force
   php artisan optimize:clear
   php artisan config:cache && php artisan route:cache && php artisan view:cache
   php artisan somadi:tester-email votre@adresse
   php artisan somadi:verifier
   php artisan up
   rm -rf maj-auth somadi-authentification-diff.zip
   ```
   `somadi:installer` crée deux tables : `app_codes_connexion` (codes à usage unique, stockés sous forme d'empreinte HMAC) et `app_empreintes` (clés **publiques** des téléphones). Aucune table existante n'est modifiée ; l'ancienne application continue de se connecter comme avant.
   Revenir en arrière : `php artisan down`, `tar -xzf sauvegarde-avant-auth.tgz`, supprimer les fichiers marqués « ajouté » dans `LISTE_FICHIERS_DETAIL.txt`, puis les trois commandes `…:cache` et `php artisan up`. Plus simple encore, en cas de souci d'envoi d'e-mails : `SOMADI_PIN_EMAIL=false` (voir plus bas).
   Si vous installez à partir du dépôt GitHub plutôt que de l'archive, lancez `composer install --no-dev --optimize-autoloader` : une bibliothèque a été ajoutée (`lbuchs/webauthn`).
4. Contrôles : `somadi:tester-email` doit arriver (vérifiez les indésirables) ; connectez-vous depuis un PC (code reçu ?), puis depuis un téléphone : Mon profil → « Activer sur ce téléphone », déconnexion, « Se connecter avec mon empreinte ».

**En cas de panne de l'envoi d'e-mails** : mettez `SOMADI_PIN_EMAIL=false` dans `.env` puis `php artisan config:cache`. La connexion revient au mot de passe seul ; les empreintes continuent de fonctionner. Remettez `true` dès que l'envoi est rétabli.

## 13. Mettre à jour (lots suivants)

1. Sauvegarde de la base (étape 1).
2. `php artisan down` (page de maintenance).
3. Remplacement des fichiers par ceux du nouveau lot dans `/home/somadix`, **en conservant** `.env` et le dossier `storage/`.
4. `php artisan somadi:installer --force` puis les trois commandes `…:cache`.
5. `php artisan up`.

## 14. Revenir en arrière

La nouvelle application n'a pas modifié la structure des tables existantes. Les saisies faites avec elle (ponte, lots, soins…) sont enregistrées dans les tables habituelles et restent visibles dans l'ancienne application. Pour la retirer complètement :
- retirez le domaine du multisite et supprimez les dossiers et fichiers ajoutés dans `/home/somadix` à l'étape 4 (en gardant `www/` et vos autres dossiers) ;
- facultatif : `DROP TABLE app_journal, app_permissions, app_requetes, app_lots_options, app_infra_planning, app_codes_connexion, app_empreintes, app_migrations;`

L'ancienne application n'est pas affectée.

## Points d'attention

- **Ne lancez jamais** `php artisan migrate:fresh`, `migrate:reset` ou `db:wipe` sur la base de production : ces commandes suppriment toutes les tables, y compris celles de l'ancienne application. L'application les bloque en mode production, mais restez prudent.
- Les sessions et le cache sont stockés en fichiers (`storage/framework/`) : aucune table supplémentaire.
