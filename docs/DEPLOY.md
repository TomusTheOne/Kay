# Mise en ligne — tout chez OVH

Le site est **exporté en HTML statique** et les deux endpoints de paiement sont
écrits en **PHP 8.2**. Tout tourne sur ton hébergement STARTER existant, avec la
base MySQL déjà incluse. Pas de prestataire supplémentaire.

```
/home/xxx/kay-config.php     ← les secrets, JAMAIS servis par le web
/home/xxx/www/               ← la racine web (le déploiement écrit ici)
        ├── .htaccess
        ├── en/  es/  fr/    ← les pages
        ├── assets/  _next/
        └── api/
            ├── booking.php  ← crée la réservation et ouvre le paiement
            ├── webhook.php  ← reçoit la confirmation Mercado Pago
            ├── lib/
            └── products.json
```

---

## 1. La base de données

Panneau OVH → ton hébergement → **Bases de données** → *Créer une base*.
Note bien : **serveur, nom de la base, utilisateur, mot de passe**.

Puis ouvre **phpMyAdmin** depuis le même écran et exécute le contenu de
`site/php/schema.sql` (onglet *SQL*, copier-coller, exécuter).

Vérifie que la table existe :

```sql
SHOW TABLES;          -- doit afficher "bookings"
DESCRIBE bookings;
```

---

## 2. Le fichier de secrets

Copie `site/kay-config.example.php` en `kay-config.php`, remplis-le, et
envoie-le par FTP **à côté de `www/`, surtout pas dedans**.

C'est volontaire : un fichier au-dessus de la racine web ne peut pas être servi,
même si une règle Apache est mal écrite. Il n'est pas non plus dans git.

---

## 3. Le domaine

Panneau OVH → ton hébergement → onglet **Multisite** → *Ajouter un domaine*.
Pointe-le sur le dossier `www` (ou un sous-dossier si tu héberges plusieurs sites
— tu en as trois sur cette offre).

Active ensuite le **certificat SSL** (onglet *Certificats SSL*). C'est gratuit et
automatique chez OVH. Le `.htaccess` force déjà HTTPS.

---

## 4. Le déploiement automatique

Un workflow GitHub build le site et l'envoie en FTP à chaque push sur `main`.

**Secrets** (GitHub → *Settings* → *Secrets and variables* → *Actions* → *Secrets*) :

| Nom | Valeur |
|---|---|
| `FTP_SERVER` | l'hôte FTP OVH, ex. `ftp.cluster151.hosting.ovh.net` |
| `FTP_USERNAME` | ton identifiant FTP |
| `FTP_PASSWORD` | le mot de passe FTP |

**Variables** (même écran, onglet *Variables*) :

| Nom | Valeur |
|---|---|
| `SITE_URL` | `https://ton-domaine.com` — sans slash final |
| `FTP_SERVER_DIR` | `/www/` — ou `/www/kaydiving/` si multisite |

> ⚠️ `FTP_SERVER_DIR` doit pointer **exactement** sur le bon dossier. Le
> déploiement synchronise ce répertoire : mal réglé, il écraserait un de tes
> autres sites. `kay-config.php` est au-dessus, il n'est jamais touché.

Déploiement manuel possible : onglet *Actions* → *Build and deploy to OVH* →
*Run workflow*.

### Ou à la main

```bash
cd site
NEXT_PUBLIC_SITE_URL=https://ton-domaine.com npm run build:deploy
# puis envoyer le contenu de site/out/ dans www/ par FileZilla
```

---

## 5. Mercado Pago

1. [Panneau développeur](https://www.mercadopago.com.mx/developers/panel) → créer une application
2. Récupérer le **jeton d'accès de production**
3. **Webhooks** → URL : `https://ton-domaine.com/api/webhook.php`
4. Cocher l'événement **Paiements**
5. Copier le **secret de signature**
6. Reporter les deux dans `kay-config.php`

> Sans le secret, **toutes** les notifications sont rejetées avec un 401. C'est
> voulu : le webhook vérifie la signature avant de croire quoi que ce soit, sinon
> n'importe qui pourrait marquer une réservation comme payée.

### Tester avant d'ouvrir

Mercado Pago fournit des comptes et des cartes de test. Fais une réservation
complète, puis vérifie dans phpMyAdmin :

```sql
SELECT product, dives, dive_date, divers, status, paid_at
FROM bookings ORDER BY created_at DESC LIMIT 5;
```

La ligne doit passer de `pending` à `paid`.

---

## Comment c'est protégé

- **Le prix est recalculé côté serveur** à partir des seuls identifiants produit.
  Le navigateur envoie des choix, jamais des montants. Front et back lisent le
  **même `products.json`**, donc la page ne peut pas afficher un prix que le
  serveur n'appliquerait pas.
- **La réservation est écrite avant l'appel à Mercado Pago.** Si le paiement
  échoue, il reste une ligne `cancelled` — sans gravité. L'inverse, un paiement
  sans réservation, ne peut pas arriver.
- **Le webhook vérifie la signature HMAC** et refuse toute notification de plus
  de cinq minutes, pour qu'une notification capturée ne puisse pas être rejouée.
- **La mise à jour est idempotente** (`WHERE status = 'pending'`) : Mercado Pago
  réessaie, et une réservation déjà payée ne peut pas être « dé-payée ».
- **Requêtes préparées** partout, `PDO::ATTR_EMULATE_PREPARES => false`.
- **Les secrets sont au-dessus de la racine web** et hors de git.

## Les tests

```bash
cd site && npm test      # php php/tests/run.php
```

32 assertions : chaque prix du menu, le refus d'un total envoyé par le client,
les tailles non vendues, les dates passées, l'idempotence du règlement, et les
signatures invalides. Il faut une base MySQL joignable et un `kay-config.php`.

---

## Avant d'ouvrir au public

- [ ] Réponses de Kay intégrées *(profondeur 40 m, Discover Scuba 7 m ou 30 ft)*
- [ ] Téléphone, adresse et horaires réels remplacent les valeurs provisoires
- [ ] Traductions **ES** et **FR** — aujourd'hui les deux affichent l'anglais
- [ ] Paiement testé de bout en bout avec les cartes de test
- [ ] E-mail de confirmation *(pas encore développé — `TODO(email)` dans `webhook.php`)*
- [ ] Google Search Console : propriété ajoutée, sitemap soumis
- [ ] Fiche Google Business Profile cohérente avec le site
- [ ] Droits sur les photos confirmés si certaines sont des reposts

---

## Si un jour l'offre STARTER ne suffit plus

OVH la décrit lui-même comme *bridée sur les ressources PHP*. Ça n'a aucune
importance ici — les endpoints sont appelés une fois par réservation, pas à
chaque visite, et les pages sont du HTML statique. Mais si le trafic monte
sérieusement, passer à l'offre **Pro** est un simple changement d'abonnement :
rien dans le code ne bouge.
