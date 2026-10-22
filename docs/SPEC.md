# DotComSA Website Spec

Source of truth: the Website Brief Xolani submitted on the TLC website (22 Oct 2026), plus answers confirmed with Dion since.

## Client brief

| Item | Detail |
| --- | --- |
| Contact | Xolani, 082 049 3930, xolanimayor@gmail.com |
| Business | DotComSA, selling clothes; 5 years' experience with regular customers |
| Trading model | Online only, no physical shop |
| Customers | Men, women and kids |
| Delivery area | Nationwide, all of South Africa |
| Website purpose | Sell online |
| Main visitor actions | Call, WhatsApp, email, buy |
| Special features | Online store; map (agreed: nationwide delivery map) |
| Colours | Red, blue and white; friendly look |
| Text and images | Client says ready; not yet received |
| Logo | To follow |
| Domain | Client needs one (dotcomsa.co.za is taken by another business) |
| Hosting | TLC to provide, on Xneelo |
| Final approver | Joseph Mathenjwa, 073 410 2006 |

## Decisions

| Decision | Choice | Why |
| --- | --- | --- |
| Build approach | Code in GitHub, no Wix mock-up; GitHub preview link acts as the mock-up | Same pattern as M.A.K Ameen; faster, GitHub stays the source of truth |
| Phase 1 checkout | Order sent to the business on WhatsApp or email | Works on free static hosting; no card data handled; quick to launch |
| Phase 2 checkout | Card payments via PayFast or Yoco on Xneelo | Needs the client's payment account and a server to verify payments safely |
| Map | Picture of South Africa, not a live Google Map | Business is online-only; no API keys, nothing to break |

## Colours

| Use | Colour | Code |
| --- | --- | --- |
| Header, footer, menus | Deep blue | `#1E3A8A` |
| Buy, WhatsApp and Call buttons, sale tags | Bright red | `#DC2626` |
| Page background | White | `#FFFFFF` |
| Section background | Light blue-grey | `#F1F5F9` |
| Text | Near-black | `#111827` |

## Pages

| Page | File |
| --- | --- |
| Home | `index.html` |
| Shop: all, men, women, kids | `shop.html`, `shop.html?cat=men` etc. |
| Product | `product.html?id=...` |
| Cart and checkout | `cart.html` |
| About | `about.html` |
| Contact | `contact.html` |
| Delivery and returns | `delivery.html` |
| Terms and Privacy | `terms.html`, `privacy.html` (drafts for client review) |
| Not found | `404.html` |
