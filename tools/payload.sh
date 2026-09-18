#!/usr/bin/env bash
# Measure the front-end payload of a page: HTML, inline CSS, external CSS/JS/fonts/images.
# Usage: tools/payload.sh <url>   (dev tool; not loaded by WordPress)
set -euo pipefail
URL="${1:?usage: payload.sh <url>}"
UA="Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128 Safari/537.36"
TMP="$(mktemp -d)"
curl -s --noproxy '*' -A "$UA" "$URL" -o "$TMP/page.html" -w '%{size_download}\n' > "$TMP/html.size"
python3 - "$URL" "$TMP" <<'PY'
import re, sys, subprocess, gzip, urllib.parse
url, tmp = sys.argv[1], sys.argv[2]
html = open(f"{tmp}/page.html", encoding="utf-8", errors="replace").read()
def size(u):
    u = urllib.parse.urljoin(url, u)
    try:
        out = subprocess.run(["curl","-s","--noproxy","*","-o","/dev/null","-w","%{size_download} %{content_type}", u], capture_output=True, text=True, timeout=30).stdout.split(" ",1)
        return int(out[0]), (out[1] if len(out)>1 else "")
    except Exception: return 0, "?"
def gz(s): return len(gzip.compress(s.encode("utf-8")))
inline_css = re.findall(r"<style[^>]*>(.*?)</style>", html, re.S)
css_links = re.findall(r"<link[^>]+rel=['\"]stylesheet['\"][^>]+href=['\"]([^'\"]+)['\"]", html) + re.findall(r"<link[^>]+href=['\"]([^'\"]+)['\"][^>]+rel=['\"]stylesheet['\"]", html)
scripts = re.findall(r"<script[^>]+src=['\"]([^'\"]+)['\"]", html)
modules = re.findall(r"<script[^>]*type=['\"]module['\"][^>]*src=['\"]([^'\"]+)['\"]", html) + re.findall(r"<script[^>]*src=['\"]([^'\"]+)['\"][^>]*type=['\"]module['\"]", html)
preloads = re.findall(r"<link[^>]+rel=['\"]preload['\"][^>]+href=['\"]([^'\"]+)['\"]", html)
fonts = re.findall(r"url\(['\"]?([^'\")]+\.woff2)", html)
imgs = re.findall(r"<img[^>]+src=['\"]([^'\"]+)['\"]", html)
print(f"URL: {url}")
print(f"HTML: {len(html.encode())} bytes ({gz(html)} gz) — inline <style> blocks: {len(inline_css)} = {sum(len(c) for c in inline_css)} bytes ({gz(''.join(inline_css))} gz)")
tot_css = tot_js = tot_font = 0
print("External CSS:")
for u in dict.fromkeys(css_links):
    s,_ = size(u); tot_css += s; print(f"  {s:>8}  {u.split('?')[0].split('/wp-')[-1]}")
print("Scripts:")
for u in dict.fromkeys(scripts):
    s,_ = size(u); tot_js += s; print(f"  {s:>8}  {u.split('?')[0].split('/wp-')[-1]}")
print("Fonts:")
for u in dict.fromkeys(fonts):
    s,_ = size(u); tot_font += s; print(f"  {s:>8}  {u.split('/')[-1]}")
print(f"Script modules: {len(set(modules))} | Preloads: {len(preloads)} | <img> tags: {len(imgs)}")
lazy = len(re.findall(r'<img[^>]+loading=["\']lazy', html)); hi = len(re.findall(r'fetchpriority=["\']high', html))
print(f"Images lazy-loaded: {lazy}/{len(imgs)} | fetchpriority=high: {hi}")
print(f"TOTAL external CSS {tot_css} B | JS {tot_js} B | fonts {tot_font} B | HTML+inline {len(html.encode())} B ({gz(html)} B gz)")
print(f"Requests (doc + css + js + fonts + imgs): {1+len(set(css_links))+len(set(scripts))+len(set(fonts))+len(set(imgs))}")
PY
rm -rf "$TMP"
