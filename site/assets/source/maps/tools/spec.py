"""Every label of every cenote map, in source-image pixels, and the words in three languages."""
import os
BASE = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), 'base')

TYPES = {  # messages/<locale>.json → cenotes.types
    'open': dict(en='Open cenote', es='Cenote abierto', fr='Cenote ouvert'),
    'cavern': dict(en='Cavern', es='Caverna', fr='Caverne'),
    'deep': dict(en='Deep', es='Profundo', fr='Profond'),
}

WORDS = {
    'snorkel': dict(en='Snorkel area', es='Zona de snorkel', fr='Zone de snorkeling'),
    'mangrove': dict(en='Mangrove', es='Manglar', fr='Mangrove'),
    'jungle': dict(en='Jungle', es='Selva', fr='Jungle'),
    'crack': dict(en='Crack passage', es='Paso de la grieta', fr='Passage de la faille'),
    'cavpass': dict(en='Cavern passage', es='Paso de caverna', fr='Passage de la caverne'),
    'limit': dict(en='Cavern limit', es='Límite de caverna', fr='Limite de la caverne'),
    'entrance': dict(en='Main entrance', es='Entrada principal', fr='Entrée principale'),
    'entry': dict(en='Entrance', es='Entrada', fr='Entrée'),
    'line': dict(en='Dive line', es='Línea de buceo', fr='Fil guide'),
    'cline': dict(en='Cavern line', es='Línea de caverna', fr='Fil guide de la caverne'),
    'form': dict(en='Formations', es='Formaciones', fr='Concrétions'),
    'plat': dict(en='Platform', es='Plataforma', fr='Ponton'),
    'open': dict(en='Open water', es='Aguas abiertas', fr='Eau libre'),
    'cave': dict(en='Cave divers only', es='Solo buzos de cueva', fr='Plongeurs spéléo uniquement'),
    'halo': dict(en='Halocline', es='Haloclina', fr='Halocline'),
    'stop': dict(en='Cavern limit', es='Límite de caverna', fr='Limite de la caverne'),
    'batcave': dict(en='Bat Cave', es='Baticueva', fr='Baticueva'),
    'west': dict(en='West Eye', es='Ojo Oeste', fr='Œil ouest'),
    'east': dict(en='East Eye', es='Ojo Este', fr='Œil est'),
    'barbie': dict(en='Barbie line', es='Línea Barbie', fr='Ligne Barbie'),
    'coba': dict(en='To Cobá →', es='Hacia Cobá →', fr='Vers Cobá →'),
    'halo2': dict(en='Halocline · hydrogen sulphide cloud', es='Haloclina · nube de ácido sulfhídrico', fr='Halocline · nuage d’hydrogène sulfuré'),
    'trunks': dict(en='Sunken trees', es='Árboles hundidos', fr='Arbres engloutis'),
    'debris': dict(en='Rock fall', es='Derrumbe', fr='Éboulis'),
    'snorkelpool': dict(en='Snorkel area', es='Zona de snorkel', fr='Zone de snorkeling'),
}

AFTER = dict(en='After', es='Según', fr="D'après")
def credit(who):
    return {l: f'{AFTER[l]} {who}' for l in AFTER}

def depths(pts):
    return [('d', x, y, f'{v} m') for x, y, v in pts]

MAPS = {}

MAPS['casa-cenote'] = dict(
    title='Casa Cenote', type='open', base=f'{BASE}/casa-cenote.png', size=(1616, 1140), scale=2.0,
    credit=credit('Gerardo Aizpuru © 2004<br>Underwater Editions'),
    svg='<g fill="none" stroke="#4FE0D2" stroke-opacity=".55" stroke-width="1.1" stroke-dasharray="4 4">'
        '<path d="M478 168 L520 178 L556 186 L583 170"/><path d="M507 126 L522 134"/></g>',
    labels=[
        ('zone', 394, 158, 'snorkel'), ('zone', 668, 214, 'snorkel'), ('zone', 322, 405, 'snorkel'),
        ('mang', 240, 200, 'mangrove'), ('mang', 520, 285, 'mangrove'), ('mang', 90, 410, 'mangrove'), ('mang', 560, 44, 'mangrove'),
        ('lab', 490, 79, 'crack'), ('lab', 530, 222, 'cavpass'),
        *depths([(370, 122, 4), (462, 104, 3), (532, 140, 2), (548, 197, 6), (606, 134, 5), (708, 214, 5),
                 (681, 323, 4), (376, 254, 5), (283, 431, 5), (198, 466, 6)]),
        ('stop', 209, 497, 'limit'), ('stop', 730, 165, 'limit', dict(cls='l')),
        ('home', 210, 536, 'entrance'),
    ],
    scale_m=(3.93, 30, 'left:44px;top:960px'), north=35, north_at='right:70px;bottom:150px',
)

MAPS['dos-ojos'] = dict(
    title='Cenote Dos Ojos', type='cavern', base=f'{BASE}/dos-ojos.png', size=(1600, 1897), scale=1600 / 496,
    credit=credit('Sebastián Maza © 2011<br>Fisheye Adventures'), credit_at='right:44px;bottom:44px',
    labels=[
        ('zone', 240, 358, 'snorkel'), ('zone', 278, 507, 'snorkel'),
        ('name', 85, 205, 'batcave'), ('name', 285, 312, 'west'), ('name', 137, 505, 'east'),
        ('lab', 452, 92, 'barbie'),
        *depths([(92, 126, 7), (94, 157, 6), (46, 176, 7), (26, 217, 5), (54, 234, 10), (118, 230, 2), (155, 210, 5),
                 (160, 280, 5), (86, 294, 5), (44, 305, 8), (62, 334, 6), (180, 327, 8), (139, 367, 9), (81, 393, 8),
                 (119, 435, 6), (244, 385, 7), (275, 391, 4), (306, 380, 3), (342, 332, 6), (396, 332, 4), (411, 352, 5),
                 (362, 381, 6), (312, 416, 8), (275, 422, 7), (257, 444, 4), (226, 466, 6), (194, 434, 6), (190, 469, 3),
                 (377, 206, 5), (405, 237, 4), (444, 227, 8), (437, 189, 4), (380, 122, 6)]),
        ('stop', 19, 117, ''), ('stop', 399, 64, ''), ('stop', 327, 461, ''),
        ('form', 125, 171, ''), ('form', 62, 361, ''), ('form', 457, 211, ''), ('form', 401, 374, ''), ('form', 252, 426, ''), ('form', 426, 120, ''),
        ('plat', 205, 284, '', dict(rot=35)), ('plat', 352, 220, '', dict(rot=55)), ('plat', 365, 290, '', dict(rot=-60)), ('plat', 207, 507, '', dict(rot=30)),
    ],
    legend=['open', 'line', 'stop', 'form', 'plat'], legend_at='right:44px;top:1250px',
    scale_m=(3.0, 30, 'left:1260px;top:1530px'), north=0, north_at='left:768px;top:680px',
)

MAPS['car-wash'] = dict(
    title='Cenote Car Wash', aka='Aktun Ha', type='cavern', base=f'{BASE}/car-wash.png', size=(1600, 1137), scale=1600 / 1065,
    credit=credit('Underwater Editions'), credit_at='right:44px;bottom:30px',
    svg='<path d="M520 762 L1070 573" fill="none" stroke="#4FE0D2" stroke-opacity=".32" stroke-width="2.2"/>',
    labels=[
        ('zone', 463, 487, 'snorkel'),
        *depths([(850, 100, 10), (785, 147, 14), (922, 155, 10), (583, 235, 15), (845, 262, 15), (783, 370, 13), (707, 433, 7),
                 (515, 440, 5), (320, 537, 5), (168, 470, 16)]),
        ('stop', 938, 77, 'limit', dict(cls='above')), ('stop', 140, 437, 'limit', dict(cls='above')),
        ('form', 712, 160, ''), ('form', 960, 123, ''), ('form', 790, 415, ''),
        ('home', 640, 600, 'entrance', dict(cls='below')),
        ('road', 900, 640, 'coba', dict(rot=-18.8)),
    ],
    note=dict(en='No permanent guideline: Car Wash is a training cavern, so your guide lays a line at the start of the dive and reels it back in at the end.',
              es='Sin línea permanente: Car Wash es una caverna de entrenamiento, así que tu guía tiende una línea al empezar la inmersión y la recoge al terminar.',
              fr="Pas de fil permanent : Car Wash sert de caverne d'entraînement, votre guide pose donc un fil au début de la plongée et le récupère à la fin."),
    note_at='left:200px;top:250px',
    legend=['stop', 'form'], legend_at='left:44px;top:470px',
    scale_m=(9.33, 30, 'left:600px;top:120px'), north=130, north_at='left:70px;top:900px',
    credit_note=True,
)

MAPS['dreamgate'] = dict(
    title='Cenote Dreamgate', type='cavern', base=f'{BASE}/dreamgate.png', size=(1600, 866), scale=1600 / 1110,
    credit={'en': 'After the dive-centre map', 'es': 'Según el mapa del centro de buceo', 'fr': 'D\'après le plan du centre de plongée'},
    credit_at='right:44px;bottom:30px',
    svg='<g fill="none" stroke="#F7EFE3" stroke-opacity=".45" stroke-width="1.4" stroke-dasharray="5 4" stroke-linecap="round">'
        '<path d="M322 74 C345 90 368 108 392 128"/><path d="M368 50 C385 74 400 94 412 112"/><path d="M470 46 C482 68 494 84 505 100"/>'
        '<path d="M290 152 C322 157 362 161 398 170"/><path d="M296 178 C330 182 360 188 388 195"/><path d="M300 218 C332 222 366 231 400 240"/>'
        '<path d="M736 54 C752 72 772 90 800 106"/><path d="M776 50 C790 68 806 84 826 98"/>'
        '<path d="M1012 44 C996 54 978 64 960 76"/><path d="M1040 78 C1024 90 1010 104 996 120"/><path d="M1044 112 C1030 122 1016 134 1004 146"/></g>',
    labels=[
        ('mang', 650, 230, 'jungle'),
        ('form', 420, 390, ''), ('form', 545, 428, ''), ('form', 897, 252, ''), ('form', 835, 362, ''),
        ('plat', 633, 480, '', dict(rot=90, size='width:84px;height:22px;margin:-11px 0 0 -42px')),
        ('plat', 604, 486, '', dict(rot=0, size='width:44px;height:20px;margin:-10px 0 0 -22px')),
        ('home', 598, 512, 'entry', dict(cls='l')),
        ('zone', 700, 455, 'open'),
    ],
    legend=['open', 'cline', 'form', 'cave', 'plat'], legend_at='left:44px;top:470px',
)

MAPS['el-pit'] = dict(
    title='Cenote El Pit', type='deep', base=f'{BASE}/el-pit.png', size=(1300, 1833), scale=1300 / 424,
    credit=credit('Alejandro Elizondo<br>Underwater Editions'), credit_at='right:44px;bottom:36px',
    labels=[
        ('mang', 392, 150, 'jungle'),
        ('home', 367, 170, 'entrance', dict(cls='l')),
        ('plat', 356, 206, '', dict(size='width:96px;height:14px;margin:-7px 0 0 -48px')),
        ('stop', 117, 306, 'limit', dict(cls='below')),
        ('pin', 133, 381, 'halo2', dict(cls='l wrap')),
        ('zone', 238, 470, 'debris'),
        ('d', 378, 273, '10 m', dict(cls='r')), ('d', 362, 335, '20 m', dict(cls='r')), ('d', 358, 405, '30 m', dict(cls='r')), ('d', 362, 460, '40 m', dict(cls='r')),
    ],
    note=dict(en='El Pit goes on down to 119 m. The dive reaches 30–32 m, through the cloud.',
              es='El Pit sigue bajando hasta 119 m. La inmersión llega a 30–32 m, atravesando la nube.',
              fr='El Pit descend jusqu’à 119 m. La plongée atteint 30–32 m, à travers le nuage.'),
    note_at='left:44px;top:1650px;max-width:560px',
)

MAPS['angelita'] = dict(
    title='Cenote Angelita', type='deep', base=f'{BASE}/angelita.png', size=(1600, 1464), scale=1600 / 470,
    credit=credit('Underwater Expeditions México'), credit_at='right:44px;bottom:36px',
    labels=[
        ('mang', 70, 66, 'jungle'), ('mang', 400, 72, 'jungle'),
        ('home', 230, 90, 'entrance', dict(cls='above')),
        ('pin', 320, 254, 'halo2', dict(cls='wrap left')),
        ('zone', 238, 335, 'trunks'),
        ('d', 120, 254, '30 m', dict(cls='')), ('d', 92, 301, '40 m'), ('d', 52, 364, '55 m'),
    ],
    note=dict(en='Angelita is about 60 m deep. The dive reaches 35–38 m, below the cloud.',
              es='Angelita tiene unos 60 m de profundidad. La inmersión llega a 35–38 m, por debajo de la nube.',
              fr='Angelita fait environ 60 m de fond. La plongée atteint 35–38 m, sous le nuage.'),
    note_at='right:44px;top:1100px;max-width:330px',
)
