#!/usr/bin/env python3
"""Render PNG versions of the logo with headless Chrome (transparent backgrounds).

    python3 brand/export_png.py

PNGs are for tools that don't take SVG: Lightroom/Photoshop watermarks and the
WordPress Site Icon (Settings > General, or the Site Editor's site logo panel).
"""

import os
import re
import subprocess
import tempfile

HERE = os.path.dirname(os.path.abspath(__file__))
CHROME = os.environ.get("CHROME", "/Applications/Google Chrome.app/Contents/MacOS/Google Chrome")

# (source svg, output png, width in px)
EXPORTS = [
    ("wordmark-white.svg", "watermark-white.png", 3000),
    ("watermark-white-shadow.svg", "watermark-white-shadow.png", 3000),
    ("wordmark-black.svg", "watermark-black.png", 3000),
    ("wordmark-color.svg", "wordmark-color.png", 2000),
    ("watermark-viewfinder.svg", "watermark-viewfinder.png", 3000),
    ("icon-viewfinder.svg", "site-icon-512.png", 512),
]


def size_for(svg_path, width):
    with open(svg_path) as f:
        vb = re.search(r'viewBox="0 0 ([\d.]+) ([\d.]+)"', f.read())
    w, h = float(vb.group(1)), float(vb.group(2))
    return width, round(width * h / w)


def render(svg_path, png_path, width):
    w, h = size_for(svg_path, width)
    with tempfile.TemporaryDirectory() as tmp:
        page = os.path.join(tmp, "page.html")
        with open(page, "w") as f:
            f.write(
                '<!doctype html><meta charset="utf-8">'
                "<style>html,body{margin:0;background:transparent}img{display:block}</style>"
                f'<img src="file://{svg_path}" width="{w}" height="{h}">'
            )
        subprocess.run(
            [
                CHROME,
                "--headless=new",
                "--disable-gpu",
                "--hide-scrollbars",
                "--allow-file-access-from-files",
                "--default-background-color=00000000",
                f"--user-data-dir={os.path.join(tmp, 'profile')}",
                f"--window-size={w},{h}",
                f"--screenshot={png_path}",
                f"file://{page}",
            ],
            check=True,
            stdout=subprocess.DEVNULL,
            stderr=subprocess.DEVNULL,
        )
    print(f"wrote brand/png/{os.path.basename(png_path)} ({w}x{h})")


def main():
    out = os.path.join(HERE, "png")
    os.makedirs(out, exist_ok=True)
    for src, dst, width in EXPORTS:
        render(os.path.join(HERE, "svg", src), os.path.join(out, dst), width)


main()
