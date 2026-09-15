# Parti pris de design

Trois directions, conçues pour être **incomparables** plutôt que comparables : mood, typographie,
grille, couleur, quantité de mouvement et mécanique signature diffèrent sur tous les axes.
Si deux d'entre elles se ressemblaient, le choix serait faux.

Point de départ commun : Kay Diving guide des **petits groupes** dans les cenotes de Tulum —
les puits naturels que les Mayas considéraient comme l'entrée de **Xibalba**, le monde d'en bas.
C'est un sujet que presque aucun centre de plongée n'exploite : la concurrence à Tulum vend
« turquoise + turtle + PADI ». Les trois concepts partent de là et divergent.

---

## A — XIBALBA · le cinéma

> *« Descend into the Mayan underworld. »*

**L'idée.** La page **est** une plongée. Le scroll est une descente.

**La mécanique signature.** Une jauge de profondeur fixée sur le bord gauche compte les mètres
au fil du scroll (0 → 40 m) et affiche la zone traversée : *Surface · Cavern zone · Deep · The abyss*.
En parallèle, un calque noir fixe voit son opacité pilotée par la même variable CSS `--descent` :
**l'eau s'assombrit réellement à mesure qu'on descend.** Sur mobile la jauge devient une barre
de progression en bas d'écran. Coût : un `scroll` listener en `requestAnimationFrame` et deux
custom properties — aucune image, aucun canvas.

**Couleur.** Un monde froid : abysse `#03090E`, turquoise de cenote `#4FE0D2`.
Une seule couleur chaude sur tout le site — l'ambre `#F5C77E` — **réservée exclusivement à la
réservation**. Dans un monde bleu nuit, l'action de réservation est littéralement la seule
lumière chaude à l'écran. On ne peut pas la rater, et on n'a besoin d'aucune flèche pour la désigner.

**Typographie.** *Instrument Serif* en display (contraste élevé, un peu théâtral, très peu vu),
*Inter* en texte, *IBM Plex Mono* pour toutes les données d'instrument (coordonnées, profondeurs,
températures) — le lexique visuel d'un ordinateur de plongée.

**Réservation.** Une **ardoise de plongeur** : fond sombre rayé, lignes en pointillés,
tampon « Not confirmed » légèrement de travers, total en ambre.

**Risque assumé.** Les sites sombres peuvent paraître « niche » et fatiguer en plein soleil sur mobile —
c'est-à-dire exactement la situation d'un touriste à Tulum. À arbitrer.

---

## B — LUZ · le magazine

> *« Light falls a long way down here. »*

**L'idée.** L'inverse exact de A. Un **journal de voyage imprimé** : papier crème, filets d'un pixel,
beaucoup d'air, et les photos traitées comme des **planches** légendées (`Plate 01`, `Plate 02`…).

**La mécanique signature.** Il n'y en a pas — c'est le propos. Une seule animation (un fondu de 14 px
à l'apparition), zéro parallaxe, zéro calcul au scroll. **La vitesse est le design.** C'est le concept
le plus léger des trois (19 Ko gzip) et celui qui passera le mieux sur une 4G mexicaine.

La composition fait le travail à la place du mouvement : hiérarchie très contrastée, lettrine,
colonnes asymétriques, numérotation éditoriale `01 / 02 / 03`, et un **index typographique des cenotes**
— un tableau qui donne profondeur, niveau requis et usage pour chaque site.

**Couleur.** Papier `#FBF8F3`, sable `#E9DECA`, encre `#191512`, vert marin profond `#1B5B57`
pour les liens, terre cuite `#BE5C36` pour la réservation. Chaud, calme, cher.

**Typographie.** *Newsreader* (serif éditorial variable, chaleureux, faible contraste) avec *Karla*
(grotesque légèrement idiosyncratique). Le couple sonne « bon magazine », pas « agence web ».

**Ce qu'il apporte en plus.** C'est le seul concept où le **moteur SEO est visible dans le design** :
l'index des cenotes et la section *Journal* sont des surfaces de contenu assumées, pas des
rustines ajoutées après coup.

**Risque assumé.** Un design clair et aéré **dépend entièrement de la qualité des photos**.
Avec de belles images il est somptueux ; avec des photos moyennes il tombe à plat.

---

## C — BITÁCORA · le carnet de plongée

> *« Every site. Every depth. Stated plainly. »*

**L'idée.** La crédibilité comme esthétique. Là où les autres centres écrivent « cenote magique »,
celui-ci écrit **10 m, Open Water, 30 m de visibilité, 4 plongeurs maximum**.

**La mécanique signature.** Deux, complémentaires :

1. **L'index filtrable des sites** — sept fiches, chacune avec profondeur max, visibilité,
   température, temps de trajet, badges de niveau, et des filtres *Open Water / Advanced / Cavern /
   Deep / Reef*. Utile pour le plongeur, excellent pour Google.
2. **Le profil de profondeur** — un graphique SVG qui place chaque site face à la **limite cavern
   de 21 m**, tracée en pointillés orange. On voit d'un coup d'œil que seuls Angelita et The Pit
   la franchissent, et pourquoi ils exigent un niveau Advanced. C'est un argument de sécurité
   transformé en objet graphique.

Plus une **barre de statut** en haut de page : température d'eau, visibilité, heures de départ, ratio guide.

**Couleur.** Calcaire `#EDEAE3`, encre `#14171A`, et un jaune acide de signalisation `#D4ED3F`
utilisé **uniquement en aplat** (jamais en texte sur fond clair — le contraste serait insuffisant),
avec un bleu de données `#0E6E85` et un orange d'alerte `#E4572E` pour tout ce qui dépasse 21 m.

**Typographie.** *Space Grotesk* en display serré tout en capitales, *JetBrains Mono* pour
**tous les chiffres de la page**. Grille visible, filets partout, angles nets.

**Réservation.** Une **entrée de carnet** (`Log entry`) sur panneau noir, tampon « Pending »,
total en jaune acide.

**Risque assumé.** Moins romantique. Excellent pour convertir un plongeur certifié qui compare
trois centres ; moins efficace sur la réservation d'impulsion d'un touriste qui découvre.

---

## Les illustrations

Six scènes SVG dessinées à la main pour la maquette :

| fichier | scène |
|---|---|
| `cenote-shaft.svg` | rais de lumière dans un cenote, parois calcaires, racines, plongeur |
| `cavern.svg` | plongée cavern : stalactites, colonnes, torche, fil d'Ariane |
| `casa-cenote.svg` | eau peu profonde et lumineuse, racines de mangrove, banc de poissons |
| `the-pit.svg` | puits profond et étroit, une seule colonne de lumière |
| `halocline.svg` | plongeur traversant l'halocline |
| `reef.svg` | récif mésoaméricain : tortue, gorgones, rais de lumière |

**Pourquoi du SVG plutôt que des photos de banque d'images :** aucune licence à gérer,
23 Ko pour l'ensemble (6 Ko gzip), net à toutes les résolutions, et surtout — une maquette
avec de belles photos achetées ment sur le rendu final. Ici, ce que vous jugez est la
**mise en page**, et les emplacements ont déjà les bons ratios pour recevoir les photos de Kay.

---

## Où ils se rejoignent

Les trois partagent le même socle, pour que la comparaison soit honnête :
même contenu, mêmes tarifs, même FAQ, même moteur de réservation (choix du site → date → niveau →
plongeurs → transfert → options → total calculé en direct), même balisage SEO, mêmes règles
d'accessibilité, même absence totale de framework.

## Pistes d'hybridation

Rien n'oblige à choisir un concept pur :

- **A + C** — l'ambiance cinématographique de Xibalba, avec l'index filtrable et le graphique
  de profondeur de Bitácora en deuxième moitié de page. Probablement le meilleur compromis
  émotion / conversion.
- **B + C** — la mise en page éditoriale de Luz avec la rigueur data de Bitácora dans les fiches sites.
- **A en page d'accueil, B pour le journal** — la home fait rêver, les articles SEO respirent.
