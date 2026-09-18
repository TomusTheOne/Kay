# Mise en ligne

## La contrainte à connaître d'abord

Le site est une application **Next.js**. Elle a besoin d'un **runtime Node.js** pour
faire tourner deux choses côté serveur :

- `/api/booking` — crée la réservation et ouvre le paiement Mercado Pago
- `/api/mp-webhook` — reçoit la confirmation de paiement

**L'hébergement mutualisé OVH est du PHP** : il ne peut pas les exécuter. Sans runtime
Node, on perd le paiement en ligne — c'est-à-dire la raison pour laquelle on a choisi
Next.js plutôt qu'Astro.

**Le nom de domaine, lui, ne pose aucun problème.** Un domaine chez OVH pointe où on
veut : c'est juste du DNS. Rien de ce qui suit n'oblige à le déplacer.

---

## Option A — Domaine chez OVH, application sur Vercel *(recommandé)*

Vercel est l'éditeur de Next.js : le déploiement est natif, gratuit à ce volume,
avec HTTPS automatique et un CDN mondial. Aucune administration système.

### 1. Déployer

1. Aller sur [vercel.com](https://vercel.com), se connecter avec le compte GitHub
2. **Add New → Project**, choisir le dépôt `TomusTheOne/Kay`
3. **Root Directory** : `site` *(important — l'app n'est pas à la racine du dépôt)*
4. Renseigner les variables d'environnement (voir plus bas)
5. **Deploy**

### 2. Brancher le domaine OVH

Dans Vercel : **Settings → Domains → Add**, saisir le domaine.
Vercel affiche alors les enregistrements à créer.

Dans l'espace client OVH : **Noms de domaine → *votre domaine* → Zone DNS**

| Type | Sous-domaine | Cible |
|---|---|---|
| `A` | *(laisser vide)* | `76.76.21.21` |
| `CNAME` | `www` | `cname.vercel-dns.com.` |

> Vercel affiche les valeurs exactes à utiliser — **prendre celles-là**, pas celles
> ci-dessus si elles diffèrent. Elles changent parfois.

Compter de quelques minutes à quelques heures de propagation. Le certificat HTTPS
est émis automatiquement ensuite.

### 3. Ce qu'on garde chez OVH

Le domaine, les e-mails, tout le reste. Seule l'application tourne ailleurs.

---

## Option B — Tout chez OVH, sur un VPS

Possible, mais ce n'est **pas** l'hébergement mutualisé : il faut un **VPS** (à partir
d'environ 5 €/mois) ou l'offre **Cloud Web**, qui supportent Node.js.

Un `Dockerfile` est fourni dans `site/`.

```bash
# sur le VPS, après avoir installé Docker
git clone https://github.com/TomusTheOne/Kay.git && cd Kay/site
docker build --build-arg NEXT_PUBLIC_SITE_URL=https://ton-domaine.com -t kaydiving .
docker run -d --restart=always -p 3000:3000 --env-file .env.production kaydiving
```

Il reste ensuite à installer **Nginx** en reverse proxy devant le port 3000, et
**certbot** pour le certificat HTTPS.

### Ce que ça implique vraiment

C'est de l'administration système, à ta charge et dans la durée :

- mises à jour de sécurité du serveur
- renouvellement du certificat TLS (automatisable, mais à surveiller)
- redémarrage de l'app si elle tombe
- sauvegardes
- pas de CDN mondial : les visiteurs mexicains taperont un serveur en Europe

**Si tu n'as pas envie de maintenir un serveur, prends l'option A.** Le domaine reste
chez OVH dans les deux cas.

---

## Base de données

Il faut un **PostgreSQL** accessible depuis l'application.

| Option | Remarque |
|---|---|
| **Neon** *(recommandé)* | Serverless, offre gratuite suffisante ici, se met en veille tout seul. 2 minutes à créer. |
| **Supabase** | Gratuit aussi, plus de fonctionnalités que nécessaire. |
| **OVH Web Cloud Databases** | Payant, mais tout reste chez OVH si tu y tiens. |

Une fois l'URL obtenue, appliquer le schéma :

```bash
cd site
DATABASE_URL="postgres://..." npm run db:migrate
```

---

## Variables d'environnement

À renseigner sur l'hébergeur (Vercel : *Settings → Environment Variables*).

| Variable | Valeur |
|---|---|
| `NEXT_PUBLIC_SITE_URL` | `https://ton-domaine.com` — sans slash final |
| `DATABASE_URL` | la chaîne de connexion Postgres |
| `MP_ACCESS_TOKEN` | jeton Mercado Pago (**production**, pas le jeton de test) |
| `MP_WEBHOOK_SECRET` | secret de signature du webhook |
| `DEPOSIT_RATE` | `0.3` — part payée à la réservation |
| `USD_TO_MXN` | taux de conversion, ex. `17.5` |

---

## Mercado Pago, côté production

1. [Panneau développeur](https://www.mercadopago.com.mx/developers/panel) → créer une application
2. Récupérer le **jeton d'accès de production**
3. **Webhooks** → ajouter l'URL : `https://ton-domaine.com/api/mp-webhook`
4. Cocher l'événement **Paiements**
5. Copier le **secret de signature** → c'est `MP_WEBHOOK_SECRET`

> Sans ce secret, **toutes** les notifications sont rejetées avec un 401. C'est voulu :
> le webhook vérifie la signature avant de croire quoi que ce soit, sinon n'importe qui
> pourrait marquer une réservation comme payée.

### Tester avant d'ouvrir au public

Mercado Pago fournit des **comptes de test** et des **cartes de test**. Faire une
réservation complète de bout en bout et vérifier que la ligne passe bien en `paid` :

```sql
select product, dives, dive_date, divers, status, paid_at from bookings order by created_at desc limit 5;
```

---

## Avant d'ouvrir

- [ ] Les réponses de Kay sur les profondeurs sont intégrées *(40 m, Discover Scuba)*
- [ ] Téléphone, adresse et horaires réels remplacent les valeurs provisoires
- [ ] Traductions **ES** et **FR** faites — aujourd'hui les deux affichent l'anglais
- [ ] Paiement testé de bout en bout avec les cartes de test Mercado Pago
- [ ] Un e-mail de confirmation existe *(pas encore développé — `TODO(email)`)*
- [ ] Google Search Console : propriété ajoutée, sitemap soumis
- [ ] Fiche Google Business Profile créée et cohérente avec le site (nom, adresse, téléphone)
- [ ] Les droits sur les photos sont confirmés si certaines sont des reposts
