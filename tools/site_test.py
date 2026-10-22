"""
DotComSA automated site check.

What it checks, on desktop AND mobile:
  - every page loads with no JavaScript errors and no failed requests
  - every internal link points to a page that exists
  - every image loads
  - every product page renders
  - the cart flow: add to cart, quantity, validation, WhatsApp order message
  - no horizontal scrolling on a phone-sized screen

How to run (from the repo folder):
  python3 -m http.server 8765 &
  python3 tools/site_test.py http://localhost:8765 [screenshot-folder]

Exit code 0 = all checks passed.
"""
import re
import sys
import urllib.parse
import urllib.request
from playwright.sync_api import sync_playwright

BASE = sys.argv[1].rstrip("/") if len(sys.argv) > 1 else "http://localhost:8765"
SHOTS = sys.argv[2] if len(sys.argv) > 2 else None
PAGES = ["index.html", "shop.html", "shop.html?cat=men", "shop.html?cat=women", "shop.html?cat=kids",
         "cart.html", "about.html", "contact.html", "delivery.html", "terms.html", "privacy.html",
         "404.html", "product.html?id=does-not-exist"]

failures = []


def fail(msg):
    failures.append(msg)
    print("  FAIL:", msg)


def check_page(page, path, label):
    errors, bad = [], []
    page.on("pageerror", lambda e: errors.append(str(e)))
    page.on("console", lambda m: errors.append(m.text) if m.type == "error" else None)
    page.on("requestfailed", lambda r: bad.append(r.url) if r.url.startswith(BASE) else None)
    page.on("response", lambda r: bad.append(f"{r.status} {r.url}") if r.url.startswith(BASE) and r.status >= 400 else None)
    page.goto(f"{BASE}/{path}", wait_until="networkidle")
    for e in errors:
        fail(f"[{label}] {path}: JS error: {e}")
    for b in bad:
        fail(f"[{label}] {path}: failed request: {b}")
    broken = page.evaluate("""() => [...document.images].filter(i => !i.complete || i.naturalWidth === 0).map(i => i.src)""")
    for src in broken:
        fail(f"[{label}] {path}: image did not load: {src}")
    overflow = page.evaluate("() => document.documentElement.scrollWidth - window.innerWidth")
    if overflow > 1:
        fail(f"[{label}] {path}: page scrolls sideways by {overflow}px")
    if not page.query_selector("header.header") or not page.query_selector("footer.footer"):
        fail(f"[{label}] {path}: header or footer missing")
    links = page.evaluate("() => [...document.querySelectorAll('a[href]')].map(a => a.getAttribute('href'))")
    return links


def main():
    all_links = set()
    with sync_playwright() as p:
        browser = p.chromium.launch()
        for label, opts in [("desktop", {"viewport": {"width": 1366, "height": 900}}),
                            ("mobile", {"viewport": {"width": 375, "height": 812}, "is_mobile": True, "has_touch": True})]:
            print(f"== {label} ==")
            ctx = browser.new_context(**opts)
            # product ids
            pg = ctx.new_page()
            pg.goto(f"{BASE}/index.html")
            ids = pg.evaluate("() => window.DOTCOMSA_PRODUCTS.map(p => p.id)")
            pg.close()
            for path in PAGES + [f"product.html?id={i}" for i in ids]:
                pg = ctx.new_page()
                links = check_page(pg, path, label)
                all_links.update(links)
                if SHOTS and path in ("index.html", "shop.html", "cart.html", f"product.html?id={ids[0]}", "delivery.html"):
                    name = re.sub(r"[^a-z0-9]+", "-", path.lower())
                    pg.screenshot(path=f"{SHOTS}/{label}-{name}.png", full_page=True)
                pg.close()

            # product page shows name
            pg = ctx.new_page()
            pg.goto(f"{BASE}/product.html?id={ids[0]}", wait_until="networkidle")
            if not pg.query_selector("h1") or "Loading" in pg.inner_text("#product-root"):
                fail(f"[{label}] product page did not render")

            # cart flow
            print("  cart flow")
            pg.click("button[type=submit]")
            if "Please choose a size" not in pg.inner_text("#add-msg"):
                fail(f"[{label}] missing size validation")
            pg.click("label[for=size-1]")
            pg.click("label[for=colour-0]")
            pg.click("[data-q='1']")
            pg.click("button[type=submit]")
            count = pg.inner_text("#cart-count")
            if count != "2":
                fail(f"[{label}] cart count expected 2, got {count}")
            pg.goto(f"{BASE}/cart.html", wait_until="networkidle")
            if pg.is_hidden("#cart-summary"):
                fail(f"[{label}] cart summary hidden with items in cart")
            opened = []
            pg.evaluate("() => { window.open = (u) => { window.__opened = u; }; }")
            pg.click("#send-wa")
            if pg.evaluate("() => window.__opened || null"):
                fail(f"[{label}] order sent without delivery details")
            pg.fill("#f-name", "Test Customer")
            pg.fill("#f-phone", "0821234567")
            pg.fill("#f-address", "1 Test Street")
            pg.fill("#f-city", "Umhlanga, Durban")
            pg.select_option("#f-province", "KwaZulu-Natal")
            pg.fill("#f-postal", "4319")
            pg.click("#send-wa")
            url = pg.evaluate("() => window.__opened || ''")
            if not url.startswith("https://wa.me/27820493930?text="):
                fail(f"[{label}] WhatsApp link wrong: {url[:80]}")
            text = urllib.parse.unquote(url.split("text=", 1)[-1])
            for must in ["New order DCS-", "2 x ", "Total: R", "Test Customer", "KwaZulu-Natal", "4319"]:
                if must not in text:
                    fail(f"[{label}] order message missing '{must}'")
            if pg.is_hidden("#order-done"):
                fail(f"[{label}] confirmation not shown after sending")
            pg.click("#clear-cart")
            if pg.inner_text("#cart-count") != "0":
                fail(f"[{label}] cart not cleared")
            if label == "desktop":
                print("  sample order message:\n    " + text.replace("\n", "\n    "))
            ctx.close()
        browser.close()

    # internal link check
    print("== links ==")

    for href in sorted(all_links):
        if href.startswith(("http", "tel:", "mailto:", "#")):
            if href.startswith("http") and not href.startswith(("https://wa.me/",)):
                fail(f"unexpected external link: {href}")
            continue
        target = href.split("#")[0]
        try:
            code = urllib.request.urlopen(f"{BASE}/{target}").status
        except Exception as e:  # noqa
            code = str(e)
        if code != 200:
            fail(f"broken link {href}: {code}")
    print(f"checked {len(all_links)} unique links")

    print("\nRESULT:", "PASS" if not failures else f"FAIL ({len(failures)} problems)")
    sys.exit(1 if failures else 0)


if __name__ == "__main__":
    main()
