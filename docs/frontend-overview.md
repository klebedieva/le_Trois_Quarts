# Frontend Overview

This note helps new contributors understand which JavaScript modules and stylesheets power each public-facing page. Global assets from `base.html.twig` (Bootstrap bundle, `static/js/main.js`, `static/js/cart-api.js`, `static/js/global.js`, `static/css/global.css`, and component CSS bundles) are always loaded, so only page-specific assets are listed below.

| Page / Route | Template | Page-Specific JS | Page-Specific CSS | Core Responsibilities |
| --- | --- | --- | --- | --- |
| Homepage `/` | `templates/home/homepage.html.twig` | `static/js/reviews.js` (loads and paginates testimonials) | `static/css/gallery.css` (homepage gallery tiles) | Hero carousel, featured reviews, gallery teaser, reservation CTA. |
| Menu `/menu` | `templates/pages/menu.html.twig` | `static/js/menu.js` (filtering, search, add-to-cart buttons) | `static/css/menu.css` | Menu filters, price search, dietary toggles, menu cards. |
| Gallery `/gallery` | `templates/pages/gallery.html.twig` | `static/js/gallery.js` (category filter, modal navigation) | `static/css/gallery.css` | Full gallery grid, category filters, gallery modal controls. |
| Reservation `/reservation` | `templates/pages/reservation.html.twig` | `static/js/reservation.js` (AJAX submission, validation, CSRF handling) | `static/css/contact.css` | Booking form, validation feedback, practical info blocks. |
| Reviews `/reviews` | `templates/pages/reviews.html.twig` | `static/js/reviews.js` (lazy loading, modal submission) | `static/css/reviews.css` | Reviews listing, pagination, review modal trigger. |
| Contact `/contact` | `templates/pages/contact.html.twig` | `static/js/contact.js` (client-side validation, AJAX submission) | `static/css/contact.css` | Contact cards, map embed, contact form with feedback. |
| Order `/order` | `templates/pages/order.html.twig` | `static/js/order/*.js` modules (`order-constants`, `order-utils`, `order-api`, `order-validation`, `order-steps`, `order-coupon`, `order-submission`, `order-cart`, `order-delivery`, `order-address`, `order-field-validation`, final `order.js`) | `static/css/order.css` | Multi-step checkout, delivery/address validation, coupon handling, cart summary. |
| Dish detail `/menu/{id}` | `templates/pages/dish_detail.html.twig` | `static/js/dish-detail.js`, `static/js/reviews.js` | `static/css/dish-detail.css` | Dish presentation, related dishes carousel, dish-specific reviews modal. |
| CGV `/cgv` | `templates/pages/cgv.html.twig` | — | `static/css/cgv.css` | Static legal content with scrolling sections. |
| 404 `/404` | `templates/bundles/TwigBundle/Exception/error404.html.twig` | — | `static/css/404.css` (loaded via template) | Custom not-found experience with navigation back to home. |

## Shared UI Pieces
- **Modals**: gallery modal (`templates/partials/gallery-modal.html.twig`), review modal (`templates/components/review_modal.html.twig`) run inside Bootstrap. Their triggers live on homepage, gallery, reviews, and dish detail pages.
- **Cart sidebar**: included on every page via `partials/cart-sidebar.html.twig`, driven by `cart-api.js` and specific page helpers (`order-cart.js`, etc.).
- **SEO / JSON-LD**: handled in `base.html.twig` with optional page overrides (e.g., reviews, dish detail).

Use this table as a quick reference when wiring new features: find the existing module, follow its pattern, and update the correct stylesheet bundle instead of creating duplicates.
