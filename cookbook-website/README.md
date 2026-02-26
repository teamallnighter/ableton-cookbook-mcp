# 🎵 Ableton Cookbook

A community-driven platform for sharing and discovering Ableton Live racks, techniques, and creative resources.
[![Deploy to Production](https://github.com/teamallnighter/ableton-cookbook/actions/workflows/deploy.yml/badge.svg)](https://github.com/teamallnighter/ableton-cookbook/actions/workflows/deploy.yml)
![Laravel](https://img.shields.io/badge/Laravel-12.x-FF2D20?style=flat&logo=laravel)
![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?style=flat&logo=php)
![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-38B2AC?style=flat&logo=tailwind-css)
![Livewire](https://img.shields.io/badge/Livewire-3.x-4E56A6?style=flat&logo=livewire)
![Swagger](https://img.shields.io/badge/OpenAPI-3.0-6BA539?style=flat&logo=swagger)
![Postman](https://img.shields.io/badge/Postman-Collection-FF6C37?style=flat&logo=postman)

## 🌟 Features

### 🎛️ Rack Management
- **Upload & Share**: Share your custom Ableton Live racks with the community
- **Smart Analysis**: Automatic rack analysis including device detection and Ableton version compatibility
- **Specialized Drum Analysis**: Advanced drum rack analyzer with MIDI pad mapping and performance insights
- **Interactive Visualization**: 4x4 drum pad grid with device categorization and complexity analysis
- **Categories & Tags**: Organize racks by genre, style, and device types
- **Rating System**: Community-driven rating and review system
- **Favorites**: Save and organize your favorite racks

### 👥 User System
- **Enhanced Registration**: Username-based accounts with email verification
- **User Profiles**: Customizable profiles with social media links and bio
- **Follow System**: Follow other users and get notified of new uploads
- **Activity Feed**: Stay updated with community activity

### 📝 Blog System
- **Content Management**: Full-featured blog system with rich text editing
- **WYSIWYG Editor**: TinyMCE integration with drag & drop image uploads
- **Category Management**: Organize posts with color-coded categories
- **SEO Optimized**: SEO-friendly URLs, meta tags, and structured data
- **Homepage Integration**: Recent blog posts featured on the main page
- **Admin Interface**: Complete admin panel for content management

### 🚀 API Documentation
- **Comprehensive REST API**: Full API for all platform features including specialized drum rack endpoints
- **Interactive Documentation**: Swagger UI available at `/api/docs` and modern docs at `/docs/api`
- **Drum Rack API**: Dedicated endpoints for drum rack analysis, validation, and batch processing
- **Postman Collection**: Ready-to-use Postman collections and environments
- **Authentication Support**: Both session and token-based authentication with Laravel Sanctum
- **Developer Resources**: Complete API testing suite and examples

### 📧 Email System
- **Professional Email Templates**: Custom-branded verification and notification emails
- **GDPR Compliant**: Explicit email consent with granular preferences
- **Email Authentication**: Full SPF/DKIM/DMARC configuration for optimal deliverability
- **Notification System**: Configurable email notifications for various events

### 🔍 Discovery Features
- **Advanced Search**: Filter by device, genre, Ableton version, drum type, and more
- **Intelligent Routing**: Automatic detection between drum and general racks for optimized display
- **Browse by Category**: Organized browsing experience with drum-specific categorization
- **Trending Racks**: Discover popular and recently uploaded content
- **Performance Analysis**: Filter by complexity scores and optimization recommendations
- **Recommendations**: Personalized rack suggestions based on usage patterns

### 🛡️ Security & Performance
- **Two-Factor Authentication**: Optional 2FA for enhanced account security
- **Role-Based Permissions**: Admin and user role management
- **Performance Optimized**: Cached queries and optimized database indexes
- **SEO Optimized**: Full SEO implementation with structured data

## 🚀 Quick Start

### Prerequisites
- PHP 8.2+
- Composer
- Node.js 20+ & NPM
- MySQL 8.0+ (for JSON column support)
- Redis (recommended for caching and queues)
- Web server (Apache/Nginx)

### Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/teamallnighter/ableton-cookbook.git
   cd ableton-cookbook/laravel-app
   ```

2. **Install dependencies**
   ```bash
   composer install
   npm install && npm run build
   ```

3. **Environment setup**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Configure your `.env` file**
   ```env
   APP_NAME="Ableton Cookbook"
   APP_URL=https://your-domain.com
   
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=ableton_cookbook
   DB_USERNAME=your_username
   DB_PASSWORD=your_password
   
   # Email Configuration
   MAIL_MAILER=smtp
   MAIL_HOST=your-smtp-host
   MAIL_PORT=465
   MAIL_USERNAME=your-email@domain.com
   MAIL_PASSWORD=your-password
   MAIL_ENCRYPTION=ssl
   MAIL_FROM_ADDRESS=noreply@your-domain.com
   MAIL_FROM_NAME="Ableton Cookbook Team"
   ```

5. **Database setup**
   ```bash
   php artisan migrate
   php artisan db:seed
   ```

6. **Storage setup**
   ```bash
   php artisan storage:link
   ```

7. **Start the application**
   ```bash
   php artisan serve
   ```

## 📧 Email Configuration

The application includes a comprehensive email system with professional templates and authentication.

### DNS Records Required

Add these DNS records to your domain for optimal email deliverability:

```dns
# SPF Record
TXT @ "v=spf1 include:your-mail-provider.com ~all"

# DMARC Record  
TXT _dmarc "v=DMARC1; p=quarantine; rua=mailto:dmarc@your-domain.com; ruf=mailto:dmarc@your-domain.com; fo=1"

# DKIM Record (provided by your email service)
TXT selector._domainkey "v=DKIM1; k=rsa; p=YOUR_PUBLIC_KEY"
```

### Email Testing
```bash
php artisan email:test your-test-email@example.com
```

## 📝 Blog System

The application includes a comprehensive blog system for sharing insights, tutorials, and updates with the community.

### Features
- **Rich Text Editor**: TinyMCE WYSIWYG editor with image upload support
- **Category Management**: Create and organize blog posts with color-coded categories
- **SEO Optimization**: Automatic meta tags, structured data, and SEO-friendly URLs
- **Homepage Integration**: Recent blog posts automatically displayed on the homepage
- **Admin Panel**: Full admin interface at `/admin/blog` for content management

### Usage
1. **Admin Access**: Navigate to `/admin/blog` (requires admin role)
2. **Create Categories**: Set up blog categories with custom colors and descriptions
3. **Write Posts**: Use the rich text editor to create engaging blog content
4. **Publish**: Posts appear on `/blog` and the homepage when published

## 🚀 API Documentation

The platform provides a comprehensive REST API for developers and third-party integrations.

### Quick Access
- **Interactive Docs**: Visit `/api/docs` for Swagger UI
- **OpenAPI Spec**: JSON specification at `/api-docs.json`
- **Postman Collection**: Import from `/postman/` directory

### Postman Setup
1. **Import Collections**:
   ```bash
   # Main API collection
   postman/Ableton-Cookbook-API.postman_collection.json
   
   # Test suite
   postman/API-Tests.postman_collection.json
   ```

2. **Import Environments**:
   ```bash
   # Development
   postman/Development.postman_environment.json
   
   # Production  
   postman/Production.postman_environment.json
   ```

3. **Run Tests**:
   ```bash
   newman run postman/API-Tests.postman_collection.json \
     -e postman/Development.postman_environment.json
   ```

### API Features
- **Complete Coverage**: All platform features accessible via API
- **Authentication**: Support for both session and bearer token auth
- **Filtering & Pagination**: Advanced query capabilities
- **File Uploads**: Support for rack files and images
- **Rate Limiting**: Built-in rate limiting for API protection
- **Comprehensive Testing**: Automated test suite included

## 🔧 Development

### Key Commands
```bash
# Development environment
composer dev                        # Start serve + queue + logs + vite concurrently

# Testing
php artisan test                     # All tests
php artisan test --filter=RackApiTest  # Specific test class
./vendor/bin/phpunit --testsuite=Feature  # Feature tests only
./vendor/bin/phpunit --testsuite=Unit     # Unit tests only

# API documentation
php artisan l5-swagger:generate      # Generate legacy OpenAPI docs
# Modern docs auto-generate at /docs/api using Scramble

# Rack-specific commands  
php artisan rack:reanalyze          # Reprocess existing racks with updated analyzer

# SEO and optimization
php artisan sitemap:generate        # Generate SEO sitemaps
php artisan seo:optimize            # Batch SEO optimizations

# System maintenance
php artisan optimize:clear          # Clear all caches
php artisan email:test your-email@example.com  # Test email configuration
```

### Project Structure
```
laravel-app/
├── app/
│   ├── Console/Commands/     # Custom Artisan commands
│   ├── Http/Controllers/     # Application controllers
│   │   ├── Admin/           # Blog admin controllers
│   │   └── Api/            # API controllers with OpenAPI docs
│   ├── Models/              # Eloquent models (Rack, User, BlogPost, etc.)
│   ├── Notifications/       # Email notifications
│   ├── Services/           # Business logic services
│   │   ├── AbletonDrumRackAnalyzer/  # Specialized drum rack analysis
│   │   └── DrumRackAnalyzerService.php  # Laravel service wrapper
├── database/
│   ├── migrations/         # Database migrations
│   └── seeders/           # Database seeders
├── postman/               # API testing collections
│   ├── *.postman_collection.json
│   ├── *.postman_environment.json
│   └── README.md          # API documentation
├── resources/
│   ├── views/
│   │   ├── admin/blog/     # Blog admin templates
│   │   ├── blog/          # Public blog templates
│   │   ├── components/    # Reusable Blade components (including drum rack visualizer)
│   │   └── api/           # API documentation views
│   ├── css/              # Stylesheets (including drum-rack.css)
│   └── js/               # Frontend assets (Alpine.js, interactions)
└── routes/               # Application routes (web, api)
```

## 🌐 Live Demo

Visit [ableton.recipes](https://ableton.recipes) to see the application in action.

🚀 **Automated Deployment**: Now featuring GitHub Actions CI/CD for seamless deployments!

## 🥁 Drum Rack Analyzer - Advanced Features

The platform includes a specialized **Ableton Drum Rack Analyzer** that provides deep insights into drum rack structures, performance characteristics, and optimization opportunities.

### 🎵 Drum-Specific Analysis
- **MIDI Pad Mapping**: Visual representation of 24 standard drum positions (C1=36 Kick, D1=38 Snare, etc.)
- **Device Categorization**: Automatic classification of 50+ drum devices including DS series, synthesizers, and samplers
- **Performance Analysis**: Complexity scoring (0-100) with optimization recommendations
- **Chain Structure**: Detailed visualization of device chains within each drum pad

### 🎛️ Interactive Drum Visualization
- **4x4 Drum Pad Grid**: Interactive layout matching Ableton Live's standard configuration
- **Device Type Icons**: Visual indicators for synthesizers (🎹), samplers (📀), and effects (⚙️)
- **Responsive Design**: Adapts from 4x4 desktop grid to 2x2 mobile layout
- **Performance Indicators**: Color-coded complexity and resource usage warnings

### 📊 Analysis Capabilities
- **Automatic Detection**: Intelligent routing between drum and general rack analyzers
- **Batch Processing**: Support for analyzing multiple drum racks simultaneously
- **Performance Metrics**: CPU usage analysis and optimization suggestions  
- **Educational Features**: MIDI note education and drum programming insights

### 🔌 Drum Rack API Endpoints
| Method | Endpoint | Purpose | Rate Limit |
|--------|----------|---------|-----------|
| GET | `/api/v1/drum-racks/info` | Analyzer information | 60/min |
| POST | `/api/v1/drum-racks/analyze` | Single file analysis | 60/min |
| POST | `/api/v1/drum-racks/analyze-batch` | Batch processing | 10/min |
| POST | `/api/v1/drum-racks/validate` | File validation | 120/min |
| POST | `/api/v1/drum-racks/detect` | Drum rack detection | 120/min |

## 📊 Key Features Implementation

### Blog System Architecture
- **Models**: BlogPost and BlogCategory with Eloquent relationships
- **Admin Interface**: Complete CRUD operations with role-based access
- **WYSIWYG Editor**: TinyMCE integration with drag & drop image uploads
- **SEO Integration**: Automatic meta tags and structured data generation
- **Homepage Integration**: Recent blog posts dynamically displayed

### API Development
- **OpenAPI 3.0**: Complete API documentation with Swagger UI
- **Resource Controllers**: RESTful API endpoints for all major features
- **Authentication**: Dual support for session and Sanctum token auth
- **Testing Suite**: Comprehensive Postman collections with automated tests
- **Schema Validation**: Request validation with detailed error responses

### Registration System
- **Username Field**: Separate username from display name
- **Email Consent**: GDPR-compliant consent checkbox
- **Email Verification**: Custom-branded verification emails
- **Validation**: Comprehensive form validation with user feedback

### Email Authentication
- **SPF**: Authorizes sending servers
- **DKIM**: Cryptographic email signing  
- **DMARC**: Authentication policy enforcement
- **Professional Templates**: Custom-designed email templates

### Performance Optimizations
- **Database Indexing**: Optimized queries for large datasets
- **Caching**: Redis-based caching for improved performance
- **SEO**: Full search engine optimization implementation
- **Image Optimization**: Responsive image handling

## 🖥️ Server Configuration Notes

### Supervisor Queue Workers

All four Supervisor worker programs **must** have `autostart=true` in `/etc/supervisor/conf.d/ableton-cookbook.conf`. This applies to every queue priority group:

```ini
[program:ableton-cookbook-default]
autostart=true
...

[program:ableton-cookbook-high]
autostart=true
...

[program:ableton-cookbook-normal]
autostart=true
...

[program:ableton-cookbook-low]
autostart=true
...
```

> **Note (discovered 2026-02-26):** Without `autostart=true` on all programs, workers that are stopped (e.g. after a deploy restart) will not come back up automatically on server reboot or after a `supervisorctl reload`. This caused queued rack-processing jobs to silently pile up with no workers consuming them until a manual `supervisorctl start all` was issued.

After editing the config, reload Supervisor:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start all
```

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 📝 License

This project is open-sourced software licensed under the [MIT license](LICENSE).

## 🔗 Links

- **Website**: [ableton.recipes](https://ableton.recipes)
- **Repository**: [GitHub](https://github.com/teamallnighter/ableton-cookbook)
- **Issues**: [GitHub Issues](https://github.com/teamallnighter/ableton-cookbook/issues)

## 🙏 Acknowledgments

- Built with [Laravel](https://laravel.com)
- UI components from [Tailwind CSS](https://tailwindcss.com)
- Real-time features powered by [Laravel Livewire](https://laravel-livewire.com)
- Icons from [Heroicons](https://heroicons.com)

---

**Made with ❤️ for the Ableton Live community**
