"""Casa Cenote, step 2: the mask rendered in the site map palette, with the coast road and the bottom features."""
import sys,numpy as np,cv2
from PIL import Image
from scipy import ndimage as nd
src,maskp,out=sys.argv[1:4]
S=4
a=np.asarray(Image.open(src).convert('RGB')).astype(int)
r,g,b=a[...,0],a[...,1],a[...,2]
W=cv2.imread(maskp,0)>127
H,Wd=W.shape
hx=lambda h:np.array([int(h[i:i+2],16) for i in (1,3,5)],float)
INK=dict(sea=hx('#03111A'),land=hx('#0A1D26'),green=hx('#0C2629'),town=hx('#10262F'),water=hx('#0B4450'),turq=hx('#4FE0D2'),foam=hx('#E6FBF6'))
up=lambda x:cv2.resize(x.astype(np.uint8)*255,(Wd,H),interpolation=cv2.INTER_LINEAR).astype(float)/255
def over(img,col,alpha):
    alpha=np.clip(alpha,0,1)[...,None]; return img*(1-alpha)+col*alpha
img=np.zeros((H,Wd,3))+INK['land']
# mangrove fringe: a soft band of vegetation around the water
d=nd.distance_transform_edt(~W)
band=np.clip(1-d/(30*S),0,1)**.6
img=over(img,hx('#0F2E30'),band*0.95)
# road (the tan band in the original) as a faint town-coloured strip with foam edges
road=((r>110)&(r>g+15)&(g>b+10)); road[:508]=0
road=nd.binary_closing(road,iterations=2); road=nd.binary_opening(road,iterations=1)
R=cv2.GaussianBlur(up(road),(0,0),14)>.5
img=over(img,INK['town'],R*1.0)
edgeR=R ^ nd.binary_erosion(R,iterations=3)
img=over(img,INK['foam'],cv2.GaussianBlur(edgeR.astype(float),(0,0),1.2)*0.22)
# water
img=over(img,INK['water'],W*1.0)
# boulders / hand-drawn bottom features: light pixels inside water
lite=((r>120)&(g>120)&(b>120)&(abs(r-b)<70))
for x0,y0,x1,y1 in [(350,148,450,168),(625,180,715,200),(275,395,365,416),(218,460,305,482),(190,480,230,512)]:
    lite[y0:y1,x0:x1]=0
L=cv2.GaussianBlur(up(lite),(0,0),2.2)
L=L*nd.binary_erosion(W,iterations=6*S)
img=over(img,INK['foam'],np.clip(L*1.6,0,1)*0.16)
# coast: glow + hairline
edge=(W ^ nd.binary_erosion(W,iterations=4)).astype(float)
glow=cv2.GaussianBlur(edge,(0,0),9*S/2)
img=over(img,INK['turq'],np.clip(glow*9,0,1)*0.20)
img=over(img,INK['turq'],cv2.GaussianBlur(edge,(0,0),1.3)*0.85)
# subtle vignette like the map frame
yy,xx=np.mgrid[0:H,0:Wd]; v=((xx/Wd-.5)**2+(yy/H-.5)**2)**.5
img=over(img,INK['sea'],np.clip((v-.45)*1.4,0,.5))
Image.fromarray(np.clip(img,0,255).astype(np.uint8)).resize((Wd//2,H//2),Image.LANCZOS).save(out)
