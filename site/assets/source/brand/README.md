# Kay Diving — logo vectorisé

Le logo de Kay (`kay-diving-logo-source.jpg`, JPEG 1600 × 567 sur fond navy),
vectorisé par `scripts/trace-logo.py`. Tous les SVG sont en courbes pures :
aucune image bitmap embarquée, aucune police requise, net à toute taille.

    python3 scripts/trace-logo.py     # le JPEG → les SVG de ce dossier
    node scripts/brand.mjs            # ces SVG → ce que le site sert (public/assets/brand/v2/)

## Couleurs

| Rôle | Hex |
|---|---|
| Cyan (cercle, vagues, bulles, « TULUM - MÉXICO », poisson) | `#08E3F6` |
| Blanc (plongeur, « Kay Diving ») | `#FFFFFF` |
| Bleu du masque | `#106D98` |
| Navy (fond d'origine ; le blanc sur fond clair) | `#012D40` |

Le dégradé léger du cyan dans le JPEG devient un aplat : c'est ce qui tient
à 16 px comme sur un t-shirt.

## Fichiers

| Fichier | Pour |
|---|---|
| `kay-diving-logo.svg` | logo complet, **fond sombre**, transparent |
| `kay-diving-logo-light.svg` | logo complet, **fond clair** (le blanc devient navy) |
| `kay-diving-logo-navy.svg` | logo complet sur son fond navy, comme l'original |
| `kay-diving-icon.svg` / `-light.svg` | l'emblème seul (le plongeur dans son cercle) |
| `favicon.svg` | emblème sur carré navy arrondi — onglet du navigateur |
| `app-icon.svg` | emblème sur carré navy — iPhone, Android, PWA |
| `icon-maskable.svg` | emblème dans la zone de sécurité Android (80 %) |

Le texte du logo complet est illisible en dessous d'environ 50 px de haut :
c'est pourquoi l'en-tête le montre à 72 px (50 px sur téléphone), et pourquoi
l'onglet et les icônes d'application n'utilisent que l'emblème.

## Où il sert sur le site

- en-tête : `v2/logo.svg` ;
- onglet : `favicon.ico?v=2` et `v2/favicon.svg` ;
- iPhone / Android : `v2/apple-touch-icon.png`, `v2/icon-192.png`, `v2/icon-512.png`, `v2/icon-maskable-512.png` ;
- aperçu WhatsApp / réseaux : `public/assets/og/v2/*.jpg`, rendu par `scripts/og.mjs` ;
- admin : `v2/icon.svg` (barre latérale), `v2/app-icon.svg` (connexion).

Les images sont servies « immutable » pendant un an : un nouveau logo va dans
`v3/`, sinon les visiteurs gardent l'ancien.

## Fidélité

Vectorisation automatique d'un JPEG : très fidèle aux formes, avec de petites
irrégularités sur les détails les plus fins (le verre du masque). Si
l'original existe en SVG, PDF ou AI, il le remplacera avantageusement : le
poser à la place de ces fichiers et relancer `node scripts/brand.mjs`.
