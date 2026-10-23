# Card payments (Phase 2): setup on Xneelo

The code is built and tested. These steps put it on a server, in order. Steps marked **Dion** or **Client** cannot be done by Claude.

Until step 8, everything runs in **PayFast test mode**: no real money moves.

## What was built

| Part | Where |
| --- | --- |
| Checkout: prices, delivery and TLC's commission worked out on the server, sent to PayFast | `api/checkout.php` |
| PayFast payment notice (ITN): signature, PayFast server, merchant, amount and PayFast confirmation all checked before an order is marked paid | `api/notify.php` |
| Order status for the thank-you page (needs the order's secret token) | `api/order-status.php`, `thankyou.html` |
| Switch: shop front shows card payment only when this server answers | `api/status.php` |
| Shared logic and database (MySQL on Xneelo, created automatically) | `api/lib/core.php` |
| Admin: sign in, orders, mark sent, record refunds, monthly report + CSV | `admin/` |
| Manual deploy to Xneelo | `.github/workflows/deploy-xneelo.yml` |

The commission is sent to PayFast as a **fixed rand amount** per order (rate x clothing subtotal, delivery excluded), because PayFast percentages must be whole numbers.

On the GitHub preview there is no server, so the site keeps WhatsApp ordering. Nothing changes there.

## Steps

| # | Who | Step | Done when |
| --- | --- | --- | --- |
| 1 | Dion + Client | Sign the agreement (rate, R499/month, off-site orders, refunds) | Signed copy saved |
| 2 | Dion | Buy the domain and a Xneelo hosting package **with a MySQL database**. Create a test subdomain, e.g. `test.yourdomain.co.za` | Xneelo welcome email |
| 3 | Dion | In Xneelo: create the MySQL database and user; set the FTP password; note the FTP username (not the domain) | Details in the Xneelo control panel |
| 4 | Dion | GitHub repo > Settings > Secrets and variables > Actions: add `FTP_SERVER`, `FTP_USERNAME`, `FTP_PASSWORD` | 3 secrets listed |
| 5 | Claude | Run **Actions > Deploy to Xneelo** into the test subdomain's folder | Workflow green |
| 6 | Dion | On the server (Xneelo File Manager): copy `api/config.sample.php` to `api/config.local.php` and fill in: test site URL, database details, PayFast **sandbox** details, TLC's sandbox merchant ID as `split_merchant_id`, the agreed `commission_bp`, admin password hashes (`php tools/make-password-hash.php`) | Shop cart shows "Pay securely with PayFast" |
| 7 | Claude + Dion | Sandbox test on the test site with PayFast's test cards: pay, cancel, fail, refund. Confirm the split arrives and check `setup_in_signature` (flip it if PayFast shows a signature error). Answer the PayFast checklist in the spec | Test list in `docs/TESTING.md` all ticked |
| GATE 1 | Dion | Review the test site | Dion's OK |
| GATE 2 | Client | Joseph Mathenjwa approves the test site | Written approval |
| 8 | Client + Dion | Put **live** details into `config.local.php` on the live site: DotComSA's merchant ID, key and passphrase (typed in by the owner), TLC's live merchant ID, `sandbox => false`. Split Payments must be switched on in DotComSA's PayFast account | Settings saved |
| 9 | Claude + Dion | One small real payment end to end, then refund it | Money and split seen in both PayFast accounts |
| GATE 3 | Dion | Written "Go live" | Message from Dion |
| 10 | Dion | Switch off the GitHub Pages preview and make the repo **Private** | Repo shows Private |

## Rules

- Merchant keys, passphrases, database and admin passwords live **only** in `api/config.local.php` on the server. Never in GitHub, WhatsApp or email.
- `api/config.local.php` is never committed (`.gitignore`) and is blocked from the web (`api/.htaccess`).
- An order is paid **only** when PayFast's notice passes every check. The thank-you page is never proof of payment. DotComSA must not send an order until the admin area shows **Paid**.
- Do refunds in the PayFast dashboard first, then record them in the admin area so the reports stay correct.
- No go-live with any known high or critical security issue open.

## Still to confirm on the test site (cannot be tested without a real server)

- PayFast's signature rule for the split field (`setup_in_signature`).
- Whether PayFast takes its fee before or after the split.
- Apache rules in `.htaccess` (blocked folders, HTTPS redirect, security headers) on Xneelo.
- PayFast's notices reach `api/notify.php` (Xneelo firewall and HTTPS).
