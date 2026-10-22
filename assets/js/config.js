/*
 * DotComSA site settings.
 * Change business details here ONLY. Every page reads from this file.
 *
 * TODO before go-live (see docs/OPEN-QUESTIONS.md):
 *  - Confirm which WhatsApp number orders must go to.
 *  - Confirm delivery fee and delivery times with the client.
 *  - Replace sample products in products.js with the client's real stock.
 */
window.DOTCOMSA_CONFIG = {
  businessName: "DotComSA",
  tagline: "Affordable fashion for men, women and kids, delivered anywhere in South Africa.",

  // Phone shown on the site (from the client's Website Brief).
  phoneDisplay: "082 049 3930",
  phoneLink: "+27820493930",

  // WhatsApp number in international format, digits only (27 = South Africa).
  whatsappNumber: "27820493930",

  email: "xolanimayor@gmail.com",

  // Delivery - PLACEHOLDER values until the client confirms.
  deliveryFee: 100,           // Rand, flat fee per order
  freeDeliveryFrom: 1000,     // Rand, set to 0 to switch off free delivery
  deliveryTime: "3 to 5 working days",

  currency: "R",
  yearsTrading: 5,

  // Social pages - leave blank until the client supplies them; blank links are hidden.
  social: {
    facebook: "",
    instagram: "",
    tiktok: ""
  }
};
