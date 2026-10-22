/*
 * DotComSA - shared site script.
 * Builds the header and footer, runs the cart, and renders each page.
 * Each HTML page sets <body data-page="..."> to say which page it is.
 *
 * Phase 1: orders are sent to the business on WhatsApp (or email).
 * No customer data is stored on any server. The cart lives in the
 * visitor's own browser only.
 */
(function () {
  "use strict";

  var CFG = window.DOTCOMSA_CONFIG;
  var PRODUCTS = window.DOTCOMSA_PRODUCTS || [];
  var CART_KEY = "dotcomsa_cart_v1";
  var CATEGORIES = {
    men: { label: "Men", blurb: "Tees, hoodies, jeans and jackets" },
    women: { label: "Women", blurb: "Dresses, tops, jeans and more" },
    kids: { label: "Kids", blurb: "Comfy clothes for play and school" }
  };

  /* ---------- Small helpers ---------- */

  function esc(value) {
    return String(value == null ? "" : value)
      .replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;").replace(/'/g, "&#39;");
  }

  function money(n) {
    // Fixed format on every device: R1,299.00
    var parts = (Math.round(Number(n) * 100) / 100).toFixed(2).split(".");
    return CFG.currency + parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ",") + "." + parts[1];
  }

  function $(sel, root) { return (root || document).querySelector(sel); }

  function param(name) {
    return new URLSearchParams(window.location.search).get(name);
  }

  function findProduct(id) {
    for (var i = 0; i < PRODUCTS.length; i++) if (PRODUCTS[i].id === id) return PRODUCTS[i];
    return null;
  }

  function waLink(text) {
    return "https://wa.me/" + CFG.whatsappNumber + (text ? "?text=" + encodeURIComponent(text) : "");
  }

  var toastTimer;
  function toast(html) {
    var el = $("#toast");
    if (!el) return;
    el.innerHTML = html;
    el.classList.add("is-visible");
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { el.classList.remove("is-visible"); }, 3500);
  }

  /* ---------- Cart (stored in this browser only) ---------- */

  var memoryCart = [];

  function readCart() {
    try {
      var raw = window.localStorage.getItem(CART_KEY);
      var items = raw ? JSON.parse(raw) : [];
      // Drop anything that no longer matches a product.
      return Array.isArray(items) ? items.filter(function (it) { return findProduct(it.id); }) : [];
    } catch (e) {
      return memoryCart.slice();
    }
  }

  function writeCart(items) {
    memoryCart = items.slice();
    try { window.localStorage.setItem(CART_KEY, JSON.stringify(items)); } catch (e) { /* storage blocked: keep in memory */ }
    updateCartCount();
  }

  function addToCart(id, size, colour, qty) {
    var items = readCart();
    var existing = items.filter(function (it) { return it.id === id && it.size === size && it.colour === colour; })[0];
    if (existing) existing.qty = Math.min(99, existing.qty + qty);
    else items.push({ id: id, size: size, colour: colour, qty: qty });
    writeCart(items);
  }

  function cartTotals(items) {
    var subtotal = items.reduce(function (sum, it) { return sum + findProduct(it.id).price * it.qty; }, 0);
    var free = CFG.freeDeliveryFrom > 0 && subtotal >= CFG.freeDeliveryFrom;
    var delivery = items.length === 0 || free ? 0 : CFG.deliveryFee;
    return { subtotal: subtotal, delivery: delivery, total: subtotal + delivery, free: free };
  }

  function cartCount() {
    return readCart().reduce(function (n, it) { return n + it.qty; }, 0);
  }

  function updateCartCount() {
    var el = $("#cart-count");
    if (el) el.textContent = cartCount();
  }

  /* ---------- Shared header and footer ---------- */

  var chatIcon = '<svg viewBox="0 0 24 24" aria-hidden="true" fill="#fff"><path d="M12 2C6.5 2 2 6.1 2 11.2c0 2.6 1.2 5 3.2 6.6L4.4 22l4.4-2.2c1 .3 2.1.5 3.2.5 5.5 0 10-4.1 10-9.1S17.5 2 12 2zm-4 10.4a1.3 1.3 0 110-2.6 1.3 1.3 0 010 2.6zm4 0a1.3 1.3 0 110-2.6 1.3 1.3 0 010 2.6zm4 0a1.3 1.3 0 110-2.6 1.3 1.3 0 010 2.6z"/></svg>';

  function renderLayout(page) {
    var nav = [
      ["index.html", "Home", "home"],
      ["shop.html", "Shop all", "shop"],
      ["shop.html?cat=men", "Men", "men"],
      ["shop.html?cat=women", "Women", "women"],
      ["shop.html?cat=kids", "Kids", "kids"],
      ["about.html", "About", "about"],
      ["contact.html", "Contact", "contact"]
    ];
    var activeKey = page === "shop" ? (param("cat") || "shop") : page;

    var freeText = CFG.freeDeliveryFrom > 0
      ? "Free delivery on orders over " + money(CFG.freeDeliveryFrom) + " • We deliver anywhere in South Africa"
      : "We deliver anywhere in South Africa";

    var header =
      (window.DOTCOMSA_SAMPLE_DATA ? '<div class="preview-bar">Preview site: products and prices shown are samples only.</div>' : "") +
      '<div class="topbar">' + esc(freeText) + '</div>' +
      '<header class="header"><div class="container header__inner">' +
        '<a class="logo" href="index.html" aria-label="' + esc(CFG.businessName) + ' home">DotCom<span>SA</span></a>' +
        '<nav class="nav" id="site-nav" aria-label="Main">' +
          nav.map(function (n) {
            return '<a href="' + n[0] + '"' + (n[2] === activeKey ? ' aria-current="page"' : "") + ">" + n[1] + "</a>";
          }).join("") +
        "</nav>" +
        '<div class="header__actions">' +
          '<a class="cart-link" href="cart.html" aria-label="Cart">Cart <span class="cart-count" id="cart-count">0</span></a>' +
          '<button class="menu-toggle" type="button" aria-controls="site-nav" aria-expanded="false">Menu</button>' +
        "</div>" +
      "</div></header>";

    var social = ["facebook", "instagram", "tiktok"].filter(function (k) { return CFG.social[k]; })
      .map(function (k) { return '<li><a href="' + esc(CFG.social[k]) + '" target="_blank" rel="noopener">' + k.charAt(0).toUpperCase() + k.slice(1) + "</a></li>"; }).join("");

    var footer =
      '<footer class="footer"><div class="container">' +
        '<div class="footer__grid">' +
          "<div><a class=\"logo\" href=\"index.html\">DotCom<span>SA</span></a><p style=\"margin-top:10px\">" + esc(CFG.tagline) + "</p></div>" +
          '<div><h3>Shop</h3><ul><li><a href="shop.html?cat=men">Men</a></li><li><a href="shop.html?cat=women">Women</a></li><li><a href="shop.html?cat=kids">Kids</a></li><li><a href="shop.html">Shop all</a></li></ul></div>' +
          '<div><h3>Help</h3><ul><li><a href="delivery.html">Delivery &amp; returns</a></li><li><a href="contact.html">Contact us</a></li><li><a href="terms.html">Terms &amp; conditions</a></li><li><a href="privacy.html">Privacy policy</a></li></ul></div>' +
          '<div><h3>Talk to us</h3><ul>' +
            '<li><a href="tel:' + esc(CFG.phoneLink) + '">' + esc(CFG.phoneDisplay) + "</a></li>" +
            '<li><a href="' + waLink("Hi DotComSA") + '" target="_blank" rel="noopener">WhatsApp us</a></li>' +
            '<li><a href="mailto:' + esc(CFG.email) + '">' + esc(CFG.email) + "</a></li>" +
            social +
          "</ul></div>" +
        "</div>" +
        '<div class="footer__bottom"><span>&copy; ' + new Date().getFullYear() + " " + esc(CFG.businessName) + ". All rights reserved.</span><span>Website by TLC</span></div>" +
      "</div></footer>" +
      '<a class="wa-float" href="' + waLink("Hi DotComSA, I have a question") + '" target="_blank" rel="noopener" aria-label="Chat to us on WhatsApp">' + chatIcon + "</a>" +
      '<div class="toast" id="toast" role="status" aria-live="polite"></div>';

    var top = $("#site-header");
    var bottom = $("#site-footer");
    if (top) top.outerHTML = header;
    if (bottom) bottom.outerHTML = footer;

    var toggle = $(".menu-toggle");
    var navEl = $("#site-nav");
    if (toggle && navEl) {
      toggle.addEventListener("click", function () {
        var open = navEl.classList.toggle("is-open");
        toggle.setAttribute("aria-expanded", open ? "true" : "false");
      });
    }
    updateCartCount();
  }

  /* ---------- Product card ---------- */

  function productCard(p) {
    var tag = !p.inStock ? '<span class="tag tag--soldout">Sold out</span>' : (p.wasPrice ? '<span class="tag">Sale</span>' : "");
    var url = "product.html?id=" + encodeURIComponent(p.id);
    return '<article class="card">' +
      '<a class="card__img" href="' + url + '">' + tag + '<img src="' + esc(p.image) + '" alt="' + esc(p.name) + '" loading="lazy" width="400" height="480"></a>' +
      '<div class="card__body">' +
        '<span class="card__cat">' + esc(CATEGORIES[p.category].label) + "</span>" +
        '<a class="card__name" href="' + url + '">' + esc(p.name) + "</a>" +
        '<span class="price">' + money(p.price) + (p.wasPrice ? "<del>" + money(p.wasPrice) + "</del>" : "") + "</span>" +
        '<a class="btn ' + (p.inStock ? "btn--red" : "btn--outline") + '" href="' + url + '">' + (p.inStock ? "Choose size" : "View item") + "</a>" +
      "</div></article>";
  }

  /* ---------- Pages ---------- */

  function pageHome() {
    var grid = $("#featured-grid");
    if (grid) grid.innerHTML = PRODUCTS.filter(function (p) { return p.featured; }).slice(0, 8).map(productCard).join("");
    fillDeliveryFacts();
  }

  function fillDeliveryFacts() {
    var el;
    if ((el = $("#delivery-fee"))) el.textContent = money(CFG.deliveryFee);
    if ((el = $("#delivery-time"))) el.textContent = CFG.deliveryTime;
    if ((el = $("#delivery-free"))) {
      el.textContent = CFG.freeDeliveryFrom > 0 ? "Free on orders over " + money(CFG.freeDeliveryFrom) + "." : "";
    }
  }

  function pageShop() {
    var cat = param("cat");
    if (cat && !CATEGORIES[cat]) cat = null;
    var title = $("#shop-title");
    var intro = $("#shop-intro");
    if (cat) {
      title.textContent = CATEGORIES[cat].label;
      intro.textContent = CATEGORIES[cat].blurb + ", delivered anywhere in South Africa.";
      document.title = CATEGORIES[cat].label + " | " + CFG.businessName;
    }

    var chips = $("#cat-chips");
    var opts = [["", "All"], ["men", "Men"], ["women", "Women"], ["kids", "Kids"]];
    chips.innerHTML = opts.map(function (o) {
      var href = o[0] ? "shop.html?cat=" + o[0] : "shop.html";
      return '<a class="chip" href="' + href + '" aria-pressed="' + ((cat || "") === o[0] ? "true" : "false") + '">' + o[1] + "</a>";
    }).join("");

    var sortSel = $("#sort");
    function draw() {
      var list = PRODUCTS.filter(function (p) { return !cat || p.category === cat; }).slice();
      var s = sortSel.value;
      if (s === "low") list.sort(function (a, b) { return a.price - b.price; });
      else if (s === "high") list.sort(function (a, b) { return b.price - a.price; });
      else if (s === "sale") list.sort(function (a, b) { return (b.wasPrice ? 1 : 0) - (a.wasPrice ? 1 : 0); });
      // Sold-out items always go last.
      list.sort(function (a, b) { return (b.inStock ? 1 : 0) - (a.inStock ? 1 : 0); });
      $("#shop-count").textContent = list.length + (list.length === 1 ? " item" : " items");
      $("#shop-grid").innerHTML = list.length ? list.map(productCard).join("") : '<p class="empty">No products here yet. Check back soon.</p>';
    }
    sortSel.addEventListener("change", draw);
    draw();
  }

  function pageProduct() {
    var p = findProduct(param("id"));
    var root = $("#product-root");
    if (!p) {
      root.innerHTML = '<div class="empty"><h1>Product not found</h1><p>This item may have sold out or moved.</p><a class="btn btn--red" href="shop.html">Back to the shop</a></div>';
      document.title = "Product not found | " + CFG.businessName;
      return;
    }
    document.title = p.name + " | " + CFG.businessName;
    var meta = document.querySelector('meta[name="description"]');
    if (meta) meta.setAttribute("content", p.description);

    function optionList(name, values) {
      return '<fieldset class="option-group"><legend>' + (name === "size" ? "Size" : "Colour") + '</legend><div class="options">' +
        values.map(function (v, i) {
          var id = name + "-" + i;
          return '<input type="radio" name="' + name + '" id="' + id + '" value="' + esc(v) + '"' + (values.length === 1 ? " checked" : "") + '><label for="' + id + '">' + esc(v) + "</label>";
        }).join("") + "</div></fieldset>";
    }

    var tag = !p.inStock ? '<span class="tag tag--soldout">Sold out</span>' : (p.wasPrice ? '<span class="tag">Sale</span>' : "");
    root.innerHTML =
      '<nav class="breadcrumb" aria-label="Breadcrumb"><a href="index.html">Home</a> / <a href="shop.html?cat=' + p.category + '">' + CATEGORIES[p.category].label + "</a> / " + esc(p.name) + "</nav>" +
      '<div class="product">' +
        '<div class="product__img">' + tag + '<img src="' + esc(p.image) + '" alt="' + esc(p.name) + '" width="400" height="480"></div>' +
        '<div class="product__info">' +
          "<h1>" + esc(p.name) + "</h1>" +
          '<span class="price">' + money(p.price) + (p.wasPrice ? "<del>" + money(p.wasPrice) + "</del>" : "") + "</span>" +
          "<p>" + esc(p.description) + "</p>" +
          (p.inStock
            ? '<form id="add-form" novalidate>' +
                optionList("size", p.sizes) +
                optionList("colour", p.colours) +
                '<fieldset class="option-group"><legend>Quantity</legend><div class="qty"><button type="button" data-q="-1" aria-label="Less">&minus;</button><input id="qty" type="number" min="1" max="99" value="1" aria-label="Quantity"><button type="button" data-q="1" aria-label="More">+</button></div></fieldset>' +
                '<div id="add-msg" role="alert"></div>' +
                '<div class="product__actions">' +
                  '<button class="btn btn--red btn--block" type="submit">Add to cart</button>' +
                  '<button class="btn btn--wa btn--block" type="button" id="wa-buy">Order this on WhatsApp</button>' +
                "</div>" +
              "</form>"
            : '<p class="notice">This item is sold out. WhatsApp us and we’ll let you know when it’s back.</p>' +
              '<a class="btn btn--wa btn--block" target="_blank" rel="noopener" href="' + waLink("Hi DotComSA, please let me know when " + p.name + " is back in stock.") + '">Ask on WhatsApp</a>') +
          '<ul class="perks"><li>Delivered anywhere in South Africa</li><li>Delivery in ' + esc(CFG.deliveryTime) + '</li><li>Questions? Call <a href="tel:' + esc(CFG.phoneLink) + '">' + esc(CFG.phoneDisplay) + "</a></li></ul>" +
        "</div>" +
      "</div>";

    if (!p.inStock) return;

    var form = $("#add-form");
    var qty = $("#qty");
    form.addEventListener("click", function (e) {
      var b = e.target.closest("[data-q]");
      if (!b) return;
      var v = (parseInt(qty.value, 10) || 1) + parseInt(b.getAttribute("data-q"), 10);
      qty.value = Math.max(1, Math.min(99, v));
    });

    function choices() {
      var size = form.querySelector('input[name="size"]:checked');
      var colour = form.querySelector('input[name="colour"]:checked');
      var q = Math.max(1, Math.min(99, parseInt(qty.value, 10) || 1));
      qty.value = q;
      var msg = $("#add-msg");
      if (!size || !colour) {
        msg.innerHTML = '<p class="notice notice--error">Please choose a ' + (!size ? "size" : "colour") + " first.</p>";
        return null;
      }
      msg.innerHTML = "";
      return { size: size.value, colour: colour.value, qty: q };
    }

    form.addEventListener("submit", function (e) {
      e.preventDefault();
      var c = choices();
      if (!c) return;
      addToCart(p.id, c.size, c.colour, c.qty);
      toast("Added to your cart <a href=\"cart.html\">View cart</a>");
    });

    $("#wa-buy").addEventListener("click", function () {
      var c = choices();
      if (!c) return;
      var text = "Hi DotComSA, I'd like to order:\n" + c.qty + " x " + p.name + " (Size: " + c.size + ", Colour: " + c.colour + ") - " + money(p.price * c.qty) + "\n\nPlease confirm availability and delivery.";
      window.open(waLink(text), "_blank", "noopener");
    });
  }

  function pageCart() {
    var list = $("#cart-items");
    var summary = $("#cart-summary");
    var form = $("#order-form");
    var done = $("#order-done");

    function draw() {
      var items = readCart();
      var t = cartTotals(items);
      if (!items.length) {
        list.innerHTML = '<div class="empty"><p>Your cart is empty.</p><a class="btn btn--red" href="shop.html">Start shopping</a></div>';
        summary.hidden = true;
        return;
      }
      summary.hidden = false;
      list.innerHTML = items.map(function (it, i) {
        var p = findProduct(it.id);
        return '<div class="cart-item">' +
          '<img src="' + esc(p.image) + '" alt="" width="90" height="108">' +
          "<div><a class=\"card__name\" href=\"product.html?id=" + encodeURIComponent(p.id) + "\">" + esc(p.name) + "</a>" +
            '<div class="cart-item__meta">Size: ' + esc(it.size) + " • Colour: " + esc(it.colour) + "</div>" +
            '<div class="qty" style="margin-top:8px"><button type="button" data-i="' + i + '" data-q="-1" aria-label="Less">&minus;</button><input type="number" value="' + it.qty + '" readonly aria-label="Quantity"><button type="button" data-i="' + i + '" data-q="1" aria-label="More">+</button></div>' +
            ' <button class="cart-item__remove" type="button" data-remove="' + i + '">Remove</button></div>' +
          '<div class="price">' + money(p.price * it.qty) + "</div>" +
        "</div>";
      }).join("");
      $("#sum-subtotal").textContent = money(t.subtotal);
      $("#sum-delivery").textContent = t.delivery === 0 ? "Free" : money(t.delivery);
      $("#sum-total").textContent = money(t.total);
      var hint = $("#free-hint");
      if (CFG.freeDeliveryFrom > 0 && !t.free) hint.textContent = "Spend " + money(CFG.freeDeliveryFrom - t.subtotal) + " more for free delivery.";
      else hint.textContent = t.free ? "You qualify for free delivery." : "";
    }

    list.addEventListener("click", function (e) {
      var items = readCart();
      var rm = e.target.closest("[data-remove]");
      var q = e.target.closest("[data-q]");
      if (rm) items.splice(parseInt(rm.getAttribute("data-remove"), 10), 1);
      else if (q) {
        var i = parseInt(q.getAttribute("data-i"), 10);
        items[i].qty = Math.max(1, Math.min(99, items[i].qty + parseInt(q.getAttribute("data-q"), 10)));
      } else return;
      writeCart(items);
      draw();
    });

    function orderRef() {
      var d = new Date();
      var pad = function (n) { return (n < 10 ? "0" : "") + n; };
      return "DCS-" + String(d.getFullYear()).slice(2) + pad(d.getMonth() + 1) + pad(d.getDate()) + "-" + Math.floor(1000 + Math.random() * 9000);
    }

    function validate() {
      var ok = true;
      form.querySelectorAll("[required]").forEach(function (input) {
        var field = input.closest(".field");
        var err = field.querySelector(".field__error");
        var bad = !input.value.trim();
        if (!bad && input.name === "phone") bad = !/^[0-9+ ()-]{9,15}$/.test(input.value.trim());
        if (!bad && input.name === "postal") bad = !/^[0-9]{4}$/.test(input.value.trim());
        field.classList.toggle("field--error", bad);
        if (err) err.textContent = bad ? input.getAttribute("data-error") : "";
        if (bad && ok) { input.focus(); ok = false; }
      });
      return ok;
    }

    function orderText() {
      var items = readCart();
      var t = cartTotals(items);
      var f = new FormData(form);
      var lines = ["New order " + orderRef(), ""];
      items.forEach(function (it) {
        var p = findProduct(it.id);
        lines.push(it.qty + " x " + p.name + " (Size: " + it.size + ", Colour: " + it.colour + ") - " + money(p.price * it.qty));
      });
      lines.push("", "Subtotal: " + money(t.subtotal), "Delivery: " + (t.delivery === 0 ? "Free" : money(t.delivery)), "Total: " + money(t.total), "");
      lines.push("Name: " + f.get("name"), "Phone: " + f.get("phone"));
      lines.push("Delivery address: " + f.get("address") + ", " + f.get("city") + ", " + f.get("province") + ", " + f.get("postal"));
      if (String(f.get("notes") || "").trim()) lines.push("Notes: " + f.get("notes"));
      lines.push("", "Please confirm my order and send payment details.");
      return lines.join("\n");
    }

    function sent() {
      form.hidden = true;
      done.hidden = false;
    }

    $("#send-wa").addEventListener("click", function () {
      if (!readCart().length || !validate()) return;
      window.open(waLink(orderText()), "_blank", "noopener");
      sent();
    });
    $("#send-email").addEventListener("click", function () {
      if (!readCart().length || !validate()) return;
      window.location.href = "mailto:" + CFG.email + "?subject=" + encodeURIComponent("New DotComSA order") + "&body=" + encodeURIComponent(orderText());
      sent();
    });
    $("#clear-cart").addEventListener("click", function () {
      writeCart([]);
      form.reset();
      form.hidden = false;
      done.hidden = true;
      draw();
    });
    $("#back-to-form").addEventListener("click", function () {
      form.hidden = false;
      done.hidden = true;
    });

    draw();
  }

  function pageContact() {
    var el;
    if ((el = $("#c-phone"))) { el.href = "tel:" + CFG.phoneLink; el.textContent = CFG.phoneDisplay; }
    if ((el = $("#c-wa"))) el.href = waLink("Hi DotComSA");
    if ((el = $("#c-email"))) { el.href = "mailto:" + CFG.email; el.textContent = CFG.email; }
  }

  /* ---------- Start ---------- */

  document.addEventListener("DOMContentLoaded", function () {
    var page = document.body.getAttribute("data-page") || "";
    renderLayout(page);
    document.querySelectorAll("[data-wa]").forEach(function (a) { a.href = waLink(a.getAttribute("data-wa")); });
    document.querySelectorAll("[data-tel]").forEach(function (a) { a.href = "tel:" + CFG.phoneLink; });
    document.querySelectorAll("[data-mail]").forEach(function (a) { a.href = "mailto:" + CFG.email; });
    document.querySelectorAll("[data-year]").forEach(function (el) { el.textContent = CFG.yearsTrading; });
    fillDeliveryFacts();
    if (page === "home") pageHome();
    if (page === "shop") pageShop();
    if (page === "product") pageProduct();
    if (page === "cart") pageCart();
    if (page === "contact") pageContact();
  });
})();
