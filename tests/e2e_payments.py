"""
DotComSA end-to-end payment test (Phase 2), with PayFast simulated.

Needs two local servers from the repo folder:
  python3 -m http.server 8765                                  (acts like the GitHub preview)
  DCS_CONFIG=/path/test-config.php php -S localhost:8770 -t .  (acts like Xneelo, PHP + database)
Then:
  DCS_CONFIG=/path/test-config.php python3 tests/e2e_payments.py [admin-password] [screenshot-folder]

The test config must use site_url http://localhost:8770, the PayFast sandbox, split
merchant ID 10000105, commission_bp 500 (test rate), allow_whatsapp_orders false,
and an admin user "tlc".
"""
import json
import os
import subprocess
import sys
import urllib.parse
from playwright.sync_api import sync_playwright

STATIC = "http://localhost:8765"
PHP = "http://localhost:8770"
ADMIN_PASS = sys.argv[1] if len(sys.argv) > 1 else "Test-password-123"
SHOTS = sys.argv[2] if len(sys.argv) > 2 else None
HERE = os.path.dirname(os.path.abspath(__file__))
failures = []


def check(name, ok, why=""):
    print(("  ok   " if ok else "  FAIL ") + name + (f" ({why})" if why and not ok else ""))
    if not ok:
        failures.append(name)


def add_to_cart(page, base, pid, size_idx=0, colour_idx=0, extra_qty=0):
    page.goto(f"{base}/product.html?id={pid}", wait_until="networkidle")
    page.click(f"label[for=size-{size_idx}]")
    page.click(f"label[for=colour-{colour_idx}]")
    for _ in range(extra_qty):
        page.click("#add-form [data-q='1']")
    page.click("#add-form button[type=submit]")


def fill_details(page, email=True):
    page.fill("#f-name", "Thandi Nkosi")
    if email:
        page.fill("#f-email", "thandi@example.com")
    page.fill("#f-phone", "082 123 4567")
    page.fill("#f-address", "12 Test Street")
    page.fill("#f-city", "Umlazi, Durban")
    page.select_option("#f-province", "KwaZulu-Natal")
    page.fill("#f-postal", "4031")


def main():
    with sync_playwright() as p:
        browser = p.chromium.launch()

        print("== GitHub preview (no server) keeps WhatsApp ordering ==")
        ctx = browser.new_context()
        page = ctx.new_page()
        add_to_cart(page, STATIC, "womens-off-shoulder-top")
        page.goto(f"{STATIC}/cart.html", wait_until="networkidle")
        page.wait_for_timeout(800)
        check("card payment hidden", page.is_hidden("#card-pay"))
        check("WhatsApp ordering shown", page.is_visible("#send-wa"))
        check("email field hidden", page.is_hidden("#email-field"))
        ctx.close()

        print("== Xneelo-style server: card payment ==")
        ctx = browser.new_context(viewport={"width": 390, "height": 844}, is_mobile=True, has_touch=True)
        page = ctx.new_page()
        errors = []
        page.on("pageerror", lambda e: errors.append(str(e)))
        add_to_cart(page, PHP, "strap-platform-sneakers", size_idx=2, colour_idx=1)
        add_to_cart(page, PHP, "mens-striped-knit-tee", size_idx=1, colour_idx=0, extra_qty=1)
        page.goto(f"{PHP}/cart.html", wait_until="networkidle")
        page.wait_for_selector("#card-pay:not([hidden])", timeout=5000)
        check("card payment button shown", page.is_visible("#pay-card"))
        check("WhatsApp ordering hidden (card-only)", page.is_hidden("#manual-order"))
        check("email field shown", page.is_visible("#f-email"))

        # Validation before anything is sent
        page.click("#pay-card")
        check("empty form blocked", "Please enter your name" in page.inner_text("#order-form"))

        captured = {}

        def payfast(route):
            captured["url"] = route.request.url
            captured["body"] = route.request.post_data or ""
            route.fulfill(status=200, content_type="text/html", body="<h1>Fake PayFast</h1>")

        page.route("https://sandbox.payfast.co.za/**", payfast)
        fill_details(page)
        if SHOTS:
            page.screenshot(path=f"{SHOTS}/e2e-cart.png", full_page=True)
        page.click("#pay-card")
        page.wait_for_url("https://sandbox.payfast.co.za/**", timeout=10000)
        fields = dict(urllib.parse.parse_qsl(captured.get("body", "")))
        check("posted to PayFast sandbox", captured.get("url") == "https://sandbox.payfast.co.za/eng/process")
        # sneakers R350 + 2 x tee R250 = R850 clothing, under R1,000 so R100 delivery
        check("amount = R850 clothing + R100 delivery", fields.get("amount") == "950.00", fields.get("amount"))
        setup = json.loads(fields.get("setup", "{}"))
        check("TLC split = 5% (test rate) of R850 = R42.50 (4250 cents)",
              setup.get("split_payment", {}) == {"merchant_id": 10000105, "amount": 4250}, str(setup))
        check("signature present", len(fields.get("signature", "")) == 32)
        check("customer email passed", fields.get("email_address") == "thandi@example.com")
        ref = fields.get("m_payment_id", "")
        check("order reference present", ref.startswith("DCS-"))

        # Price tampering in the browser must not change what is charged
        tamper = page.request.post(f"{PHP}/api/checkout.php", data=json.dumps({
            "items": [{"id": "strap-platform-sneakers", "size": "5", "colour": "Black", "qty": 1, "price": 1}],
            "customer": {"name": "X Y", "email": "x@example.com", "phone": "0821234567", "address": "1 Road",
                         "city": "Durban", "province": "KwaZulu-Natal", "postal": "4001"}}),
            headers={"Content-Type": "application/json"})
        check("tampered price ignored", tamper.json()["fields"]["amount"] == "450.00", tamper.text()[:120])

        # Return page while payment still pending
        return_url = fields["return_url"]
        page.goto(return_url, wait_until="networkidle")
        page.wait_for_timeout(600)
        check("thank-you page waits while unpaid", "Confirming your payment" in page.inner_text("#thanks-status"))

        # PayFast confirms payment
        out = subprocess.run(["php", os.path.join(HERE, "simulate_itn.php"), ref], capture_output=True, text=True, env=os.environ)
        check("payment notice accepted", out.stdout.strip() == "paid", out.stdout + out.stderr)
        page.wait_for_selector("text=Payment received", timeout=8000)
        check("thank-you page shows payment received", ref in page.inner_text("#thanks-status"))
        check("cart emptied after payment", page.inner_text("#cart-count") == "0")
        if SHOTS:
            page.screenshot(path=f"{SHOTS}/e2e-thanks.png", full_page=True)

        # Wrong token cannot read the order
        bad = page.request.get(f"{PHP}/api/order-status.php?ref={ref}&t=wrong")
        check("order status needs the secret token", bad.status == 404)

        # Cancelled at PayFast
        page.goto(f"{PHP}/cart.html?cancelled=1", wait_until="networkidle")
        check("cancel message shown", "Payment cancelled" in page.inner_text("#cart-notice"))
        check("no JavaScript errors", not errors, "; ".join(errors))
        ctx.close()

        print("== Admin area ==")
        ctx = browser.new_context()
        page = ctx.new_page()
        page.goto(f"{PHP}/admin/index.php", wait_until="networkidle")
        page.fill("input[name=username]", "tlc")
        page.fill("input[name=password]", "wrong-password")
        page.click("button[type=submit]")
        check("wrong password refused", "Wrong username or password" in page.content())
        page.fill("input[name=username]", "tlc")
        page.fill("input[name=password]", ADMIN_PASS)
        page.click("button[type=submit]")
        page.wait_for_load_state("networkidle")
        check("signed in", "Signed in as" in page.content())
        check("paid order listed", ref in page.content())
        page.click(f"text={ref}")
        page.wait_for_load_state("networkidle")
        body = page.inner_text("main")
        check("order shows total and commission", "R950.00" in body and "R42.50" in body)
        page.select_option("select[name=fulfilment]", "shipped")
        page.click("form.inline >> text=Save")
        page.wait_for_load_state("networkidle")
        check("marked as sent", "Delivery status updated" in page.content())
        page.fill("input[name=refund_amount]", "250")
        page.fill("input[name=note]", "Exchange: wrong size")
        page.click("text=Record refund")
        page.wait_for_load_state("networkidle")
        body = page.inner_text("main")
        check("refund recorded with commission adjustment (R12.50)", "Refund recorded" in body and "R12.50" in body)
        if SHOTS:
            page.screenshot(path=f"{SHOTS}/e2e-admin-order.png", full_page=True)
        page.goto(f"{PHP}/admin/report.php", wait_until="networkidle")
        body = page.inner_text("main")
        check("monthly report shows the commission", "R42.50" in body)
        check("commission after refunds = R30.00", "R30.00" in body)
        csv = page.request.get(f"{PHP}/admin/report.php?format=csv")
        check("CSV download works", csv.status == 200 and ref in csv.text())
        if SHOTS:
            page.screenshot(path=f"{SHOTS}/e2e-report.png", full_page=True)
        page.goto(f"{PHP}/admin/logout.php", wait_until="networkidle")
        r = page.request.get(f"{PHP}/admin/report.php", max_redirects=0)
        check("signed out: report not accessible", r.status in (301, 302))
        browser.close()

    print("\nRESULT:", "PASS" if not failures else f"FAIL ({len(failures)})")
    sys.exit(1 if failures else 0)


if __name__ == "__main__":
    main()
