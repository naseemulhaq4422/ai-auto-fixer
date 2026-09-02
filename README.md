# AI Auto-Fixer 🚀

**AI Auto-Fixer** is a 100% Free, Standalone WordPress Plugin designed for modern SEO, Generative Engine Optimization (GEO), and Answer Engine Optimization (AEO). It runs deep audits across your website and allows you to resolve critical search engine and AI crawler issues in a single click—completely offline with zero SaaS or external API dependencies.

---

## ✨ Features

- **🤖 Generative Engine Optimization (GEO)**: Grants indexing permissions in virtual `robots.txt` to major AI search engines (`GPTBot`, `ClaudeBot`, `PerplexityBot`, `CCBot`, `Google-Extended`) so your content is cited as an authoritative source in AI-generated answers.
- **📊 AEO Entity Schema Injection**: Injects rich Schema.org `WebSite`, `Organization`, and `SearchAction` JSON-LD entity markup directly into the HTML head.
- **🗺️ Automated XML Sitemaps**: Verifies sitemap availability, enables core XML sitemaps, and automatically declares the sitemap directive in `robots.txt`.
- **🔍 Search Engine Visibility Auto-Repair**: Detects and fixes WordPress core indexing blocks (`blog_public = 1`).
- **🏷️ AI-Optimized Meta & OpenGraph**: Auto-generates concise search descriptions and OpenGraph social entity tags.
- **⚡ 1-Click Fix All Issues**: Instantly execute all recommended automated fixes with a single button click from the admin dashboard.
- **🛡️ 100% Free & Standalone**: Runs purely inside WordPress without requiring external cloud accounts, SaaS API keys, or subscriptions.

---

## 📁 Multi-File Architecture

```
ai-auto-fixer/
├── ai-auto-fixer.php                     # Main plugin bootstrap & lifecycle hooks
├── uninstall.php                         # Secure database cleanup routine
├── assets/
│   ├── css/
│   │   └── admin-dashboard.css          # Modern standalone admin UI styling
│   ├── js/
│   │   └── admin-dashboard.js           # AJAX/REST interactivity & 1-Click Fix All engine
│   └── images/
│       └── icon.svg                      # Custom brand SVG icon
├── includes/
│   ├── class-autoloader.php              # PSR-4 / WordPress class autoloader
│   ├── class-plugin.php                  # Central Singleton orchestrator
│   ├── class-activator.php               # Activation & background audit scheduler
│   ├── class-deactivator.php             # Deactivation cleanup
│   ├── Admin/
│   │   ├── class-admin-menu.php          # Admin menu registration
│   │   └── class-admin-dashboard.php     # Admin dashboard controller
│   ├── Services/
│   │   ├── class-site-audit-scanner.php  # Local auditor (robots, sitemaps, on-page GEO)
│   │   └── class-auto-fix-service.php    # Standalone 1-Click Auto-Fix execution engine
│   └── Api/
│       └── class-rest-controller.php     # WP REST API endpoints (/scan, /autofix, /autofix/all)
└── views/
    ├── admin-dashboard-page.php          # Primary dashboard page template
    └── partials/
        ├── audit-results-table.php       # Free audit findings listing & 1-Click Auto-Fix buttons
        └── recommendations-card.php      # Local AI GEO/AEO optimization blueprints
```

---

## ⚙️ Requirements

- **WordPress**: 5.8 or higher
- **PHP**: 7.4 or higher (Compatible with PHP 8.0, 8.1, 8.2, 8.3)
- **Permissions**: Administrator (`manage_options`)

---

## 📦 Installation

1. Download or clone this repository into your WordPress plugins directory:
   ```bash
   cd wp-content/plugins/
   git clone https://github.com/naseemulhaq4422/ai-auto-fixer.git ai-auto-fixer
   ```
2. In your WordPress Admin, navigate to **Plugins > Installed Plugins**.
3. Activate **AI Auto-Fixer**.
4. Go to the new **AI Auto-Fixer** admin menu to view your health score and trigger 1-click fixes.

---

## 📄 License

This plugin is licensed under the [GPL v2 or later](https://www.gnu.org/licenses/gpl-2.0.html).
