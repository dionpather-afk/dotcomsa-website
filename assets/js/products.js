/*
 * DotComSA product catalogue (real products from the client, 23 Oct 2026).
 *
 * Each product:
 *   id           - unique, lowercase, no spaces (used in the web address)
 *   name         - product name shown to customers
 *   category     - "men", "women" or "kids", or a list e.g. ["men", "women"] for unisex
 *   price        - selling price in Rand (number)
 *   wasPrice     - optional old price in Rand, shows a "Sale" tag
 *   sizes        - sizes the customer can choose
 *   colours      - colours the customer can choose
 *   image        - main product photo
 *   colourImages - optional: photo to show for a specific colour
 *   inStock      - true or false (false shows "Sold out" and blocks ordering)
 *   featured     - true shows it on the home page
 *   description  - short text for the product page
 *
 * IMPORTANT: keep the list below in strict JSON format (double quotes on every
 * name, no comments inside the list, no trailing commas). The payment server
 * reads this same list to check prices, so customers cannot change them.
 */
window.DOTCOMSA_SAMPLE_DATA = false;

window.DOTCOMSA_PRODUCTS = [
  {
    "id": "strap-platform-sneakers",
    "name": "Strap Platform Sneakers",
    "category": [
      "men",
      "women"
    ],
    "price": 350,
    "sizes": [
      "3",
      "4",
      "5",
      "6",
      "7",
      "8"
    ],
    "colours": [
      "White",
      "Black"
    ],
    "image": "assets/img/products/strap-sneakers-white.jpg",
    "colourImages": {
      "White": "assets/img/products/strap-sneakers-white.jpg",
      "Black": "assets/img/products/strap-sneakers-black.jpg"
    },
    "inStock": true,
    "featured": true,
    "description": "Chunky platform sneakers with easy strap fastening. A clean everyday look that goes with jeans, dresses or tracksuits."
  },
  {
    "id": "womens-off-shoulder-top",
    "name": "Women's Off-Shoulder Ribbed Top",
    "category": "women",
    "price": 120,
    "sizes": [
      "S",
      "M",
      "L",
      "XL",
      "2XL"
    ],
    "colours": [
      "Black",
      "White",
      "Maroon",
      "Brown"
    ],
    "image": "assets/img/products/womens-offshoulder-top-black.jpg",
    "colourImages": {
      "Black": "assets/img/products/womens-offshoulder-top-black.jpg",
      "White": "assets/img/products/womens-offshoulder-top-white.jpg"
    },
    "inStock": true,
    "featured": true,
    "description": "Off-shoulder long-sleeve top with textured diagonal ribbing, flared sleeves and a ring detail at the waist. Dress it up or keep it casual."
  },
  {
    "id": "mens-striped-knit-tee",
    "name": "Men's Striped Knit T-Shirt",
    "category": "men",
    "price": 250,
    "sizes": [
      "M",
      "L",
      "XL",
      "2XL"
    ],
    "colours": [
      "Black/White",
      "Beige/Black"
    ],
    "image": "assets/img/products/mens-knit-tee-black-white.jpg",
    "colourImages": {
      "Black/White": "assets/img/products/mens-knit-tee-black-white.jpg",
      "Beige/Black": "assets/img/products/mens-knit-tee-beige-black.jpg"
    },
    "inStock": true,
    "featured": true,
    "description": "Short-sleeve knit T-shirt with a bold vertical stripe pattern. Smart enough for a night out, comfy enough for every day."
  }
];
