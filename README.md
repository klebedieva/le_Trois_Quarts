# Le Trois Quarts – Restaurant Platform

Modern Symfony application that powers the public website, online ordering flow and administration tools of the Le Trois Quarts brasserie in Marseille (FR-13).

---

## 🍽️ About the project

- **Public site**: storytelling homepage, menu presentation, gallery, customer testimonials and contact form.
- **Online ordering**: full cart + checkout experience (delivery / click & collect), coupon validation and secure order submission.
- **Reviews**: visitors can submit restaurant or dish reviews (stored pending moderation).
- **Administration**: EasyAdmin back-office for menu management, coupons, reservations, orders, gallery, reviews and settings.
- **API layer**: REST-ish endpoints consumed by the front (cart, order, reviews, gallery, address validation…).

---

## 🧰 Tech stack

| Layer        | Details                                                                 |
|--------------|-------------------------------------------------------------------------|
| Framework    | Symfony 7 (HTTP Kernel, Dependency Injection, Messenger optional)       |
| Database     | Doctrine ORM (MySQL/PostgreSQL compatible)                              |
| Templating   | Twig + Bootstrap 5 + Bootstrap Icons                                    |
| Admin        | EasyAdmin Bundle                                                        |
| Styles        | Custom CSS under `public/static/css`, Bootstrap utility classes        |
| JS           | Vanilla ES modules under `public/static/js` (cart, order flow, reviews) |
| Tooling      | PHPUnit 11, AssetMapper, Symfony CLI                                    |

---

## 📂 Repository structure (excerpt)

```
le_trois_quarts/
├── config/                  # Symfony & service configuration
├── docs/                    # Architecture notes + testing guides
├── public/
│   ├── assets/              # AssetMapper build output (ignored in git)
│   ├── static/              # Hand-crafted CSS/JS/images served directly
│   ├── uploads/             # Runtime media (menu pictures uploaded via admin)
│   └── index.php            # Front controller
├── src/
│   ├── Controller/          # MVC controllers (site, API, admin extensions)
│   ├── DTO/                 # Request/response DTO objects
│   ├── Entity/              # Doctrine entities
│   ├── Enum/, Service/, Repository/, Security/ …
├── templates/               # Twig templates (public + EasyAdmin overrides)
└── tests/
    ├── Unit/                # PHPUnit unit tests
    └── Integration/         # Kernel tests for services & API flows
```

---

## 🚀 Getting started

1. **Clone & install**
   ```bash
   git clone <repository-url>
   cd le_trois_quarts
   composer install
   ```

2. **Environment**
   ```bash
   cp .env .env.local
   # Adjust DATABASE_URL, MAILER_DSN, restaurant settings…
   ```

3. **Database & fixtures**
   ```bash
    php bin/console doctrine:database:create
    php bin/console doctrine:migrations:migrate
    php bin/console doctrine:fixtures:load   # optional sample data
   ```

4. **Admin accounts**
   ```bash
   php bin/console app:create-admin        # creates ROLE_ADMIN user interactively
   php bin/console app:create-moderator    # optional helper command
   ```

5. **Run the app**
   ```bash
   symfony serve -d           # or symfony serve to run in foreground
   npm run watch              # only if you add AssetMapper sources
   ```

6. **Tests**
   ```bash
   vendor/bin/phpunit --testsuite Unit
   vendor/bin/phpunit --testsuite Integration
   ```

---

## 🔐 Security notes

- Public API endpoints are limited to read-only / non-sensitive operations:
  - `/api/cart/*`, `/api/order` (POST), `/api/review(s)`, `/api/dishes/*/reviews`, `/api/gallery`, `/api/restaurant/settings`, `/api/coupon/validate`, `/api/validate-address|zip-code`.
- Everything else under `/api/**` requires `ROLE_ADMIN` or `ROLE_MODERATOR`. Critical actions (coupon apply/list, order retrieval) are double-guarded with `#[IsGranted('ROLE_ADMIN')]`.
- CSRF protection is enabled on mutating cart/order endpoints in production.
- Uploaded media is served from `public/uploads/`; keep this folder outside VCS and ensure filesystem permissions on the server.

---

## 🧑‍💻 Admin area

- Access: `/admin` (login form provides custom styling).
- Manage: menu items & categories, coupons, orders, reservations, reviews (moderation), gallery, restaurant settings.
- API keys / environment parameters set in `.env.local`:
  - `RESTAURANT_DELIVERY_FEE`, `RESTAURANT_DELIVERY_RADIUS`, `RESTAURANT_VAT_RATE`, etc.

---

## ✏️ Front-end resources

- Global styles: `public/static/css/style.css`
- Page components: `public/static/css/components/*`
- Login/admin overrides: `public/static/css/global.css`, `public/static/css/admin*.css`
- Main scripts: `public/static/js/main.js`, `public/static/js/order/*.js`, `public/static/js/reviews.js`

---

## 📞 Restaurant contact

**Le Trois Quarts**  
139 Boulevard Chave, 13005 Marseille  
☎️ 04 91 92 96 16  
✉️ letroisquarts@gmail.com  
🕗 08:00 – 23:00 (daily)

---

## 📄 License & contributions

This codebase is proprietary and maintained for Le Trois Quarts restaurant.  
External pull requests are not accepted; for questions contact the internal development team.

---

_Built with ❤️ for Le Trois Quarts_
