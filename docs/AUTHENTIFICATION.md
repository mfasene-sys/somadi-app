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

## Réglages (`.env`)

| Variable | Défaut | Rôle |
|---|---|---|
| `SOMADI_PIN_EMAIL` | `true` | Code par e-mail après le mot de passe |
| `SOMADI_EMPREINTE` | `true` | Connexion par empreinte sur téléphone |
| `SOMADI_WEBAUTHN_DOMAINE` | domaine d'`APP_URL` | Domaine de rattachement des empreintes |
| `MAIL_*` | — | Envoi SMTP (OVH : `ssl0.ovh.net`, port 465, `smtps`) |

## Vérifications

- 29 nouveaux tests automatisés (`tests/Feature/AuthentificationRenforceeTest.php`) et `ConnexionTest` adapté. Les tests d'empreinte utilisent un authentificateur logiciel qui produit de vraies signatures ECDSA, vérifiées par le serveur comme en production : activation, connexion, rejeu, clé copiée, autre origine, compte inactif, retraits.
- Parcours navigateur avec Chromium : sur PC, mot de passe + code ; sur téléphone émulé (Pixel 7) avec un capteur virtuel, connexion par code, proposition d'activation, activation, déconnexion, puis connexion par empreinte.
