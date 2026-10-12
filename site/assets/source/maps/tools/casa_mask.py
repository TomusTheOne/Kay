"""Casa Cenote, step 1: the water of the original map as a mask (the map is too busy for the generic tracer in masks.py)."""
import sys,numpy as np,cv2
from PIL import Image
from scipy import ndimage as nd
src,out=sys.argv[1],sys.argv[2]
a=np.asarray(Image.open(src).convert('RGB')).astype(int)
r,g,b=a[...,0],a[...,1],a[...,2]
m=((b>g+8)&(b>r+12)&(b>35))
m[:80,:290]=0; m[160:200,0:130]=0; m[410:440,450:600]=0   # title, halocline key, scale bar
m[484:510,200:222]=1   # under the STOP sign: the channel to the entrance pool
m=nd.binary_closing(m,iterations=3)
m=nd.binary_fill_holes(m)
lab,n=nd.label(m); sizes=nd.sum(m,lab,range(1,n+1)); m=lab==(1+int(np.argmax(sizes)))
m=nd.binary_opening(m,iterations=2)
m=nd.binary_fill_holes(m)
S=4
big=cv2.resize(m.astype(np.uint8)*255,None,fx=S,fy=S,interpolation=cv2.INTER_LINEAR)
big=cv2.GaussianBlur(big,(0,0),6); big=(big>127).astype(np.uint8)*255
cv2.imwrite(out,big)
