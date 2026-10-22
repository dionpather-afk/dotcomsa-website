# Test record

## How to test

```
python3 -m http.server 8765 &
python3 tools/site_test.py http://localhost:8765
```

## Runs

| Date | Version | Result | Notes |
| --- | --- | --- | --- |
| 2026-10-22 | 0.1.0 | PASS, 0 problems | Desktop (1366px) and mobile (375px). 25 page loads per device incl. all 12 product pages; 30 unique links; full cart-to-WhatsApp order flow. Visual check of home, shop, product, cart and map on both sizes. |

## What 0.1.0 covered

- Every page: no JavaScript errors, no failed requests, all images load, no sideways scroll on mobile, header and footer present.
- Every internal link resolves; only external links are WhatsApp (`wa.me`).
- Product page: size/colour required before adding to cart.
- Cart: quantity, remove, free-delivery hint, delivery-details validation (name, cellphone, address, town, province, 4-digit postal code).
- WhatsApp order message contains order number, items, sizes, colours, totals, name, phone and full address.
- Cart clears after "I've sent it".

## Not yet tested (needs real devices or client content)

- Real WhatsApp send on Android and iPhone.
- Email order opening in a phone mail app.
- Real product photos (size and loading speed).
