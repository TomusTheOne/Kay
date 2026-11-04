#!/usr/bin/env bash
# Rebuild the cenote dive maps. Run from anywhere; see ../README.md.
#   tools/build.sh            labels only: base/*.png -> public/assets/maps/
#   tools/build.sh --trace    re-trace base/*.png from originals/ first
set -euo pipefail
cd "$(dirname "$0")/.."
T=tools
mkdir -p .build
if [[ "${1:-}" == "--trace" ]]; then
  python3 $T/casa_mask.py originals/casa-cenote.webp .build/casa-mask.png
  python3 $T/casa.py originals/casa-cenote.webp .build/casa-mask.png base/casa-cenote.png
  python3 $T/plan.py dos_ojos  originals/dos-ojos.webp  base/dos-ojos.png
  python3 $T/plan.py aktun_ha  originals/car-wash.webp  base/car-wash.png lite=.10
  python3 $T/plan.py dreamgate originals/dreamgate.webp base/dreamgate.png thin=1 lite=.12
  python3 $T/section.py el_pit   originals/el-pit.png    base/el-pit.png 1300
  python3 $T/section.py angelita originals/angelita.webp base/angelita.png 1600
fi
python3 $T/overlay.py
node $T/shoot.mjs "$PWD/.build/jobs.json"
python3 $T/export.py
