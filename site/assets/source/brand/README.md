# Kay Diving — logo vectorisé

Vectorisation du logo Kay Diving à partir du PNG/WebP d'origine (1333 × 2000).
Tous les fichiers SVG sont en courbes pures (aucune image bitmap embarquée, aucune
police requise) et se redimensionnent sans perte.

## Couleurs de marque

| Rôle | Hex | Usage |
|---|---|---|
| Bleu | `#4D85C4` | couleur principale de la marque |
| Blanc | `#FFFFFF` | demi-teinte claire, sur fond sombre |
| Navy | `#13202A` | fond de la charte / demi-teinte sur fond clair |

## Contenu

### `svg/` — logo vectoriel

| Fichier | Pour |
|---|---|
| `kay-diving-logo.svg` | lockup complet (emblème + texte), bleu + blanc → **fond sombre** |
| `kay-diving-logo-light.svg` | lockup complet, bleu + navy → **fond clair** |
| `kay-diving-logo-mono-white/navy/blue.svg` | lockup une seule couleur |
| `kay-diving-logo-currentcolor.svg` | lockup en `currentColor` (couleur pilotée en CSS) |
| `kay-diving-icon*.svg` | emblème seul, mêmes déclinaisons |
| `kay-diving-wordmark*.svg` | « KAY DIVING » seul |

Fond **transparent** partout : le logo se pose sur n'importe quelle couleur.

### `png/` — exports raster (fond transparent)

`kay-diving-logo`, `kay-diving-logo-light`, `kay-diving-icon`, `kay-diving-wordmark`
en 400 / 800 / 1600 px de large, plus `kay-diving-og.png` (1200 × 630) pour les
partages Open Graph / réseaux sociaux.

### `favicon/` — onglet, PWA, iOS

| Fichier | Pour |
|---|---|
| `favicon.svg`, `favicon.ico`, `favicon-16/32/48.png` | onglet navigateur (marque recadrée, voir note) |
| `app-icon.svg`, `apple-touch-icon.png` (180) | raccourci iOS / Android |
| `icon-192.png`, `icon-512.png` | manifest PWA |
| `icon-maskable.svg`, `icon-maskable-512.png` | icône *maskable* Android (zone de sécurité 20 %) |

## Intégration

```html
<link rel="icon" href="/favicon.ico" sizes="32x32">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
<link rel="manifest" href="/site.webmanifest">
<meta property="og:image" content="https://kaydiving.com/kay-diving-og.png">
```

Logo dans la page — inline pour pouvoir le colorer en CSS :

```html
<a class="logo" href="/"><!-- contenu de kay-diving-logo-currentcolor.svg --></a>
```
```css
.logo { color: #fff; width: 180px; display: inline-block; }
.logo svg { width: 100%; height: auto; }
```

Ou en `<img>`, le plus simple :

```html
<img src="/kay-diving-logo.svg" alt="Kay Diving" width="180" height="206">
```

Bascule clair / sombre sans JavaScript :

```html
<picture>
  <source srcset="/kay-diving-logo.svg" media="(prefers-color-scheme: dark)">
  <img src="/kay-diving-logo-light.svg" alt="Kay Diving" width="180" height="206">
</picture>
```

## Notes techniques

- **Poids** : `kay-diving-logo.svg` = 75 Ko brut, **27 Ko transférés** une fois gzippé.
  Activez la compression (gzip/brotli) sur les `.svg`, c'est le réglage qui compte ici.
- **Structure** : chaque logo bicolore = 2 tracés, `#base` (bleu, silhouette complète)
  et `#light` / `#dark` (demi-teinte par-dessus). Pour recolorer, il suffit de changer
  deux attributs `fill`. La demi-teinte déborde de 0,7 px sur le bleu pour éviter tout
  filet parasite au raccord — c'est volontaire.
- **Texte** : « KAY DIVING » est vectorisé en contours, pas en texte vivant. La police
  d'origine n'était pas fournie ; si vous la retrouvez (géométrique sans-serif type
  Montserrat / Poppins), un remontage typographique donnerait des lettres parfaitement
  lisses. En l'état les contours sont fidèles au fichier source.
- **Favicon 16 px** : le dessin d'origine est du trait fin très détaillé, illisible à
  16 px. Le favicon livré est donc recadré sur le casque et les lunettes ; l'emblème
  complet est conservé pour les grandes tailles (`app-icon.svg`, 180 px et plus).
- **Fidélité** : écart mesuré avec la source nettoyée = 0,86 % des pixels d'encre,
  uniquement sur le lissé des bords. Aucune forme perdue.
