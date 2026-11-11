#!/usr/bin/env python3
"""
fetch_map.py — the map that goes behind a rank grid.

A grid of coloured squares is not a map. Without the ground under it nobody
can tell whether "strong in the north, gone in the south" means three blocks
or thirty kilometres, and the finding a grid exists to show — which direction
visibility falls off in — is unreadable. This builds a static map image for a
grid's own bounding box and emits it as a data URI the report can embed, so
the finished HTML stays a single self-contained file.

    python fetch_map.py run.json -o map.txt          # a BrightLocal run
    python fetch_map.py --bbox 43.71,-79.47,43.80,-79.35 -o map.txt
    python fetch_map.py run.json --into audit.json --keyword "emergency plumber"

`run.json` is anything carrying `grid_points` with latitude/longitude — the
output of `get_lsg_latest_run` pasted to disk, or an audit file's `local`
block. With `--into`, the data URI is written straight into that audit file's
`local.map_image`, which is the usual way to use this.

Tiles come from OpenStreetMap. They are fetched politely: one at a time, a
descriptive User-Agent with no personal details in it, and a hard cap on how
many tiles one run may request. A map wider than that cap is drawn at a lower
zoom rather than by fetching hundreds of tiles.
"""

import argparse
import base64
import io
import json
import math
import sys
import time
import urllib.request
from pathlib import Path

TILE = 256
MAX_TILES = 36          # 6x6. Past this, zoom out instead.
TILE_URL = "https://tile.openstreetmap.org/{z}/{x}/{y}.png"
# No personal or project details here: this is a request to a public service.
UA = "6ix-audit-report-map/1.0 (static map for a client report)"


def deg2num(lat, lon, z):
    rad = math.radians(lat)
    n = 2.0 ** z
    x = (lon + 180.0) / 360.0 * n
    y = (1.0 - math.asinh(math.tan(rad)) / math.pi) / 2.0 * n
    return x, y


def points_from(obj):
    """Pull grid points out of whatever shape was handed over."""
    if isinstance(obj, dict):
        for key in ("grid_points", "points"):
            if isinstance(obj.get(key), list):
                return obj[key]
        if isinstance(obj.get("local"), dict):
            kws = obj["local"].get("keywords") or []
            for kw in kws:
                p = points_from(kw)
                if p:
                    return p
        for v in obj.values():
            if isinstance(v, (dict, list)):
                p = points_from(v)
                if p:
                    return p
    if isinstance(obj, list):
        if obj and isinstance(obj[0], dict) and (
                "latitude" in obj[0] or "lat" in obj[0]):
            return obj
        for v in obj:
            p = points_from(v)
            if p:
                return p
    return []


def bbox_of(points):
    lats, lons = [], []
    for p in points:
        la = p.get("latitude", p.get("lat"))
        lo = p.get("longitude", p.get("lng", p.get("lon")))
        if la is None or lo is None:
            continue
        lats.append(float(la))
        lons.append(float(lo))
    if not lats:
        raise SystemExit("No latitude/longitude found in those points.")
    return min(lats), min(lons), max(lats), max(lons)


def pick_zoom(s_lat, w_lon, n_lat, e_lon):
    """The closest zoom whose tile count stays within MAX_TILES."""
    for z in range(18, 0, -1):
        x0, y0 = deg2num(n_lat, w_lon, z)
        x1, y1 = deg2num(s_lat, e_lon, z)
        tx = int(x1) - int(x0) + 1
        ty = int(y1) - int(y0) + 1
        if tx * ty <= MAX_TILES:
            return z, int(x0), int(y0), tx, ty
    raise SystemExit("Could not fit that area into a map.")


def build(s_lat, w_lon, n_lat, e_lon, pad=0.12, quiet=False):
    # A little padding, so the outermost grid points are not flush to the edge.
    dlat = (n_lat - s_lat) or 0.01
    dlon = (e_lon - w_lon) or 0.01
    s_lat -= dlat * pad; n_lat += dlat * pad
    w_lon -= dlon * pad; e_lon += dlon * pad

    z, tx0, ty0, tx, ty = pick_zoom(s_lat, w_lon, n_lat, e_lon)
    if not quiet:
        print(f"zoom {z}, {tx}x{ty} tiles", file=sys.stderr)

    try:
        from PIL import Image
    except ImportError:
        raise SystemExit(
            "This needs Pillow to stitch the tiles: pip install Pillow")

    canvas = Image.new("RGB", (tx * TILE, ty * TILE), "#e8e6e0")
    for i in range(tx):
        for j in range(ty):
            url = TILE_URL.format(z=z, x=tx0 + i, y=ty0 + j)
            req = urllib.request.Request(url, headers={"User-Agent": UA})
            try:
                with urllib.request.urlopen(req, timeout=20) as r:
                    tile = Image.open(io.BytesIO(r.read())).convert("RGB")
                canvas.paste(tile, (i * TILE, j * TILE))
            except Exception as exc:          # one missing tile is not fatal
                if not quiet:
                    print(f"  tile {z}/{tx0+i}/{ty0+j} failed: {exc}", file=sys.stderr)
            time.sleep(0.12)                  # polite, and well under any limit

    # Crop to the requested box so the image lines up with the grid drawn on it.
    fx0, fy0 = deg2num(n_lat, w_lon, z)
    fx1, fy1 = deg2num(s_lat, e_lon, z)
    left = int(round((fx0 - tx0) * TILE))
    top = int(round((fy0 - ty0) * TILE))
    right = int(round((fx1 - tx0) * TILE))
    bottom = int(round((fy1 - ty0) * TILE))
    if right > left and bottom > top:
        canvas = canvas.crop((left, top, right, bottom))

    buf = io.BytesIO()
    canvas.save(buf, format="JPEG", quality=78, optimize=True)
    return "data:image/jpeg;base64," + base64.b64encode(buf.getvalue()).decode()


def main():
    ap = argparse.ArgumentParser(description=__doc__,
                                 formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument("source", nargs="?", help="JSON carrying grid_points.")
    ap.add_argument("--bbox", help="south,west,north,east instead of a file.")
    ap.add_argument("-o", "--out", help="Write the data URI here.")
    ap.add_argument("--into", help="An audit JSON file to write local.map_image into.")
    ap.add_argument("--quiet", action="store_true")
    args = ap.parse_args()

    if args.bbox:
        try:
            s, w, n, e = (float(x) for x in args.bbox.split(","))
        except ValueError:
            raise SystemExit("--bbox wants south,west,north,east")
    elif args.source:
        data = json.loads(Path(args.source).read_text(encoding="utf-8"))
        pts = points_from(data)
        if not pts:
            raise SystemExit(f"No grid points found in {args.source}.")
        s, w, n, e = bbox_of(pts)
    else:
        raise SystemExit("Give a JSON file or --bbox.")

    uri = build(s, w, n, e, quiet=args.quiet)

    if args.into:
        path = Path(args.into)
        audit = json.loads(path.read_text(encoding="utf-8"))
        audit.setdefault("local", {})["map_image"] = uri
        path.write_text(json.dumps(audit, indent=2, ensure_ascii=False) + "\n",
                        encoding="utf-8")
        print(f"Wrote local.map_image into {path} ({len(uri):,} chars)")
    if args.out:
        Path(args.out).write_text(uri, encoding="utf-8")
        print(f"Wrote {args.out} ({len(uri):,} chars)")
    if not args.into and not args.out:
        print(uri)
    print("Map data © OpenStreetMap contributors — credit it in the report.",
          file=sys.stderr)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
