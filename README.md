# DotComSA Website

Online clothing store for **DotComSA**: men, women and kids, delivered anywhere in South Africa.
Built and maintained by **TLC (Talent Learn Consult)**. GitHub is the single source of truth for this site.

## Status

**Phase 1: preview build (not live).** First 3 real products loaded (23 Oct 2026); more to come.

| Phase | What it covers | Status |
| --- | --- | --- |
| Phase 1 | Full store, cart, orders sent to the business on WhatsApp or email | Built, awaiting client content and review |
| Phase 2 | Card payments through PayFast with automatic commission split, admin area, monthly report (Xneelo, PHP + MySQL) | Built and tested locally (PayFast simulated); needs Xneelo test site and PayFast sandbox test. See [docs/PAYMENTS-SETUP.md](docs/PAYMENTS-SETUP.md) |

## How the site works

- Shop front: plain HTML, CSS and JavaScript, no build step. Runs on GitHub Pages (preview) and Xneelo.
- Phase 2 server: PHP + MySQL in `api/` and `admin/`, runs on Xneelo only. On the GitHub preview it is ignored and the shop keeps WhatsApp ordering.
- The cart is saved only in the shopper's own browser.
- At checkout the shopper's own WhatsApp (or email app) opens with the full order typed out. Nothing is stored on a server.

## Where to change things

| To change... | Edit this file |
| --- | --- |
| Phone, WhatsApp number, email, delivery fee, social links | `assets/js/config.js` |
| Products, prices, sizes, photos, sold-out status | `assets/js/products.js` (keep strict JSON format: the payment server reads it too) |
| Payment, database, commission, admin logins (server only, never in GitHub) | `api/config.local.php` from `api/config.sample.php` |
| Product photos | `assets/img/products/` |
| Colours and layout | `assets/css/styles.css` |
| Page wording | the matching `.html` file |

When the real products go in, set `window.DOTCOMSA_SAMPLE_DATA = false;` in `products.js` to remove the "preview" banner.

## Pages

Home, Shop (all, men, women, kids), Product, Cart and checkout, About, Contact, Delivery and returns, Terms, Privacy, 404.

## Testing

Every change must pass the automated check before it is pushed:

```
python3 -m http.server 8765 &
python3 tools/site_test.py http://localhost:8765
```

It checks every page on desktop and mobile for JavaScript errors, broken links, broken images, sideways scrolling, and runs a full cart-to-WhatsApp order.

Payment server tests (Phase 2):

```
php tests/run.php                       # 55 checks: pricing, commission, PayFast form and notices, refunds
python3 tests/e2e_payments.py           # browser test: cart > PayFast (simulated) > thank-you > admin
```

`tests/run.php` uses a throwaway SQLite database by default; set `DCS_TEST_DSN`, `DCS_TEST_USER`, `DCS_TEST_PASS` to test against MySQL. See the top of `tests/e2e_payments.py` for its setup. Results are recorded in [docs/TESTING.md](docs/TESTING.md).

## Documents

- [docs/SPEC.md](docs/SPEC.md): what we're building, from the client's Website Brief
- [docs/OPEN-QUESTIONS.md](docs/OPEN-QUESTIONS.md): what we still need from the client
- [docs/TESTING.md](docs/TESTING.md): test record
- [docs/RELEASE-NOTES.md](docs/RELEASE-NOTES.md): what changed in each version
- [docs/GO-LIVE.md](docs/GO-LIVE.md): go-live checklist and rollback plan
- [docs/PAYMENTS-SETUP.md](docs/PAYMENTS-SETUP.md): card payments, step by step to go-live

## Release process (TLC Website Delivery Blueprint)

1. Build in GitHub.
2. Automated test passes.
3. Preview link reviewed by Dion, then by the client.
4. Deploy to Xneelo for full testing.
5. Client sign-off (Joseph Mathenjwa).
6. Dion's written go-ahead.
7. Go live, with the rollback plan ready.

Nothing goes live on the client's domain without steps 5 and 6.
