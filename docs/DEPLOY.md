# Mise en ligne — tout chez OVH

Le site est **exporté en HTML statique** et les deux endpoints de paiement sont
écrits en **PHP 8.2**. Tout tourne sur ton hébergement STARTER existant, avec la
base MySQL déjà incluse. Aucun hébergeur supplémentaire — le seul service
extérieur est l'envoi des e-mails de confirmation (§6), gratuit, et qui ne sert
qu'à ça.

```
/home/xxx/kay-config.php     ← les secrets, JAMAIS servis par le web
/home/xxx/www/               ← la racine web (le déploiement écrit ici)
        ├── .htaccess
        ├── en/  es/  fr/    ← les pages
        ├── assets/  _next/
        └── api/
            ├── booking.php  ← crée la réservation et ouvre le paiement
            ├── webhook.php  ← reçoit la confirmation Mercado Pago, envoie les e-mails
            ├── lib/         ← config, pricing, db, mercadopago, mail, notify
            ├── messages/    ← les textes, les mêmes que ceux du site
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
| `SITE_URL` | `https://kaydiving.com` — sans slash final |
| `FTP_SERVER_DIR` | `/www/` — ou `/www/kaydiving/` si multisite |

> ⚠️ `FTP_SERVER_DIR` doit pointer **exactement** sur le bon dossier. Le
> déploiement synchronise ce répertoire : mal réglé, il écraserait un de tes
> autres sites. `kay-config.php` est au-dessus, il n'est jamais touché.

Déploiement manuel possible : onglet *Actions* → *Build and deploy to OVH* →
*Run workflow*.

### Ou à la main

```bash
cd site
NEXT_PUBLIC_SITE_URL=https://kaydiving.com npm run build:deploy
# puis envoyer le contenu de site/out/ dans www/ par FileZilla
```

---

## 5. Mercado Pago

1. [Panneau développeur](https://www.mercadopago.com.mx/developers/panel) → créer une application
2. Récupérer le **jeton d'accès de production**
3. **Webhooks** → URL : `https://kaydiving.com/api/webhook.php`
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

## 6. Les e-mails de confirmation

Dès qu'un acompte est encaissé, deux e-mails partent : la confirmation au
plongeur, et la ligne de feuille de route à Kay (`contact@kaydiving.com`).

**Pourquoi pas le `mail()` de PHP.** Il fait partir le message depuis le serveur
web d'OVH, pas depuis la boîte `contact@kaydiving.com`. Rien ne signe donc le
message pour `kaydiving.com` : pas de DKIM, un SPF qui ne s'aligne pas, et Gmail
classe une confirmation de réservation en spam une fois sur deux. En prime, on
n'a aucune trace : quand un plongeur écrit « je n'ai jamais rien reçu », on n'a
rien à lui répondre.

**Deux choix, tous les deux gratuits.** `mail_provider` dans `kay-config.php` :

| | `resend` *(par défaut)* | `brevo` |
|---|---|---|
| Gratuit | 100/jour, 3 000/mois | 300/jour, à vie |
| Mention ajoutée | **aucune** | « Sent with Brevo » en bas |
| Domaines vérifiés | 3 sur le gratuit | 1 |
| Enregistrements DNS | sur `send.kaydiving.com` | sur `kaydiving.com` |
| Interface | orientée développeur, en anglais | complète, en français |

Pour un centre de plongée, 100/jour comme 300/jour sont très au-dessus du besoin
(deux e-mails par réservation, donc ~50 réservations/jour avant de toucher le
plafond). Basculer de l'un à l'autre, c'est une ligne dans `kay-config.php` — le
code parle aux deux, et le runner de tests vérifie les deux formats de requête.

### Mise en place (Resend)

1. Créer le compte sur [resend.com](https://resend.com)
2. **Domains** → *Add Domain* → `kaydiving.com`, région `us-east-1` ou `eu-west-1`
   *(l'Europe si tu préfères que les données restent dans l'UE)*
3. Resend affiche trois enregistrements à coller dans **OVH → Domaines →
   kaydiving.com → Zone DNS**. Recopie ceux que ton tableau de bord affiche, pas
   ceux d'un tutoriel : les valeurs sont propres à ton compte. En général :

   | Type | Nom | Rôle |
   |---|---|---|
   | `MX` | `send.kaydiving.com` | chemin de retour (bounces) |
   | `TXT` | `send.kaydiving.com` | SPF du sous-domaine d'envoi |
   | `TXT` | `resend._domainkey.kaydiving.com` | signature DKIM |

4. Attendre le passage en **Verified** (quelques minutes, jusqu'à 24 h)
5. **API Keys** → *Create API Key*, droit **Sending access** suffit → la reporter
   dans `mail_api_key`

> ✅ **C'est précisément pourquoi Resend va mieux ici.** Ton domaine a déjà une
> boîte chez OVH (MX Plan), donc un `MX` et sans doute un `SPF` sur la racine.
> Resend pose son `MX` et son `SPF` sur le sous-domaine `send.kaydiving.com` :
> la racine n'est pas touchée du tout. Rien à fusionner, rien à casser, ta boîte
> `contact@kaydiving.com` continue de recevoir exactement comme avant. Seul le
> DKIM s'ajoute à la racine, et un DKIM ne rentre en conflit avec rien.
>
> Les e-mails partent quand même **de** `contact@kaydiving.com` : c'est le chemin
> de retour technique qui passe par `send.`, pas l'adresse affichée.

### Si tu préfères Brevo

Même principe, mais les enregistrements se posent sur la racine, donc attention :
s'il existe déjà un `SPF` (`v=spf1 ...`), **ne pas en créer un second** — il ne
peut y en avoir qu'un, il faut fusionner les deux dans la même ligne. Mettre
`mail_provider => 'brevo'` et la clé API v3 dans `mail_api_key`.

### Tant que ce n'est pas configuré

Sans `mail_api_key`, le site prend quand même les réservations : l'envoi est
journalisé (`kay: mail not configured, would have sent: ...`) et le paiement
aboutit normalement. C'est volontaire — une clé manquante ne doit jamais coûter
une réservation. Mais avant d'ouvrir au public, le plongeur doit recevoir sa
confirmation.

### Tester

```bash
cd site && php php/tests/run.php   # le rendu des deux e-mails est couvert
```

Pour un vrai envoi, fais une réservation de test Mercado Pago de bout en bout :
l'e-mail part au moment où le webhook passe la ligne en `paid`.

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

82 assertions : chaque prix du menu, le refus d'un total envoyé par le client,
les tailles non vendues, les dates passées, l'idempotence du règlement, les
signatures invalides, et le contenu des deux e-mails — le bon produit, les trois
montants qui s'additionnent, le ramassage payé, et le fait qu'un `<script>` tapé
dans le formulaire ressorte en texte, plus le format de requête attendu par
chacun des deux fournisseurs d'e-mail. Il faut une base MySQL joignable et un
`kay-config.php`.

---

## Avant d'ouvrir au public

- [x] Réponses de Kay intégrées *(38 m max, Discover Scuba 7 m, ramassage payant)*
- [x] Téléphone, point de rendez-vous et e-mail réels dans le pied de page
- [ ] Adresse postale et horaires d'ouverture *(toujours manquants)*
- [x] Une seule langue en ligne (`publishedLocales`), pour ne pas publier trois
      fois le même texte anglais sous trois `hreflang`
- [ ] Traductions **ES** et **FR**, puis les ajouter à `publishedLocales`
- [x] Carte Open Graph 1200×630, `hreflang`, `canonical`, sitemap, JSON-LD
- [ ] DNS du domaine pointé sur l'hébergement, et **`www` résolu aussi**
      *(le `.htaccess` le redirige vers l'apex, encore faut-il qu'il existe)*
- [ ] Paiement testé de bout en bout avec les cartes de test
- [x] E-mails de confirmation développés et testés
- [ ] Clé API d'envoi renseignée et domaine authentifié *(§6)* — sans elle, le
      plongeur paie et ne reçoit rien
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
