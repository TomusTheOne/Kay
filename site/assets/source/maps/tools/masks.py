"""Water masks traced from the original maps. Each returns (water, open, lines) at source resolution."""
import numpy as np
from PIL import Image
from scipy import ndimage as nd

def load(p):
    return np.asarray(Image.open(p).convert('RGB')).astype(int)

def biggest(m, keep=1):
    lab, n = nd.label(m)
    if n == 0: return m
    sizes = nd.sum(m, lab, range(1, n + 1))
    order = np.argsort(sizes)[::-1][:keep]
    return np.isin(lab, order + 1)

def boxes(m, bs, v):
    for x0, y0, x1, y1 in bs: m[y0:y1, x0:x1] = v
    return m

def dos_ojos(p):
    a = load(p); r, g, b = a[..., 0], a[..., 1], a[..., 2]
    w = (b > g + 18) & (b > r + 40) & (b > 70)
    w = boxes(w, [(180, 10, 300, 190), (380, 380, 496, 540), (0, 0, 160, 80)], 0)
    w = nd.binary_closing(w, iterations=3); w = nd.binary_fill_holes(w); w = biggest(w)
    w = nd.binary_opening(w, iterations=1)
    op = (b > 190) & (g > 140) & (r > 90) & (r < 190)
    op = nd.binary_closing(op, iterations=2); op = nd.binary_fill_holes(op); op = biggest(op, 4) & w
    op = nd.binary_opening(op, iterations=2)
    ln = (r > 200) & (g > 200) & (b > 200)
    ln = boxes(ln, [(100, 195, 235, 225), (330, 270, 390, 320) if False else (0,0,0,0),
                    (230, 290, 330, 330), (210, 340, 270, 375), (195, 480, 290, 520), (100, 490, 170, 520),
                    (340, 70, 460, 95), (240, 250, 270, 280)], 0)
    return a, w, op, ln, None

def aktun_ha(p):
    import cv2
    a = load(p); r, g, b = a[..., 0], a[..., 1], a[..., 2]
    o = (r > 95) & (g > 95) & (b > 95) & (np.abs(r - b) < 45) & (np.abs(g - b) < 45)
    o = boxes(o, [(120, 0, 470, 260), (0, 0, 300, 80), (360, 0, 1065, 30), (360, 90, 670, 115), (0, 690, 1065, 757), (880, 380, 1065, 540),
                  (480, 20, 760, 60)], 0)
    o = np.ascontiguousarray(nd.binary_dilation(o, iterations=3).astype(np.uint8))
    for pts in ([(783, 63), (820, 55), (870, 60), (905, 85), (930, 120), (923, 182)],
                [(212, 386), (185, 400), (152, 416), (128, 450), (125, 492)],
                [(556, 614), (580, 596), (602, 570), (632, 576), (662, 577), (692, 548)],
                [(466, 210), (480, 222), (492, 238)], [(120, 488), (136, 506)], [(266, 402), (287, 398)],
                [(125, 492), (130, 500), (150, 520), (165, 555), (180, 585), (200, 597), (245, 597), (265, 605), (275, 630),
                 (290, 645), (315, 665), (335, 680), (350, 682), (360, 670), (352, 655), (345, 635), (350, 620), (370, 607),
                 (390, 597), (420, 590), (450, 600), (480, 612), (510, 617), (535, 615), (567, 597), (602, 570)]):
        cv2.polylines(o, [np.array(pts, np.int32).reshape(-1, 1, 2)], False, 1, 5)
    lab, n = nd.label(o == 0); w = lab == lab[450, 500]
    w = nd.binary_dilation(w, iterations=3)
    w = nd.binary_fill_holes(nd.binary_closing(w, iterations=5)); w = nd.binary_opening(w, iterations=3)
    w = nd.binary_opening(w, iterations=2)
    # rock outlines and the cavern's bottom: the lavender lines inside
    ln = None
    lite = (b > r + 14) & (b > g + 12) & (b > 45) & (b < 220)
    lite = boxes(lite, [(250, 455, 330, 490), (395, 472, 530, 500)], 0)  # the flow arrow, the zone name
    return a, w, None, None, lite & nd.binary_erosion(w, iterations=6)

def dreamgate(p):
    a = load(p); r, g, b = a[..., 0], a[..., 1], a[..., 2]
    w = (b > r + 22) & (b > 150)
    w = boxes(w, [(0, 330, 395, 601)], 0)
    w = boxes(w, [(585, 440, 652, 522)], 1)
    w = nd.binary_closing(w, iterations=3); w = nd.binary_fill_holes(w); w = biggest(w)
    w = nd.binary_opening(w, iterations=2)
    op = (b > r + 60) & (r < 175) & (g > 170) & (b > 200)
    op = boxes(op, [(0, 0, 560, 601), (760, 0, 1110, 601), (0, 0, 1110, 385)], 0)
    op = boxes(op, [(585, 440, 652, 522)], 1)
    op = nd.binary_closing(op, iterations=4); op = nd.binary_fill_holes(op); op = biggest(op) & w
    op = nd.binary_opening(op, iterations=3)
    ln = (r < 90) & (g > 120) & (b > 190) & (b > r + 120)
    ln = boxes(ln, [(0, 330, 395, 601)], 0)
    lite = (r < 170) & (g < 170) & (b < 185) & (np.abs(r - b) < 40) & (r > 60)  # the grey cave drawing
    lite = boxes(lite, [(0, 330, 395, 601)], 0)
    return a, w, op, ln, lite & nd.binary_erosion(w, iterations=4)
