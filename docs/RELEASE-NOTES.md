# Release notes

## 0.3.0 (23 Oct 2026): Phase 2 card payments (built, not deployed)

- PayFast checkout: server works out prices, delivery and TLC's commission; customer pays on PayFast's page.
- Commission sent to PayFast Split Payments as a fixed amount per order (clothing subtotal x agreed rate, delivery excluded).
- Payment notice (ITN) handler: signature, PayFast source, merchant, amount and PayFast confirmation checked; repeat notices change nothing.
- Thank-you page confirms payment and empties the cart; cancel returns to the cart.
- Admin area: sign in, orders, mark sent, record refunds (with commission adjustment), monthly report and CSV.
- MySQL on Xneelo; tables created automatically.
- Manual "Deploy to Xneelo" workflow; .htaccess security rules and headers.
- On the GitHub preview nothing changes: WhatsApp ordering stays.
- Fixed: field error messages now clear when the customer corrects the field.

## 0.2.0 (23 Oct 2026): first real products

- Sample products replaced with 3 real products: Strap Platform Sneakers (R350, sizes 3-8, white/black), Women's Off-Shoulder Ribbed Top (R120, S-2XL, black/white/maroon/brown), Men's Striped Knit T-Shirt (R250, M-2XL, black-white/beige-black).
- Products can now be unisex (shown under Men and Women).
- Product photo changes when a colour is picked, and the cart shows the right colour photo.
- Preview banner now says delivery fees and policies are still being finalised.

## 0.1.0 (22 Oct 2026): Phase 1 preview build

Not live. Sample products only.

- Full store: home, shop with men/women/kids filters and sorting, product pages with sizes and colours, cart.
- Checkout sends the full order to DotComSA on WhatsApp, or by email.
- Click-to-call, WhatsApp and email on every page; floating WhatsApp button.
- Nationwide delivery map of South Africa on the home and delivery pages.
- About, Contact, Delivery and returns, draft Terms and Privacy (POPIA) pages.
- Red, blue and white branding; mobile-first layout.
- Automated test script (`tools/site_test.py`) and project docs.
