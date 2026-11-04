# Architecture SEO — kaydiving.com

Objectif : un site vitrine qui **capte la recherche d'intention** du plongeur avant et pendant
son séjour à Tulum, et qui la convertit en réservation sur le site.

La concurrence à Tulum est nombreuse mais le niveau SEO y est faible : beaucoup de sites lents,
en une seule langue, sans données structurées, avec des pages « nos tarifs » qui ne répondent à
aucune question. C'est un terrain gagnable.

---

## 0. Ce qui est réellement en ligne au lancement

Ce document décrit la cible. Voici l'état exact du site tel qu'il part en
production — le reste est la feuille de route, pas une description.

**Livré :**

| | |
|---|---|
| Pages | **trois** : `/en/`, `/es/`, `/fr/` |
| `canonical` | auto-référent sur `/en/` |
| `hreflang` | `en` · `es` · `fr` réciproques + `x-default` vers `/en/` |
| `sitemap.xml` | généré, `<lastmod>` piloté par `contentUpdated` |
| `robots.txt` | `Disallow: /api/` seulement, et il déclare le sitemap |
| JSON-LD | `WebSite` + `WebPage` + `SportsActivityLocation` + `ItemList`/`Product`/`Offer` + `FAQPage`, tous liés par `@id` |
| Open Graph | carte **1200×630 JPEG** par langue, générée par `site/scripts/og.mjs` |
| Hôte canonique | `www` → apex en 301 *(dans `.htaccess`)* |
| HTTPS | forcé en 301, HSTS 2 ans |
| Manifest + icônes | `site.webmanifest`, apple-touch 180, 192, 512 |
| Pages de paiement | `noindex, nofollow`, et **volontairement pas** dans `robots.txt` |

**Pas encore livré, et pourquoi :**

- ~~`/es/` et `/fr/` ne sont pas publiées~~ — **elles le sont maintenant.** Les
  trois locales sont dans `content/products.json → publishedLocales`, et
  `build-deploy.mjs` refuse toujours de construire si une locale y figure alors
  que son fichier de messages est encore marqué `_translated: false`. Le garde
  reste utile pour la prochaine langue.
- **Les pages intérieures** (`/cenote-diving/angelita/`, `/courses/...`,
  `/journal/...`) décrites plus bas n'existent pas. Le site est une page unique
  avec des ancres. C'est suffisant pour ouvrir ; c'est le principal levier de
  croissance ensuite.
- **`AggregateRating` / `Review`** : rien, tant qu'il n'y a pas de vrais avis
  vérifiables. Des avis inventés en JSON-LD sont une violation des règles Google.
- **`openingHoursSpecification`** : manquant, on n'a pas encore les horaires.
- **Photos** : les sources sont des exports Instagram (861×531 au plus grand).
  La carte Open Graph est donc légèrement suragrandie. Des originaux haute
  définition amélioreraient à la fois le partage et le rendu sur grand écran.

---

## 1. Le principe directeur

Les gens ne cherchent pas « centre de plongée Tulum ». Ils cherchent :

- *cenote diving Tulum price* — intention transactionnelle
- *do you need to be certified to dive a cenote* — intention informationnelle
- *Dos Ojos vs Dreamgate* — intention comparative
- *Angelita cenote depth* — intention factuelle
- *best cenote for beginners Tulum* — intention de recommandation

**Une page par intention.** Une page « Tarifs » ne rankera jamais sur ces requêtes.
Une page *Angelita — 30 m, Advanced, le nuage d'hydrogène sulfuré* le peut, et elle convertit
mieux parce qu'elle arrive sur quelqu'un qui sait déjà ce qu'il veut.

C'est pourquoi les maquettes B et C contiennent déjà un **index des cenotes** : c'est le squelette
d'une trentaine de pages à fort potentiel.

---

## 2. Architecture d'URL et internationalisation

Sous-répertoires par langue, sur un seul domaine (cumule l'autorité, contrairement aux sous-domaines) :

```
/          → 302 → /en/     la racine ne sert rien elle-même
/en/                        EN  (x-default)
/es/                        ES  — pas encore publiée
/fr/                        FR  — pas encore publiée
```

Anglais en langue par défaut : c'est la langue de réservation du tourisme plongée à Tulum.
Espagnol pour le marché local et mexicain, français parce que Tulum reçoit beaucoup de
francophones et que la concurrence francophone y est déjà installée.

```
/cenote-diving/                      pilier — l'offre cenotes
/cenote-diving/dos-ojos/             une page par site
/cenote-diving/angelita/
/cenote-diving/the-pit/
/cenote-diving/casa-cenote/
/cenote-diving/dreamgate/
/cenote-diving/calavera/
/reef-diving-tulum/                  pilier — récif
/courses/                            pilier — formations
/courses/open-water/
/courses/advanced/
/courses/cavern-diver/
/journal/<slug>/                     contenu éditorial
/book/                               centre de réservation
/about/  /contact/
```

**`hreflang` réciproque** sur chaque page (déjà en place dans les trois maquettes), y compris
`x-default`. Chaque variante linguistique a son propre `canonical` pointant sur elle-même.

⚠️ Traduire réellement — pas de traduction automatique non relue. Une page ES médiocre
peut faire plus de mal que pas de page ES.

---

## 3. Modèles de titres et de H1

| Type de page | `<title>` | `<h1>` |
|---|---|---|
| Accueil | `Cenote Diving in Tulum \| Kay Diving — Small-Group Cavern & Reef Dives` | accroche de marque |
| Site de plongée | `Diving Cenote Angelita, Tulum — 30 m, Advanced \| Kay Diving` | `Cenote Angelita` |
| Cours | `PADI Open Water Course in Tulum — 4 Days \| Kay Diving` | `Open Water` |
| Article | `Cavern vs Cave Diving: What You're Actually Allowed to Do` | idem |

Règles : ≤ 60 caractères utiles, le mot-clé en tête, la marque en fin, **un seul `<h1>` par page**,
et une `meta description` écrite pour le clic (elle n'est pas un facteur de ranking direct mais
elle pilote le CTR, qui compte).

---

## 4. Données structurées (JSON-LD)

Déjà implémenté dans les maquettes :

- **`SportsActivityLocation`** (sous-type de `LocalBusiness`) — nom, adresse, `geo`, téléphone,
  horaires, `priceRange`, `sameAs` vers Instagram. C'est le socle du SEO local.
- **`FAQPage`** — les questions de la FAQ, éligibles aux résultats enrichis.
- **`ItemList` / `Product` + `Offer`** (concepts B et C) — chaque plongée avec son prix et sa devise.

À ajouter lors de la construction du site complet :

- **`BreadcrumbList`** sur toutes les pages intérieures.
- **`Course`** sur les pages formation.
- **`Article`** sur le journal, avec `author`, `datePublished`, `dateModified`.
- **`AggregateRating`** + **`Review`** — **uniquement avec de vrais avis vérifiables**.
  Des avis inventés en JSON-LD sont une violation des règles Google et peuvent coûter
  une pénalité manuelle. À brancher sur Google Business Profile ou TripAdvisor.

---

## 5. Performance — priorité n° 2 du brief, et facteur de ranking

**Mesuré sur le build de production**, page `/en/` en 390 px, CPU bridé ×4 :

| | FCP | LCP | CLS |
|---|---|---|---|
| Réseau rapide | ~400 ms | **~400 ms** | **0.000** |
| 4G lente (1,6 Mb/s, 150 ms de latence) | ~1 900 ms | **~1 900 ms** | **0.000 – 0.007** |

Le CLS résiduel (0,007 sur 10 chargements sur 12) est le seul décalage réel qui
subsiste : le titre du hero est en Instrument Serif à 74 px, et quand la police
se substitue à la police de repli, les deux boutons et la bande de données
dessous descendent de quelques pixels. C'est quatorze fois sous le seuil de
Google.

Les seuils « bon » de Google sont LCP < 2 500 ms et CLS < 0,1 : les deux sont
tenus, y compris dans le scénario dégradé. Poids total au premier chargement :
**312 Ko gzippés** (152 Ko de JS, 107 Ko de polices, 20 Ko de HTML, 8 Ko de CSS).
Ces chiffres tiennent aux polices auto-hébergées par `next/font` et aux
`width`/`height` posés sur chaque image. Un décalage quatre fois plus gros
(0,030) venait du voile de caustiques du hero, dimensionné en pourcentage de
son parent : quand le titre se recomposait, sa boîte était recalculée. Il est
maintenant dimensionné en `svh`, donc indépendant du contenu.

**Responsive, vérifié par la mesure** de 320 px à 2560 px — treize tailles, plus
le paysage téléphone et les deux orientations d'iPad : aucun défilement
horizontal, aucun débordement, aucun texte tronqué. Le contenu se reflue aussi
à 400 % de zoom (critère WCAG 1.4.10). Sur pointeur tactile, les champs du
formulaire sont à 16 px — en dessous, Safari iOS zoome tout seul au focus et ne
revient pas — et le compteur de plongeurs passe à 44 × 44.


Budget cible (mobile, 4G) :

| Métrique | Cible |
|---|---|
| LCP | < 1,8 s |
| INP | < 150 ms |
| CLS | < 0,05 |
| JS total | < 30 Ko |
| Poids page (hors photos) | < 60 Ko gzip |

Ce que les maquettes font déjà :

- **Aucun framework.** ~100 lignes de JS par concept, chargées en `defer`.
- **Aucune requête bloquante hors polices.** Un seul CSS, un seul JS.
- `width` / `height` sur toutes les images → **CLS ≈ 0**.
- `fetchpriority="high"` + `preload` sur l'image LCP, `loading="lazy"` partout ailleurs.
- Illustrations SVG : 6 Ko gzip pour l'ensemble.
- `prefers-reduced-motion` respecté partout.

À faire avant mise en ligne :

- **Auto-héberger les polices** en `woff2` sous-ensemblé (latin + latin-ext) avec `font-display: swap`.
  Google Fonts coûte deux connexions tierces — c'est le principal gain restant sur le LCP.
- **Photos de Kay en AVIF + WebP**, `srcset` responsive, jamais plus de 1 800 px de large.
- Inliner le CSS critique du hero, différer le reste.
- CDN avec Brotli, cache long sur les assets versionnés.
- `sitemap.xml` (une entrée par langue) + `robots.txt`.

---

## 6. SEO local — le levier le plus rentable

Pour un centre de plongée, le pack local Google pèse souvent plus que le référencement organique.

1. **Google Business Profile** complet : catégorie *Dive shop* / *SCUBA instructor*, horaires,
   photos réelles, attributs, et surtout **les avis** — solliciter systématiquement après chaque
   sortie, répondre à tous.
2. **NAP cohérent** (Name, Address, Phone) à l'identique partout : site, GBP, TripAdvisor,
   Instagram, annuaires plongée. Toute incohérence dilue le signal.
3. **Citations** : PADI/SSI dive shop locator, TripAdvisor, Google Maps, annuaires de plongée
   mexicains, offices de tourisme de Quintana Roo.
4. Page **`/contact/`** avec adresse en texte (pas seulement dans une image), carte intégrée en
   `loading="lazy"`, et le même `LocalBusiness` en JSON-LD.

---

## 7. Le moteur de contenu

Le journal n'est pas de la décoration : c'est ce qui capte la recherche informationnelle
3 à 12 semaines **avant** la réservation, quand le voyageur planifie.

Premiers sujets, classés par rapport valeur / difficulté :

1. *Cavern vs cave diving: what your certification actually allows* — forte intention, faible concurrence
2. *The best month to dive cenotes in Tulum* — saisonnier, capte la planification
3. *Halocline explained: why the water goes blurry* — curiosité, très partageable
4. *Dos Ojos vs Dreamgate: which cenote should you dive?* — comparatif, convertit très bien
5. *Cenote diving prices in Tulum, explained honestly* — transactionnel, capte les comparateurs
6. *What to bring for a day of cenote diving* — pratique, réservé aux clients déjà convaincus

Chaque article **lie vers la page du cenote concerné**, qui lie vers `/book/`. C'est le maillage
interne qui transforme le trafic informationnel en réservations.

---

## 8. Mesure

- **Google Search Console** dès le jour 1 — c'est la seule source fiable sur les requêtes réelles.
- **Analytics respectueux de la vie privée** (Plausible, Umami) plutôt que GA4 : plus léger,
  pas de bandeau cookie, pas de conformité RGPD à gérer. Cohérent avec la priorité vitesse.
- Suivre : réservations envoyées / sessions, position moyenne sur le groupe « cenote diving Tulum »,
  et les Core Web Vitals en données terrain.

### Ce qui est câblé

**Le comptage maison, toujours actif.** Chaque page envoie un signal à
`/api/track.php`, enregistré dans la base de Kay et affiché dans l'onglet
*Trafic* de l'admin : sources, pays, pages, appareils, et le tunnel
visiteurs → formulaire vu → paiement ouvert → acompte payé. Sans cookie ni
adresse IP, donc sans bandeau. Détails dans [`ADMIN.md`](ADMIN.md).

Les fournisseurs tiers ci-dessous restent optionnels, en complément.

`components/Analytics.tsx` émet la balise du fournisseur choisi, ou **rien du
tout** si `ANALYTICS_PROVIDER` est vide — c'est le défaut, et ce n'est pas un
placeholder : une build non configurée ne fait aucune requête tierce.

Trois fournisseurs, tous sans cookie et autour de 1 Ko : `umami`, `plausible`,
`cloudflare`. Changer d'avis, c'est une variable GitHub et un redéploiement.

**Le tunnel.** `lib/analytics.ts` envoie un seul événement personnalisé,
`checkout-opened`, juste avant la redirection vers Mercado Pago. Avec la vue
de page que `/booking/thanks/` produit déjà au retour, trois nombres
s'alignent par pays : visiteurs → checkouts ouverts → acomptes payés.

Propriétés envoyées : `product`, `dives`, `divers`, `depositMxn`, `locale`.
Ce qui a été réservé, jamais qui l'a réservé — ni nom, ni email.

L'envoi est borné à 400 ms et se résout dans tous les cas, y compris si le
fournisseur ne répond jamais : mesuré à 224 ms de redirection avec un
fournisseur qui répond, 687 ms avec un fournisseur muet, 213 ms sans
fournisseur du tout. Une panne d'analytics ne doit jamais coûter une
réservation.

Cloudflare Web Analytics ne gère que les vues de page, pas les événements
personnalisés : avec ce fournisseur, le tunnel se réduit à deux nombres.

GA4 a été écarté volontairement : ~90 Ko de JavaScript sur une page qui n'en
charge aucun d'origine tierce, et des cookies qui obligent à un bandeau de
consentement pour la moitié européenne des clients de Kay — un bandeau
par-dessus le hero, sur l'écran autour duquel tout le design est construit.

### Vérification Search Console

Passer par l'**enregistrement DNS TXT** chez OVH, pas par la balise ni par le
fichier HTML. Deux raisons :

1. Il crée une *propriété de domaine* : apex, `www`, http, https et les trois
   langues dans un seul rapport. Une propriété par préfixe d'URL en ferait
   trois, voire six.
2. `/` renvoie un 302 vers `/en/`. Une vérification par balise ou par fichier
   sur la racine a donc une redirection en travers.

Et surtout : **ne jamais déposer le fichier de vérification par FTP.** Le
déploiement synchronise `www/` avec `mirror --delete` — tout fichier absent de
la build est supprimé au déploiement suivant, y compris celui-là. S'il faut
absolument passer par un fichier, il va dans `site/public/`, versionné.

La balise reste disponible pour qui préfère : variable `GSC_VERIFICATION`.

---

## 9. Arbitrage entre les trois concepts

Les trois ont le même socle technique, donc le même **plancher** SEO. Ils diffèrent par leur **plafond** :

| | Plafond SEO | Pourquoi |
|---|---|---|
| **A — Xibalba** | Bon | Excellent sur la marque et le partage social ; peu de surface de contenu structuré en page d'accueil. |
| **B — Luz** | Très bon | L'index des cenotes et le journal sont des surfaces de contenu assumées dans le design. |
| **C — Bitácora** | **Le meilleur** | Le contenu est factuel, structuré et en forme de réponse — exactement ce que Google sert sur `profondeur`, `niveau requis`, `prix`. Les fiches sites se transforment directement en pages de destination. |

Cela dit : **le SEO est la priorité n° 3**. Un concept qui fait réserver 20 % de visiteurs en plus
bat un concept qui attire 20 % de visiteurs en plus. Et rien n'empêche de greffer l'index de C
sur la page d'accueil de A — voir les pistes d'hybridation dans [DESIGN.md](DESIGN.md).
