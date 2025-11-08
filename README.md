# Le Trois Quarts - Brasserie Website

A modern restaurant website built with Symfony 7 for Le Trois Quarts brasserie located in Marseille, France.

## 🍽️ About

Le Trois Quarts is a friendly brasserie located in the heart of the Camas district in Marseille, on Boulevard Chave. We offer a generous cuisine in a warm atmosphere with a sunny terrace.

## 🚀 Features

- **Homepage** with hero carousel, about section, customer reviews, and gallery
- **Contact Form** with validation and database storage
- **Customer Reviews** system with admin approval
- **Admin Panel** built with EasyAdmin for content management
- **Responsive Design** optimized for all devices
- **Modern UI** with Bootstrap 5 and custom styling

## 🛠️ Technology Stack

- **Backend:** Symfony 7
- **Database:** MySQL/PostgreSQL with Doctrine ORM
- **Frontend:** Twig templates, Bootstrap 5, Bootstrap Icons
- **Admin Panel:** EasyAdmin Bundle
- **Styling:** Custom CSS with responsive design
- **JavaScript:** Vanilla JS for interactive features

## 📁 Project Structure

```
le_trois_quarts/
├── src/
│   ├── Controller/          # Application controllers
│   ├── Entity/             # Doctrine entities
│   ├── Form/               # Symfony forms
│   ├── Repository/         # Data repositories
│   └── Security/           # Security configuration
├── templates/
│   ├── home/               # Homepage templates
│   ├── pages/              # Static pages
│   ├── partials/           # Reusable template parts
│   └── admin/              # Admin panel templates
├── public/
│   ├── assets/             # CSS, JS, and images
│   └── images/             # Static images
└── config/                 # Application configuration
```

## 🏗️ Installation

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd le_trois_quarts
   ```

2. **Install dependencies**
   ```bash
   composer install
   ```

3. **Configure environment**
   ```bash
   cp .env .env.local
   # Edit .env.local with your database credentials
   ```

4. **Setup database**
   ```bash
   php bin/console doctrine:database:create
   php bin/console doctrine:migrations:migrate
   ```

5. **Create admin user**
   ```bash
   php bin/console app:create-admin
   ```

6. **Start development server**
   ```bash
   symfony serve
   ```

## 📝 Usage

### Admin Panel
Access the admin panel at `/admin` to:
- Manage customer reviews
- View contact form submissions
- Configure site settings

### Contact Form
The contact form includes:
- Name and email validation
- Subject selection (reservation, order, private event, etc.)
- Message field with consent checkbox
- Automatic email notifications

### Customer Reviews
- Customers can submit reviews through the homepage
- Reviews require admin approval before display
- Star rating system (1-5 stars)

## 🎨 Customization

### Styling
- Main styles: `public/static/css/style.css`
- Contact page styles: `public/static/css/contact.css`
- Responsive design with Bootstrap 5

### Templates
- Base template: `templates/base.html.twig`
- Partials: `templates/partials/`
- Page templates: `templates/pages/`

## 📧 Contact Information

**Le Trois Quarts**
- Address: 139 Boulevard Chave, 13005 Marseille
- Phone: 04 91 92 96 16
- Email: letroisquarts@gmail.com
- Hours: Monday-Sunday, 8:00 AM - 11:00 PM

## 📄 License

This project is proprietary software for Le Trois Quarts restaurant.

## 🤝 Contributing

This is a private project. For any issues or suggestions, please contact the development team.

---

*Built with ❤️ for Le Trois Quarts*
