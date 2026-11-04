"""Render a cenote in section (side view) in the site map's palette."""
import os, sys, numpy as np, cv2
from PIL import Image
from scipy import ndimage as nd
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__))); import masks
from plan import INK, hx, over

def poly(shape, pts):
    m = np.zeros(shape, np.uint8); cv2.fillPoly(m, [np.array(pts, np.int32).reshape(-1, 1, 2)], 1); return m.astype(bool)

def el_pit(p):
    a = masks.load(p); r, g, b = a[..., 0], a[..., 1], a[..., 2]
    blue = (b > r + 40) & (b > 70)
    blue = masks.boxes(blue, [(0, 0, 180, 80), (175, 0, 285, 160), (20, 330, 110, 380), (0, 0, 424, 165)], 0)
    rock = (np.abs(r - b) < 30) & (np.abs(g - b) < 30) & (r > 45)
    rock = masks.boxes(rock, [(0, 0, 424, 350), (0, 0, 120, 598), (0, 520, 424, 598)], 0)
    band = (r > 125) & (g > 155) & (b > 140) & (g > r + 12)
    band = masks.boxes(band, [(0, 0, 424, 360), (0, 398, 424, 598), (0, 0, 130, 598)], 0)
    w = nd.binary_closing(blue | rock | band, iterations=3); w = nd.binary_fill_holes(w); w = masks.biggest(w)
    w = masks.boxes(w, [(0, 520, 424, 598)], 0)
    w = nd.binary_opening(w, iterations=2)
    air = poly(w.shape, [(351, 168), (384, 168), (382, 208), (332, 208), (336, 200), (343, 190)])
    w = w & ~air
    band = nd.binary_closing(band, iterations=3) & w
    rock = nd.binary_closing(rock & ~band, iterations=2) & w & ~nd.binary_dilation(band, iterations=1)
    edges = (r > 200) & (g > 200) & (b > 200)
    edges = masks.boxes(edges, [(0, 0, 424, 355), (0, 520, 424, 598)], 0)
    return dict(src=a, water=w, air=air, rock=nd.binary_opening(rock, iterations=1), band=band, rock_lines=edges & w,
                ground=170, surface=207, band_y=(367, 394), branches=None)

def angelita(p):
    a = masks.load(p)[:430, :470]; r, g, b = a[..., 0], a[..., 1], a[..., 2]
    sh = a.shape[:2]
    wall = [(150, 100), (152, 127), (150, 177), (147, 200), (143, 247), (142, 260), (110, 277), (103, 300), (80, 327), (53, 353),
            (40, 377), (30, 403), (33, 413), (363, 409), (360, 353), (350, 327), (333, 293), (320, 277), (317, 247), (320, 200),
            (313, 167), (310, 140), (313, 110), (310, 100)]
    w = poly(sh, wall)
    air = poly(sh, [(117, 80), (127, 92), (150, 100), (310, 100), (310, 88), (322, 84), (330, 84), (330, 60), (117, 60)])
    mound = poly(sh, [(40, 412), (97, 343), (130, 317), (167, 282), (195, 262), (250, 243), (300, 250), (322, 268), (338, 300),
                      (352, 335), (362, 370), (365, 412)]) & w
    band = poly(sh, [(140, 247), (320, 247), (320, 261), (140, 261)]) & w
    br = (r < 45) & (g < 45) & (b < 45)
    br = br & nd.binary_erosion(w, iterations=3); br = masks.boxes(br, [(0, 395, 470, 430)], 0)
    br = masks.boxes(br, [(0, 380, 470, 430)], 0)
    br = nd.binary_opening(br, iterations=1); br = masks.biggest(br, 12)
    return dict(src=a, water=w, air=air, rock=mound, band=band, rock_lines=None, ground=(80, 85), surface=100,
                band_y=(247, 261), branches=br)

def render(name, src, out, FW):
    d = globals()[name](src)
    w = d['water']; sh, sw = w.shape
    W2 = FW * 2; H2 = round(sh * W2 / sw); k = W2 / sw
    def up(m, blur=0):
        x = cv2.resize(m.astype(np.uint8) * 255, (W2, H2), interpolation=cv2.INTER_LINEAR).astype(float) / 255
        return cv2.GaussianBlur(x, (0, 0), blur) if blur else x
    yy = np.mgrid[0:H2, 0:W2][0] / k  # source-pixel row of every output pixel
    img = np.zeros((H2, W2, 3)) + INK['sea']
    g = d['ground']; gl, gr = (g, g) if isinstance(g, int) else g
    xs = np.mgrid[0:H2, 0:W2][1] / k
    gy = np.where(xs < sw / 2, gl, gr)
    earth = yy >= gy
    img = over(img, INK['land'], earth * 1.0)
    img = over(img, INK['green'], (earth & (yy < gy + 9)) * .9)
    gline = np.abs(yy - gy) < 1.2 / k * 2
    img = over(img, INK['turq'], gline * .35)
    W = up(w, k * .8) > .5
    A = up(d['air'], k * .8) > .5
    img = over(img, INK['sea'], A * 1.0)
    b0, b1 = d['band_y']; s = d['surface']
    t = np.clip((yy - s) / max(b0 - s, 1), 0, 1)
    top = INK['open'][None, None] * (1 - t[..., None]) + INK['water'][None, None] * t[..., None]
    deep = hx('#06202B')
    col = np.where((yy > (b0 + b1) / 2)[..., None], deep[None, None], top)
    img = np.where(W[..., None], col, img)
    # the halocline / hydrogen-sulphide cloud: a soft lit layer
    B = cv2.GaussianBlur((up(d['band'], k * .6) > .5).astype(float), (0, 0), k * 2.2)
    img = over(img, INK['foam'], np.clip(B * 1.3, 0, 1) * .42 * W)
    R = up(d['rock'], k * .7) > .5
    img = over(img, hx('#102A33'), R * 1.0)
    if d['rock_lines'] is not None:
        L = cv2.GaussianBlur(up(d['rock_lines']), (0, 0), k * .35)
        img = over(img, INK['foam'], np.clip(L * 2, 0, 1) * .28 * R)
    re = (R ^ nd.binary_erosion(R, iterations=3)).astype(float)
    img = over(img, INK['foam'], cv2.GaussianBlur(re, (0, 0), 1.1) * .32)
    if d['branches'] is not None:
        Bn = cv2.GaussianBlur(up(d['branches']), (0, 0), k * .3)
        img = over(img, hx('#020B10'), np.clip(Bn * 1.6, 0, 1) * .62)
    edge = (W ^ nd.binary_erosion(W, iterations=4)).astype(float)
    img = over(img, INK['turq'], np.clip(cv2.GaussianBlur(edge, (0, 0), 18) * 9, 0, 1) * .2)
    img = over(img, INK['turq'], cv2.GaussianBlur(edge, (0, 0), 1.3) * .85)
    # the water surface
    surf = W & (np.abs(yy - s) < 3 / k * 2)
    img = over(img, INK['glow'], cv2.GaussianBlur(surf.astype(float), (0, 0), 2) * .9)
    Image.fromarray(np.clip(img, 0, 255).astype(np.uint8)).resize((FW, H2 // 2), Image.LANCZOS).save(out)
    print(out, FW, H2 // 2, 'scale', FW / sw)

if __name__ == '__main__':
    render(sys.argv[1], sys.argv[2], sys.argv[3], int(sys.argv[4]))
