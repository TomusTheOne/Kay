# Kay Diving — site vitrine (maquettes)

Trois directions de design **complètes et volontairement très différentes** pour
[kaydiving.com](https://www.instagram.com/kaydivingtulum) — centre de plongée cenotes / récif à Tulum.

Priorités du brief, dans l'ordre : **1. le design · 2. la rapidité de navigation · 3. le SEO**,
avec un **centre de réservation intégré au site**.

---

## Voir les maquettes

Tout est statique : pas de build, pas de dépendances.

```bash
# n'importe quel serveur statique fait l'affaire
python3 -m http.server 8080
# puis http://localhost:8080
```

`index.html` à la racine est la **page de comparaison** : les trois concepts côte à côte,
avec aperçus live, argumentaire et tableau comparatif.

| | Concept | En une phrase |
|---|---|---|
| **A** | [Xibalba](concepts/a-xibalba/) | Cinématographique et mythique. Le scroll est une descente : une jauge de profondeur suit le lecteur de 0 à 40 m et l'eau s'assombrit. **Vend l'émerveillement.** |
| **B** | [Luz](concepts/b-luz/) | Journal de voyage imprimé. Papier, filets, air, photos traitées comme des planches. Quasi zéro animation. **Vend le goût et la confiance.** |
| **C** | [Bitácora](concepts/c-bitacora/) | Le carnet de plongée. Calcaire, encre et un jaune de signalisation sur grille visible. Chaque site annonce sa profondeur, sa visibilité, son niveau. **Vend la compétence.** |

---

## Structure

```
index.html                  page de comparaison des 3 concepts
concepts/
  a-xibalba/   index.html · style.css · app.js
  b-luz/       index.html · style.css · app.js
  c-bitacora/  index.html · style.css · app.js
assets/art/                 6 illustrations SVG + favicon (23 Ko bruts, 6 Ko gzip)
docs/DESIGN.md              parti pris de chaque concept, systèmes typo/couleur
docs/SEO.md                 architecture SEO complète : i18n, données structurées, contenu, perf
```

Chaque concept est **autonome** : son propre CSS, son propre JS, sa propre grille.
Ce ne sont pas trois habillages d'une même maquette — c'est le seul moyen de juger
honnêtement trois directions.

---

## Ce qui est commun aux trois

- **Même contenu, même moteur de réservation, même socle SEO.** La seule variable à juger est le design.
- **Zéro framework.** HTML + CSS + un fichier JS d'une centaine de lignes. Pas de React, pas de jQuery, pas de bundle.
- **Réservation fonctionnelle** : choix du site, date, niveau, nombre de plongeurs, transfert, options,
  et un récapitulatif qui calcule le total en direct. Sans back-end — c'est l'étape « infra ».
- **Accessibilité** : focus visibles, `aria-live` sur les récapitulatifs, `prefers-reduced-motion` respecté,
  navigation clavier, contrastes vérifiés.
- **Responsive** jusqu'à 380 px.
- **SEO** : `title` / `description` uniques, canonical, Open Graph, `hreflang` EN/ES/FR,
  et JSON-LD `SportsActivityLocation` + `FAQPage` (+ `ItemList` de produits sur B et C).

### Poids réel (mesuré, hors webfonts)

| | code (gzip) | images | premier affichage |
|---|---|---|---|
| **A — Xibalba** | 16 Ko | 297 Ko AVIF (12 fichiers, tous en `lazy` sauf le hero) | **33 Ko** |
| B — Luz | 19 Ko | 6 Ko (SVG) | 25 Ko |
| C — Bitácora | 21 Ko | 6 Ko (SVG) | 27 Ko |

Le chiffre qui compte est le **premier affichage** : ce que le navigateur télécharge avant
que la page soit lisible. Sur le concept A c'est 33 Ko — le code plus la seule photo du hero
(16 Ko en AVIF). Les onze autres images sont en `loading="lazy"` et n'arrivent que si on
descend. Les illustrations restantes sont des **SVG dessinés à la main**, sans licence à gérer.

---

## Important : ce qui est réel, ce qui est provisoire

**Provisoire — à confirmer avec Kay avant toute mise en ligne :**

- tous les **tarifs**, les horaires, le **numéro de téléphone** et l'**adresse postale** ;
- toute affirmation sur l'**agence de certification** (PADI / SSI), la taille de l'équipe ou l'ancienneté.
  Je n'ai volontairement inventé **ni nom de gérant·e ni biographie**.

**Réel — recherché, pas inventé** (mais mérite une relecture par Kay) :

- les cenotes et leurs profondeurs, les niveaux de certification exigés, la limite cavern de 21 m,
  les températures d'eau, les chiffres du système Sac Actun, les faits sur le récif mésoaméricain.

**Photos réelles depuis le concept A** : 5 photos fournies par Kay sont intégrées
(hero, plaque du manifeste, 3 cartes de plongée, récif, bandeau final, galerie), servies
en AVIF avec repli WebP via `<picture>`. Il reste **une seule illustration** dans le concept A :
la carte « The Pit », faute de photo du puits. Les concepts B et C tournent encore
entièrement sur les illustrations.

⚠️ **Résolution** : les fichiers fournis font ~860 px de large (compression Instagram).
Suffisant pour la maquette, insuffisant pour la production — il faudra les **originaux**
pour servir du 2× sur le hero et les grands blocs.

**Copie en anglais** parce que c'est la langue de réservation du tourisme plongée à Tulum.
Les trois concepts sont câblés pour EN / ES / FR (`hreflang` déjà en place).

---

## Prochaines étapes

1. **Choisir une direction** (ou un mélange : par ex. l'ambiance de A avec l'index de C).
2. Récupérer chez Kay : photos, tarifs réels, coordonnées, agence de certification, avis clients.
3. Décliner les pages intérieures : une page par cenote, une page par cours, le journal.
4. **Infra** — à décider ensemble : hébergement statique + un back-end léger pour la réservation
   (e-mail / WhatsApp / paiement), ou branchement sur un système de booking existant.
5. Auto-hébergement des polices, images en AVIF/WebP, et passe Lighthouse avant mise en ligne.

Le détail SEO et les arbitrages de performance sont dans **[docs/SEO.md](docs/SEO.md)**.
Le parti pris de chaque concept est dans **[docs/DESIGN.md](docs/DESIGN.md)**.
