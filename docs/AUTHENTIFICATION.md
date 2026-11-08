# Authentification renforcée

Mise à jour du 2 novembre 2026. Installation : section 12 du [guide OVH](DEPLOIEMENT_OVH.md).

## Ce qui change pour les utilisateurs

| Appareil | Connexion |
|---|---|
| PC de bureau | Adresse e-mail + mot de passe, puis **code à 6 chiffres reçu par e-mail** |
| Téléphone, empreinte activée | **Empreinte seule** (bouton « Se connecter avec mon empreinte ») |
| Téléphone, empreinte pas encore activée | Comme sur PC (mot de passe + code), puis proposition d'activer l'empreinte |

Activer l'empreinte : sur le téléphone, **Mon profil → Connexion par empreinte**, saisir son mot de passe, puis poser le doigt. Le bouton n'apparaît que sur un téléphone ou une tablette équipés d'un capteur.

## Règles

**Code par e-mail**
- 6 chiffres, valable 10 minutes, usage unique. Un nouveau code annule le précédent.
- 5 essais par code. Au-delà, il faut ressaisir le mot de passe.
- « Renvoyer un code » : 3 fois par quart d'heure. Au total, 10 codes par compte et par heure au maximum.
- Le code n'est jamais stocké en clair (empreinte HMAC dans `app_codes_connexion`).
- L'e-mail précise : si vous n'êtes pas à l'origine de la demande, quelqu'un connaît votre mot de passe.

**Empreinte (norme WebAuthn, « clés d'accès »)**
- L'empreinte reste dans le téléphone. SOMADI n'enregistre que la clé **publique** créée par le téléphone (`app_empreintes`). À chaque connexion, le téléphone signe un défi aléatoire à usage unique, et le serveur vérifie la signature.
- L'activation redemande le mot de passe, pour qu'une session laissée ouverte ne suffise pas à ajouter un téléphone.
- Le téléphone doit vérifier lui-même l'utilisateur (empreinte, visage, ou à défaut son code de verrouillage). Seul le capteur intégré est accepté : pas de clé USB.
- La clé est liée au domaine (`APP_URL`). Une réponse venant d'un autre site est refusée. Un compteur détecte une clé copiée.
- Un compte désactivé ne peut plus se connecter, même avec une empreinte.
- Chaque utilisateur voit ses appareils dans Mon profil et peut en retirer un. Un administrateur peut retirer toutes les empreintes d'un compte (fiche utilisateur), par exemple en cas de téléphone perdu ou de départ.

**Journal** : `connexion.code_envoye`, `connexion.code_refuse`, `connexion.code_non_envoye`, `connexion.code_bloque`, `connexion.reussie` (avec la méthode `mot_de_passe+code` ou `empreinte`), `empreinte.activation`, `empreinte.activation_refusee`, `empreinte.retrait`, `empreinte.retrait_admin`, `connexion.empreinte_refusee`.

## Limites à connaître

- **Téléphones partagés** : le serveur ne sait pas *quel* doigt a été posé, seulement que le téléphone a reconnu une empreinte enregistrée sur lui. N'activer l'empreinte que sur un téléphone personnel.
- **Envoi d'e-mails** : sans SMTP qui fonctionne, plus personne ne se connecte par mot de passe. `somadi:verifier` le signale. En production, un envoi `log` est refusé avec un message clair. Interrupteur de secours : `SOMADI_PIN_EMAIL=false`.
- **Changement de domaine** : chacun devra réactiver son empreinte.
- **Ancienne application** : elle garde sa connexion par mot de passe seul, tant qu'elle reste en ligne.
- La distinction PC / téléphone se fait dans le navigateur. Elle sert l'ergonomie, pas la sécurité : quel que soit l'appareil, une connexion exige soit mot de passe + code, soit une empreinte activée.

## Application d'authentification et codes de secours (08/11/2026)

Alternative au code par e-mail, choisie par chaque utilisateur dans **Mon profil → Application d'authentification**.

- **Activation** : installer Google Authenticator ou Microsoft Authenticator (gratuites), scanner le QR code (ou saisir la clé à la main), puis confirmer avec le code affiché et le mot de passe. Le QR code est fabriqué par l'application elle-même : la clé ne passe par aucun service extérieur.
- **Connexion** : après le mot de passe, on saisit le code à 6 chiffres affiché par l'application (il change toutes les 30 s, un décalage de 30 s de l'horloge est toléré). **Aucun e-mail n'est envoyé.** Le lien « Recevoir plutôt un code par e-mail » reste disponible, et « Utiliser l'application » permet de revenir.
- **Codes de secours** : 10 codes à usage unique (`XXXXX-XXXXX`), affichés une seule fois à l'activation, à imprimer ou télécharger. On les saisit via « Utiliser un code de secours » quand ni le téléphone ni l'e-mail ne sont disponibles. Quand il en reste 3 ou moins, l'application invite à en créer de nouveaux (Mon profil, avec le mot de passe ; les anciens cessent alors de fonctionner).
- **Désactivation** par l'utilisateur (mot de passe), ou **retrait par l'administrateur** sur la fiche utilisateur (téléphone perdu sans codes de secours). Changement de téléphone : désactiver, puis réactiver depuis le nouveau.
- **Sécurité** : la clé est chiffrée avec `APP_KEY` (table `app_authentificateurs`), les codes de secours ne sont conservés que sous forme d'empreinte HMAC (`app_codes_secours`). Un code de l'application n'est accepté qu'une fois. Les essais sont limités : 5 par demande de connexion, 10 par quart d'heure et par compte. L'application reste exigée même si `SOMADI_PIN_EMAIL=false`.
- **Attention** : changer `APP_KEY` rend les clés et les codes de secours inutilisables. Chacun devrait alors réactiver l'application (en se connectant avec le code par e-mail, après retrait par l'administrateur).
- Journal : `authentificateur.activation`, `authentificateur.codes_secours`, `authentificateur.desactivation`, `authentificateur.retrait_admin`, `connexion.code_secours`, et `connexion.code_refuse` (type `application` ou `secours`).

## Réglages (`.env`)

| Variable | Défaut | Rôle |
|---|---|---|
| `SOMADI_PIN_EMAIL` | `true` | Code par e-mail après le mot de passe |
| `SOMADI_EMPREINTE` | `true` | Connexion par empreinte sur téléphone |
| `SOMADI_AUTHENTIFICATEUR` | `true` | Application d'authentification et codes de secours (`false` masque la rubrique et revient au code par e-mail pour tous) |
| `SOMADI_WEBAUTHN_DOMAINE` | domaine d'`APP_URL` | Domaine de rattachement des empreintes |
| `MAIL_MAILER` | `phpmail` chez OVH | Envoi par la fonction PHP `mail()`, seul moyen autorisé sur l'hébergement mutualisé (SMTP sortant et sendmail refusés). Test : Administration → Utilisateurs → « Tester l'envoi d'e-mail » |

## Vérifications

- 29 nouveaux tests automatisés (`tests/Feature/AuthentificationRenforceeTest.php`) et `ConnexionTest` adapté. Les tests d'empreinte utilisent un authentificateur logiciel qui produit de vraies signatures ECDSA, vérifiées par le serveur comme en production : activation, connexion, rejeu, clé copiée, autre origine, compte inactif, retraits.
- Parcours navigateur avec Chromium : sur PC, mot de passe + code ; sur téléphone émulé (Pixel 7) avec un capteur virtuel, connexion par code, proposition d'activation, activation, déconnexion, puis connexion par empreinte.
