# L'espace administrateur — `kaydiving.com/admin/`

Un back-office pour Kay : les réservations, les clients (CRM) et le trafic du
site, sur ordinateur comme sur téléphone. Même hébergement OVH, même base
MySQL, même PHP que les paiements — rien de plus à installer ni à payer.

| Page | Ce qu'on y fait |
|---|---|
| **Tableau de bord** | Plongeurs des 7 prochains jours, réservations et encaissements du mois (comparés au mois dernier), reste à encaisser, relances à faire, paiements non aboutis, chiffre d'affaires par mois, visiteurs sur 30 jours |
| **Planning** | La feuille de route d'un jour : qui plonge, à quel départ, niveau, ramassage, ce qu'il reste à encaisser, lien WhatsApp. Bandeau des 14 prochains jours. **Imprimable** |
| **Réservations** | À venir, passées, paiements en attente, annulées. Recherche (nom, e-mail, téléphone, référence), filtre par sortie et par dates, **export CSV** |
| **Fiche réservation** | Changer le statut, modifier (date, départ, plongeurs, transport…), enregistrer un paiement (acompte, solde, remboursement), renvoyer l'e-mail de confirmation, historique |
| **Nouvelle réservation** | Pour ce qui arrive par WhatsApp, téléphone ou au comptoir. Prix calculé depuis le catalogue, acompte en espèces possible, e-mail de confirmation en option |
| **Clients** | Tous ceux qui ont réservé ou ouvert un paiement. Segments (clients, prospects, plongée à venir, fidèles), étiquettes, tri par valeur, **export CSV** |
| **Fiche client** | Coordonnées, pays, niveau, étiquettes, notes permanentes, toutes ses réservations, suivi (notes, appels, WhatsApp, relances datées), effacement RGPD |
| **Trafic** | Visiteurs, pages vues, rebond, tunnel visiteur → formulaire → paiement → acompte, sources, pays, pages, langues, appareils, campagnes |
| **Compte** | Mot de passe, sessions ouvertes, accès pour d'autres personnes |

---

## Mise en route (une seule fois, avec ton logiciel FTP)

Il n'y a **aucun fichier existant à modifier** : on ajoute seulement un petit
fichier texte sur le serveur, à côté de `kay-config.php`. Une faute de frappe
dedans ne peut rien casser — au pire, la page d'installation dit qu'elle ne
trouve pas la phrase.

**1. Préparer le fichier sur ton ordinateur**

- Windows : ouvre le **Bloc-notes**. Mac : ouvre **TextEdit**, puis menu
  *Format → Convertir au format texte*.
- Écris **une phrase de ton invention, d'au moins 20 caractères** — un
  souvenir de plongée que toi seul connais, par exemple. Pas une phrase copiée
  d'ici ou d'ailleurs : elle sert de clé. Note-la, tu vas la retaper.
- Enregistre sous le nom **`kay-admin-token.txt`**, sur le Bureau par exemple.
  *(Si Windows l'enregistre en `kay-admin-token.txt.txt`, ce n'est pas grave :
  ce nom-là est accepté aussi.)*

**2. L'envoyer sur le serveur avec FileZilla** (ou Cyberduck, WinSCP… le
principe est le même)

- Connecte-toi comme d'habitude : à gauche ton ordinateur, à droite le serveur.
- À droite, reste au **premier niveau** : c'est là que tu vois le dossier
  `www` **et** le fichier `kay-config.php`. **N'entre pas dans `www`.**
- Fais glisser `kay-admin-token.txt` depuis la gauche vers la droite, dans
  cette liste, à côté de `kay-config.php`.

```
/                         ← ici, le premier niveau
├── kay-config.php
├── kay-admin-token.txt   ← le fichier va ici
└── www/                  ← pas dedans
```

> Pourquoi pas dans `www` ? Tout ce qui est dans `www` peut être téléchargé
> par n'importe qui, et le déploiement automatique efface à chaque mise à jour
> ce qu'il n'a pas mis lui-même. Si le fichier s'y retrouve par erreur, la
> page d'installation le refuse et explique quoi faire.

**3. Créer le compte**

- Ouvre `https://kaydiving.com/admin/` dans ton navigateur.
- Tape la même phrase, ton nom, ton e-mail et un mot de passe (10 caractères
  minimum). C'est le premier compte.
- **Les tables se créent toutes seules** à ce moment-là : rien à faire dans
  phpMyAdmin.

**4. Ensuite (facultatif)** — tu peux supprimer `kay-admin-token.txt` du
serveur (clic droit → *Supprimer* dans FileZilla). Tant qu'il est là, sa
phrase permet de réinitialiser un mot de passe oublié ; la page *Compte* te
le rappelle. Tu pourras toujours le remettre le jour où tu en as besoin.

Pour donner accès à quelqu'un d'autre : *Compte* → *Donner accès à
quelqu'un*. Tous les comptes ont les mêmes droits.

**Mot de passe oublié** : un autre compte peut en définir un nouveau depuis
*Compte*. Sinon, remets `kay-admin-token.txt` sur le serveur comme à l'étape
2, puis ouvre `https://kaydiving.com/admin/setup.php` : la page propose de
choisir un nouveau mot de passe.

*(Pour qui préfère : la même phrase peut aussi être mise dans
`kay-config.php`, clé `admin_setup_token`. Déconseillé si tu modifies ce
fichier à la main : une virgule ou un guillemet de travers l'empêche d'être
lu, et les réservations avec.)*

---

## Au quotidien

**Les statuts d'une réservation**

| Statut | Veut dire | Qui le pose |
|---|---|---|
| Paiement en attente | Le plongeur a ouvert Mercado Pago, rien n'est encore payé | le site |
| Acompte payé | L'acompte de 30 % est encaissé en ligne | Mercado Pago (webhook) |
| Confirmée | Réservation prise par Kay (WhatsApp, téléphone, comptoir) | l'admin |
| Effectuée | La plongée a eu lieu | l'admin |
| Absent | Le client n'est pas venu — l'acompte reste acquis | l'admin |
| Annulée / Remboursée | … | l'admin, ou Mercado Pago |

Seules les transitions qui ont un sens sont proposées. « Marquer remboursée »
**enregistre** un remboursement : l'argent, lui, se rend depuis le tableau
de bord Mercado Pago.

**L'argent.** Une réservation a un **total** (toujours calculé depuis
`products.json`, jamais tapé à la main) et reçoit :

- l'acompte en ligne, écrit par Mercado Pago, qu'on ne modifie jamais ici ;
- les paiements saisis : acompte en espèces, solde payé sur le bateau,
  remboursement. Chacun garde son montant, son moyen, sa date et son auteur.

*Reste à payer* = total − tout ce qui a été reçu. Il apparaît sur le
planning, la liste, la fiche, et en total sur le tableau de bord.

**Paiements non aboutis.** Le tableau de bord liste ceux qui ont ouvert le
paiement ces 14 derniers jours sans payer, **et n'ont pas réservé depuis**.
Ce sont des gens qui voulaient plonger : un message suffit souvent.

**Relances.** Dans le suivi d'un client, choisir « À faire » avec une date :
la relance apparaît sur le tableau de bord (en rouge si elle est en retard)
jusqu'à ce qu'on la coche.

**Les clients se créent tout seuls.** Chaque réservation faite sur le site
est rattachée au client qui a le même e-mail, dès la prochaine ouverture de
l'admin. Le client garde ce que Kay a saisi (nom, téléphone, notes) ; seul
le niveau de certification est mis à jour par une nouvelle réservation.

**Effacement RGPD.** Sur la fiche client : le nom, l'e-mail, le téléphone
et les notes disparaissent de la fiche et de ses réservations. Les dates et
montants restent, pour la comptabilité.

---

## Le suivi du trafic

Chaque page du site envoie un petit signal (`/api/track.php`) au serveur de
Kay, qui l'enregistre dans sa propre base. **Aucun service tiers, aucun
cookie, aucune adresse IP enregistrée** — donc pas de bandeau de
consentement, et le site reste aussi rapide (un signal de quelques centaines d'octets,
envoyé après l'affichage avec `navigator.sendBeacon`).

| Ce qui est gardé | Ce qui ne l'est pas |
|---|---|
| la page, la langue du site | l'adresse IP |
| la source (Google, Instagram, lien direct, `utm_…`) | le chemin complet de l'URL d'origine (seul le domaine est gardé) |
| le pays, **déduit du fuseau horaire** de l'appareil | les paramètres de l'URL, sauf `utm_*` |
| mobile / tablette / ordinateur, navigateur, système | tout cookie, tout identifiant durable |
| la langue du navigateur (2 lettres) | nom, e-mail — jamais |

**Comment un visiteur est compté sans être identifié.** Une empreinte
`sha256(clé du jour, IP, navigateur)`, tronquée à 16 caractères. La clé est
tirée au hasard chaque jour et **détruite** le lendemain : impossible de
relier deux jours entre eux, ou de retrouver l'IP à partir de l'empreinte,
même avec la base en main. Conséquence assumée : quelqu'un qui revient trois
jours compte trois visiteurs. Les chiffres sont des « visiteurs par jour,
additionnés ».

**Le pays** vient du fuseau horaire : un Canadien déjà à Tulum compte pour
le Mexique. Moins précis qu'une géolocalisation IP, mais sans base GeoIP à
maintenir sur l'hébergement, et sans rien qui identifie la personne.

**Le tunnel.** Quatre nombres alignés sur la même période :
visiteurs → ont vu le formulaire (fait défiler jusqu'à la réservation) → ont
ouvert le paiement → ont payé l'acompte (lu dans la table des réservations,
pas dans les statistiques).

**Pas comptés** : les robots (Googlebot, aperçus de liens WhatsApp, etc.),
les navigateurs sans interface, et **Kay lui-même** — se connecter à l'admin
pose un cookie `kay_notrack` limité à `/api/`, qui fait ignorer ce navigateur.

**Campagnes.** Ajouter `?utm_source=instagram&utm_campaign=promo-mai` au
lien partagé : la source et la campagne apparaissent dans *Trafic*.

Les données de plus de 400 jours sont effacées au fil de l'eau.

Les fournisseurs tiers de `components/Analytics.tsx` (Umami, Plausible,
Cloudflare) restent disponibles et désactivés par défaut ; les deux peuvent
coexister.

---

## Sécurité

- **Sessions** en base, pas en fichiers : le cookie contient 32 octets
  aléatoires, la base n'en garde que l'empreinte SHA-256. Cookie `HttpOnly`,
  `Secure`, `SameSite=Lax`, limité à `/admin/`. Expire après 7 jours sans
  visite et 30 jours au plus. Changer de mot de passe déconnecte les autres
  appareils.
- **Mots de passe** hachés en bcrypt (coût 12). Connexion bloquée 15 minutes
  après 5 échecs sur un compte ou 10 depuis une même adresse ; la phrase de
  `kay-admin-token.txt` est soumise au même blocage, et le fichier n'est
  jamais servi par le web, même déposé par erreur dans `www/`.
- **Chaque formulaire** porte un jeton CSRF propre à la session, et l'origine
  de la requête est vérifiée.
- **Content-Security-Policy stricte** : aucun script ni style en ligne,
  rien d'une autre origine. Pages jamais mises en cache, jamais indexées,
  jamais affichées dans un cadre.
- **Tout ce qui s'affiche est échappé**, y compris dans les graphiques et
  les exports. Les exports CSV neutralisent les cellules qu'un tableur
  exécuterait comme formule (`=`, `+`, `-`, `@`).
- `/admin/_boot.php` (chargé par chaque page) répond 404 s'il est demandé
  directement.

---

## Côté technique

```
php/admin/        les pages          → www/admin/
php/lib/          la bibliothèque    → www/api/lib/   (partagée avec les paiements)
  migrate.php     schéma versionné, appliqué à l'ouverture de l'admin
  auth.php        comptes, sessions, CSRF, blocage
  bookings.php    statuts, recherche, modifications, paiements, chiffres
  crm.php         clients, synchronisation, notes et relances
  traffic.php     enregistrement et rapports du trafic
  view.php        mise en page, formats, graphiques, CSV
php/track.php     le point d'entrée du trafic → www/api/track.php
components/Beacon.tsx, lib/analytics.ts       l'envoi, côté site
```

**Les tables** (créées par `migrate.php`, versions enregistrées dans
`schema_migrations`) : `customers`, `crm_notes`, `booking_payments`,
`admin_users`, `admin_sessions`, `admin_login_attempts`, `page_views`,
`traffic_salts`, et trois colonnes ajoutées à `bookings` (`customer_id`,
`source`, `updated_at`) plus les statuts `confirmed`, `completed`, `no_show`.
Aucune colonne existante n'est modifiée ni supprimée.

**Le chemin du paiement ne dépend pas de l'admin.** `booking.php` écrit
exactement les mêmes colonnes qu'avant : un déploiement qui arrive avant la
première ouverture de l'admin ne peut pas coûter une réservation. Si les
tables du trafic n'existent pas encore, `track.php` journalise et répond 204.

**Un changement dans le webhook** : un paiement approuvé est désormais
enregistré aussi sur une réservation qui n'était plus « en attente » sans
avoir jamais été payée — carte refusée puis nouvelle carte sur la même page
Mercado Pago (le refus avait déjà annulé la réservation, et le paiement
réussi se perdait), paiement OXXO approuvé des jours plus tard, ou
réservation confirmée ou annulée à la main entre-temps. `paid_at IS NULL`
garde l'idempotence ; une réservation remboursée reste remboursée. Voir
`kay_settle_payment()` dans `php/lib/mercadopago.php`.

**Les tests** (`npm test`) couvrent aussi l'admin : migrations rejouables,
connexion et blocage, sessions et CSRF, prix recalculés pour les
réservations manuelles, transitions de statut, grand livre, synchronisation
et segments du CRM, effacement RGPD, anonymat et agrégats du trafic,
échappement des graphiques et des exports.

**En local**, la page de test complète :

```bash
cd site
NEXT_PUBLIC_SITE_URL=https://kaydiving.com npm run build:deploy
cp ../kay-config.php .            # out/api/lib/../../../ = site/
php -S 127.0.0.1:8080 -t out      # puis http://127.0.0.1:8080/admin/
```

(`php -S` ne lit pas `.htaccess` : `/admin/` et `/` ne sont pas redirigés
comme en production, ouvrir `/admin/index.php` et `/en/` directement.)
