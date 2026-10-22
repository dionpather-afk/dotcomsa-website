/*
 * DotComSA product catalogue.
 *
 * SAMPLE DATA ONLY: these items show how the store works.
 * Replace them with the client's real products, prices and photos before go-live.
 *
 * Each product:
 *   id        - unique, lowercase, no spaces (used in the web address)
 *   name      - product name shown to customers
 *   category  - "men", "women" or "kids"
 *   price     - selling price in Rand (number)
 *   wasPrice  - optional old price in Rand, shows a "Sale" tag
 *   sizes     - list of sizes the customer can choose
 *   colours   - list of colours the customer can choose
 *   image     - path to the product photo
 *   inStock   - true or false (false shows "Sold out" and blocks ordering)
 *   featured  - true shows it on the home page
 *   description - short text for the product page
 */
window.DOTCOMSA_SAMPLE_DATA = true;

window.DOTCOMSA_PRODUCTS = [
  {
    id: "mens-classic-tee",
    name: "Men's Classic Crew Tee",
    category: "men",
    price: 149,
    sizes: ["S", "M", "L", "XL", "XXL"],
    colours: ["Navy", "White", "Red"],
    image: "assets/img/products/mens-classic-tee.svg",
    inStock: true,
    featured: true,
    description: "Soft cotton crew-neck tee for everyday wear. Easy to match with jeans or shorts."
  },
  {
    id: "mens-pullover-hoodie",
    name: "Men's Pullover Hoodie",
    category: "men",
    price: 399,
    wasPrice: 459,
    sizes: ["S", "M", "L", "XL", "XXL"],
    colours: ["Navy", "Grey"],
    image: "assets/img/products/mens-pullover-hoodie.svg",
    inStock: true,
    featured: true,
    description: "Warm fleece-lined hoodie with a front pocket. Perfect for winter mornings."
  },
  {
    id: "mens-slim-jeans",
    name: "Men's Slim Fit Jeans",
    category: "men",
    price: 449,
    sizes: ["30", "32", "34", "36", "38"],
    colours: ["Dark Blue", "Black"],
    image: "assets/img/products/mens-slim-jeans.svg",
    inStock: true,
    featured: true,
    description: "Comfortable stretch denim in a modern slim fit."
  },
  {
    id: "mens-bomber-jacket",
    name: "Men's Bomber Jacket",
    category: "men",
    price: 599,
    sizes: ["M", "L", "XL"],
    colours: ["Black", "Navy"],
    image: "assets/img/products/mens-bomber-jacket.svg",
    inStock: false,
    featured: false,
    description: "Lightweight bomber jacket with ribbed cuffs and a zip front."
  },
  {
    id: "womens-wrap-dress",
    name: "Women's Wrap Midi Dress",
    category: "women",
    price: 499,
    wasPrice: 579,
    sizes: ["XS", "S", "M", "L", "XL"],
    colours: ["Red", "Navy"],
    image: "assets/img/products/womens-wrap-dress.svg",
    inStock: true,
    featured: true,
    description: "Flattering wrap dress in a flowing midi length. Dress it up or keep it casual."
  },
  {
    id: "womens-fitted-tee",
    name: "Women's Fitted Tee",
    category: "women",
    price: 139,
    sizes: ["XS", "S", "M", "L", "XL"],
    colours: ["White", "Pink", "Black"],
    image: "assets/img/products/womens-fitted-tee.svg",
    inStock: true,
    featured: false,
    description: "A wardrobe staple in soft stretch cotton."
  },
  {
    id: "womens-high-waist-jeans",
    name: "Women's High-Waist Jeans",
    category: "women",
    price: 479,
    sizes: ["28", "30", "32", "34", "36"],
    colours: ["Mid Blue", "Black"],
    image: "assets/img/products/womens-high-waist-jeans.svg",
    inStock: true,
    featured: true,
    description: "High-waist straight-leg jeans with a comfortable stretch."
  },
  {
    id: "womens-zip-hoodie",
    name: "Women's Zip-Up Hoodie",
    category: "women",
    price: 379,
    sizes: ["S", "M", "L", "XL"],
    colours: ["Grey", "Pink"],
    image: "assets/img/products/womens-zip-hoodie.svg",
    inStock: true,
    featured: true,
    description: "Cosy zip-up hoodie with a soft brushed inside."
  },
  {
    id: "kids-graphic-tee",
    name: "Kids' Graphic Tee",
    category: "kids",
    price: 99,
    sizes: ["2-3 yrs", "4-5 yrs", "6-7 yrs", "8-9 yrs", "10-11 yrs"],
    colours: ["Blue", "Red", "Yellow"],
    image: "assets/img/products/kids-graphic-tee.svg",
    inStock: true,
    featured: true,
    description: "Fun printed tee made for play. Easy-care cotton."
  },
  {
    id: "kids-jogger-set",
    name: "Kids' Hoodie & Jogger Set",
    category: "kids",
    price: 299,
    wasPrice: 349,
    sizes: ["2-3 yrs", "4-5 yrs", "6-7 yrs", "8-9 yrs"],
    colours: ["Navy", "Grey"],
    image: "assets/img/products/kids-jogger-set.svg",
    inStock: true,
    featured: true,
    description: "Matching hoodie and jogger set. Warm, comfy and school-run ready."
  },
  {
    id: "kids-denim-shorts",
    name: "Kids' Denim Shorts",
    category: "kids",
    price: 149,
    sizes: ["4-5 yrs", "6-7 yrs", "8-9 yrs", "10-11 yrs"],
    colours: ["Blue"],
    image: "assets/img/products/kids-denim-shorts.svg",
    inStock: true,
    featured: false,
    description: "Durable denim shorts with an adjustable waist."
  },
  {
    id: "kids-party-dress",
    name: "Girls' Party Dress",
    category: "kids",
    price: 279,
    sizes: ["2-3 yrs", "4-5 yrs", "6-7 yrs", "8-9 yrs"],
    colours: ["Red", "White"],
    image: "assets/img/products/kids-party-dress.svg",
    inStock: true,
    featured: false,
    description: "Pretty twirl dress for birthdays and special days."
  }
];
