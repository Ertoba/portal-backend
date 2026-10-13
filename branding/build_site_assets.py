#!/usr/bin/env python3
"""Regenerate MILI React and Laravel brand icons from the canonical monogram SVG."""
from pathlib import Path
import io
import cairosvg
from PIL import Image

ROOT = Path(__file__).resolve().parent.parent
REACT_SVG = ROOT / "public/static/mili-mark-20261013.svg"
ADMIN_SVG = ROOT / "public/assets/admin/img/mili-mark-20261013.svg"
IS_REACT = REACT_SVG.is_file()
SOURCE = REACT_SVG if IS_REACT else ADMIN_SVG
if not SOURCE.is_file():
    raise SystemExit("MILI monogram SVG is missing")
raw = cairosvg.svg2png(url=str(SOURCE), output_width=1536, output_height=1536)
base = Image.open(io.BytesIO(raw)).convert("RGBA")
mark = base.crop(base.getbbox())

def render(size, white=False, occupancy=0.74):
    w, h = size
    bg = Image.new("RGBA", (w, h), "white" if white else (0,0,0,0))
    scale = min((w * occupancy) / mark.width, (h * .68) / mark.height)
    scaled = mark.resize((max(1, round(mark.width*scale)), max(1, round(mark.height*scale))), Image.Resampling.LANCZOS)
    bg.alpha_composite(scaled, ((w-scaled.width)//2, (h-scaled.height)//2))
    return bg

def save(path, size, white=False, occupancy=0.74):
    path.parent.mkdir(parents=True, exist_ok=True)
    render(size, white, occupancy).save(path, format="PNG", optimize=True)
    print("generated", path.relative_to(ROOT), size)

def favicon(path):
    img = render((256,256), white=True).convert("RGB")
    img.save(path, format="ICO", sizes=[(16,16),(32,32),(48,48),(64,64),(128,128),(256,256)])
    print("generated", path.relative_to(ROOT), "favicon")

if IS_REACT:
    pub = ROOT / "public"
    static = pub / "static"
    save(pub/"apple-touch-icon.png", (180,180), True)
    save(pub/"icon-192.png", (192,192), True)
    save(pub/"icon-512.png", (512,512), True)
    save(static/"mili-mark-20261013.png", (640,400))
    for f in static.glob("mili*.png"):
        if f.name == "mili-mark-20261013.png":
            continue
        with Image.open(f) as old:
            size = old.size
        save(f, size, white=("favicon" in f.name), occupancy=.72)
    if (pub/"favicon.ico").exists():
        favicon(pub/"favicon.ico")
else:
    pub = ROOT / "public"
    admin = pub / "assets/admin/img"
    for name, size in [("favicon.png",(64,64)), ("6m-logo.png",(640,320)), ("invoice-logo.png",(640,320))]:
        path = admin / name
        if path.exists():
            save(path, size, white=(name=="favicon.png"))
    path = admin / "invoice/logo.png"
    if path.exists():
        save(path,(640,320))
    ico = pub/"favicon.ico"
    if ico.exists():
        favicon(ico)
print("MILI_SITE_ICONS_DONE")
