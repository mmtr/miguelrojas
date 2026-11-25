#!/usr/bin/env python3
"""Build the Miguel Rojas logo files from simple geometry (no font needed).

Each letter is a stroke along a centerline with rounded corners, on a 100-unit cap
height: squared letters in the spirit of the original logo, drawn flat.

    python3 brand/build.py           # writes brand/svg/*.svg and the theme's copies
    sh brand/export-png.sh           # renders PNGs (watermark, site icon) with Chrome
"""

import math
import os

HERE = os.path.dirname(os.path.abspath(__file__))
THEME_ASSETS = os.path.join(HERE, "..", "themes", "photos", "assets")

H = 100  # cap height
S = 20  # stroke weight
R = 14  # corner radius on the centerline: outer corners R + S/2, inner R - S/2
GAP = 30  # space between letters
SPACE = 64  # space between words

INK = "#1B1B1A"
RED = "#D7191F"
WHITE = "#FFFFFF"

HALF = S / 2


def rounded(points, closed=False):
    """SVG path through points, rounding every corner (axis-aligned turns only)."""
    pts = list(points)
    if closed:
        first_mid = ((pts[0][0] + pts[1][0]) / 2, (pts[0][1] + pts[1][1]) / 2)
        pts = [first_mid] + pts[1:] + [pts[0], first_mid]

    def step(a, b):
        dx, dy = b[0] - a[0], b[1] - a[1]
        length = math.hypot(dx, dy)
        return (dx / length, dy / length), length

    out = [f"M{pts[0][0]:g},{pts[0][1]:g}"]
    for i in range(1, len(pts) - 1):
        prev, here, nxt = pts[i - 1], pts[i], pts[i + 1]
        (ux, uy), d_in = step(prev, here)
        (vx, vy), d_out = step(here, nxt)
        r = min(R, d_in / 2, d_out / 2)
        sweep = 1 if ux * vy - uy * vx > 0 else 0
        start = (here[0] - ux * r, here[1] - uy * r)
        end = (here[0] + vx * r, here[1] + vy * r)
        out.append(f"L{start[0]:g},{start[1]:g}")
        out.append(f"A{r:g},{r:g} 0 0 {sweep} {end[0]:g},{end[1]:g}")
    if closed:
        out.append("Z")
    else:
        out.append(f"L{pts[-1][0]:g},{pts[-1][1]:g}")
    return "".join(out)


def box(w):
    """Centerline coordinates for a letter of outer width w."""
    return HALF, w - HALF, HALF, H - HALF, H / 2  # x0, x1, y0, y1, ym


# Each letter: width -> (strokes, filled polygons).
def letter_m(w):
    x0, x1, y0, y1, ym = box(w)
    return [rounded([(x0, H), (x0, y0), (x1, y0), (x1, H)]), rounded([(w / 2, y0), (w / 2, H)])], []


def letter_i(w):
    return [rounded([(HALF, 0), (HALF, H)])], []


def letter_g(w):
    x0, x1, y0, y1, ym = box(w)
    return [rounded([(w, y0), (x0, y0), (x0, y1), (x1, y1), (x1, ym), (w * 0.52, ym)])], []


def letter_u(w):
    x0, x1, y0, y1, ym = box(w)
    return [rounded([(x0, 0), (x0, y1), (x1, y1), (x1, 0)])], []


def letter_e(w):
    x0, x1, y0, y1, ym = box(w)
    return [rounded([(w, y0), (x0, y0), (x0, y1), (w, y1)]), rounded([(x0, ym), (w - 10, ym)])], []


def letter_l(w):
    x0, x1, y0, y1, ym = box(w)
    return [rounded([(x0, 0), (x0, y1), (w, y1)])], []


def letter_r(w):
    x0, x1, y0, y1, ym = box(w)
    bowl = rounded([(x0, H), (x0, y0), (x1, y0), (x1, ym), (x0, ym)])
    leg_w = S * 1.25  # horizontal width of the diagonal leg, so it reads as one stroke
    leg_x = w * 0.42
    leg = [(leg_x, ym), (leg_x + leg_w, ym), (w, H), (w - leg_w, H)]
    return [bowl], [leg]


def letter_o(w):
    x0, x1, y0, y1, ym = box(w)
    return [rounded([(x0, y0), (x1, y0), (x1, y1), (x0, y1)], closed=True)], []


def letter_j(w):
    x0, x1, y0, y1, ym = box(w)
    return [rounded([(x1, 0), (x1, y1), (x0, y1), (x0, H * 0.55)])], []


def letter_a(w):
    x0, x1, y0, y1, ym = box(w)
    return [rounded([(x0, H), (x0, y0), (x1, y0), (x1, H)]), rounded([(x0, ym + 6), (x1, ym + 6)])], []


def letter_s(w):
    x0, x1, y0, y1, ym = box(w)
    return [rounded([(w, y0), (x0, y0), (x0, ym), (x1, ym), (x1, y1), (0, y1)])], []


LETTERS = {
    "M": (letter_m, 170),
    "I": (letter_i, S),
    "G": (letter_g, 116),
    "U": (letter_u, 116),
    "E": (letter_e, 104),
    "L": (letter_l, 100),
    "R": (letter_r, 116),
    "O": (letter_o, 116),
    "J": (letter_j, 104),
    "A": (letter_a, 116),
    "S": (letter_s, 116),
}


def word(text, x=0.0, gap=GAP, widths=None):
    """Strokes and polygons for a word starting at x; returns (strokes, polys, end_x)."""
    strokes, polys = [], []
    for i, ch in enumerate(text):
        draw, default_w = LETTERS[ch]
        w = (widths or {}).get(ch, default_w)
        s, p = draw(w)
        strokes += [f'<path transform="translate({x:g} 0)" d="{d}"/>' for d in s]
        polys += [
            '<polygon points="' + " ".join(f"{px + x:g},{py:g}" for px, py in poly) + '"/>' for poly in p
        ]
        x += w + (gap if i < len(text) - 1 else 0)
    return strokes, polys, x


def group(strokes, polys, color, extra=""):
    stroke_group = f'<g fill="none" stroke="{color}" stroke-width="{S}" stroke-linecap="butt"{extra}>{"".join(strokes)}</g>'
    poly_group = f'<g fill="{color}"{extra}>{"".join(polys)}</g>' if polys else ""
    return stroke_group + poly_group


def wordmark(first_color, last_color, pad=0, shadow=False, inline=False):
    s1, p1, end = word("MIGUEL")
    s2, p2, total = word("ROJAS", end + SPACE)
    w, h = total + pad * 2, H + pad * 2
    defs = ""
    attrs = ""
    if shadow:
        defs = (
            '<defs><filter id="shadow" x="-5%" y="-30%" width="110%" height="160%">'
            '<feDropShadow dx="0" dy="2" stdDeviation="3" flood-color="#000" flood-opacity="0.45"/>'
            "</filter></defs>"
        )
        attrs = ' filter="url(#shadow)"'
    if inline:
        # For the theme: colors come from CSS (currentColor), the svg is decorative.
        head = f'<svg class="photos-logo__svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {w:g} {h:g}" aria-hidden="true" focusable="false">'
        body = (
            f'<g class="photos-logo__first">{group(s1, p1, "currentColor")}</g>'
            f'<g class="photos-logo__last">{group(s2, p2, "currentColor")}</g>'
        )
    else:
        head = (
            f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {w:g} {h:g}" width="{w:g}" height="{h:g}" '
            'role="img" aria-labelledby="title"><title id="title">Miguel Rojas</title>'
        )
        body = f'<g transform="translate({pad:g} {pad:g})"{attrs}>{group(s1, p1, first_color)}{group(s2, p2, last_color)}</g>'
    return head + defs + body + "</svg>\n"


def monogram(background, letters_color, side=300, radius=36):
    widths = {"M": 120, "R": 84}
    strokes, polys, w = word("MR", gap=20, widths=widths)
    x, y = (side - w) / 2, (side - H) / 2
    bg = f'<rect width="{side}" height="{side}" rx="{radius}" fill="{background}"/>' if background else ""
    return (
        f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {side} {side}" width="{side}" height="{side}" '
        'role="img" aria-labelledby="title"><title id="title">Miguel Rojas</title>'
        f'{bg}<g transform="translate({x:g} {y:g})">{group(strokes, polys, letters_color)}</g></svg>\n'
    )


def corners(x0, y0, x1, y1, arm, weight, color):
    """Viewfinder corner brackets, drawn inside the box (x0, y0)-(x1, y1)."""
    a, b, c, d = x0 + weight / 2, y0 + weight / 2, x1 - weight / 2, y1 - weight / 2
    path = (
        f"M{a:g},{b + arm:g}V{b:g}H{a + arm:g}M{c - arm:g},{b:g}H{c:g}V{b + arm:g}"
        f"M{a:g},{d - arm:g}V{d:g}H{a + arm:g}M{c - arm:g},{d:g}H{c:g}V{d - arm:g}"
    )
    return f'<path d="{path}" fill="none" stroke="{color}" stroke-width="{weight:g}" stroke-linecap="square"/>'


# The viewfinder: red corners around the name, 1.1 cap heights away from it (the
# watermark), or around the monogram (the favicon).
VF_PAD = 110
VF_WEIGHT = 17


def viewfinder_watermark():
    """White name with a soft shadow, in red viewfinder corners: the photos' watermark."""
    s1, p1, end = word("MIGUEL")
    s2, p2, total = word("ROJAS", end + SPACE)
    w, h = total + VF_PAD * 2, H + VF_PAD * 2
    # A soft shadow below the letters plus a thin dark halo around them, so the white name
    # stays readable on bright photos (sizes are in the name's units: letters are 20 thick).
    shadow = (
        '<defs><filter id="shadow" x="-5%" y="-60%" width="110%" height="220%">'
        '<feGaussianBlur in="SourceAlpha" stdDeviation="9" result="soft"/>'
        '<feOffset in="soft" dy="4" result="softdown"/>'
        '<feFlood flood-color="#000" flood-opacity="0.55"/><feComposite in2="softdown" operator="in" result="shadow"/>'
        '<feGaussianBlur in="SourceAlpha" stdDeviation="2.5" result="tight"/>'
        '<feFlood flood-color="#000" flood-opacity="0.5"/><feComposite in2="tight" operator="in" result="halo"/>'
        '<feMerge><feMergeNode in="shadow"/><feMergeNode in="halo"/><feMergeNode in="SourceGraphic"/></feMerge>'
        "</filter></defs>"
    )
    return (
        f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {w:g} {h:g}" width="{w:g}" height="{h:g}" '
        'role="img" aria-labelledby="title"><title id="title">Miguel Rojas</title>'
        f"{shadow}{corners(0, 0, w, h, VF_PAD, VF_WEIGHT, RED)}"
        f'<g transform="translate({VF_PAD} {VF_PAD})" filter="url(#shadow)">{group(s1, p1, WHITE)}{group(s2, p2, WHITE)}</g>'
        "</svg>\n"
    )


def viewfinder_icon(side=300):
    """The favicon / site icon: black MR in red viewfinder corners on paper."""
    widths = {"M": 120, "R": 84}
    strokes, polys, w = word("MR", gap=20, widths=widths)
    scale = side * 0.625 / w
    x, y = (side - w * scale) / 2, (side - H * scale) / 2
    inset, arm, weight = side * 0.094, side * 0.28, side * 0.078
    return (
        f'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {side} {side}" width="{side}" height="{side}" '
        'role="img" aria-labelledby="title"><title id="title">Miguel Rojas</title>'
        f'<rect width="{side}" height="{side}" rx="{side * 0.1875:g}" fill="#F6F5F2"/>'
        f"{corners(inset, inset, side - inset, side - inset, arm, weight, RED)}"
        f'<g transform="translate({x:g} {y:g}) scale({scale:g})">{group(strokes, polys, INK)}</g></svg>\n'
    )


def write(path, content):
    os.makedirs(os.path.dirname(path), exist_ok=True)
    with open(path, "w") as f:
        f.write(content)
    print("wrote", os.path.relpath(path, os.path.join(HERE, "..")))


def main():
    svg = os.path.join(HERE, "svg")
    write(os.path.join(svg, "wordmark-color.svg"), wordmark(INK, RED))
    write(os.path.join(svg, "wordmark-color-on-dark.svg"), wordmark(WHITE, RED))
    write(os.path.join(svg, "wordmark-black.svg"), wordmark(INK, INK))
    write(os.path.join(svg, "wordmark-white.svg"), wordmark(WHITE, WHITE))
    write(os.path.join(svg, "watermark-white-shadow.svg"), wordmark(WHITE, WHITE, pad=12, shadow=True))
    write(os.path.join(svg, "monogram-color.svg"), monogram(RED, WHITE))
    write(os.path.join(svg, "monogram-black.svg"), monogram(None, INK))
    write(os.path.join(svg, "monogram-white.svg"), monogram(None, WHITE))
    write(os.path.join(svg, "watermark-viewfinder.svg"), viewfinder_watermark())
    write(os.path.join(svg, "icon-viewfinder.svg"), viewfinder_icon())

    write(os.path.join(THEME_ASSETS, "logo.svg"), wordmark(None, None, inline=True))
    write(os.path.join(THEME_ASSETS, "favicon.svg"), viewfinder_icon())


main()
