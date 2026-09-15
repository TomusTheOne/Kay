# Architecture SEO — kaydiving.com

Objectif : un site vitrine qui **capte la recherche d'intention** du plongeur avant et pendant
son séjour à Tulum, et qui la convertit en réservation sur le site.

La concurrence à Tulum est nombreuse mais le niveau SEO y est faible : beaucoup de sites lents,
en une seule langue, sans données structurées, avec des pages « nos tarifs » qui ne répondent à
aucune question. C'est un terrain gagnable.

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
/                     EN  (x-default)
/es/                  ES
/fr/                  FR
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
