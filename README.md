# Solen — AI Wellness Coach Web App

A full PHP + SQLite web application with landing page, user authentication, AI wellness coach, admin dashboard, blog CMS, and subscription management.

---

## Quick Start

### Requirements
- PHP 8.1+ with `pdo_sqlite`, `curl`, `json` extensions
- Apache or Nginx (Apache `.htaccess` included)
- An Anthropic API key (get one at https://console.anthropic.com)

### 1. Upload Files
Upload the entire `solen/` folder to your web host's public directory (e.g. `public_html/`).

### 2. Set Your API Key
Open `config.php` and replace:
```php
define('CLAUDE_API_KEY', 'YOUR_ANTHROPIC_API_KEY_HERE');
```
with your real key. Or set it as a server environment variable:
```
CLAUDE_API_KEY=sk-ant-...
```

### 3. Set Your Site URL
In `config.php`:
```php
define('SITE_URL', 'https://yourdomain.com');
```

### 4. Permissions
Make the `database/` directory writable:
```bash
chmod 755 database/
```

### 5. Visit Your Site
The database is auto-created on first visit. Go to `/register.php` to create your first user account.

---

## Admin Dashboard

Go to `/admin/index.php` and sign in with:
- **Email:** `admin@getsolen.com`
- **Password:** `admin123`

**Change these immediately in Admin → Users → Edit.**

### Admin Features
| Section | What you can do |
|---|---|
| Dashboard | Overview stats, MRR, recent users & posts |
| Users | View, search, edit plan/role, reset password |
| Subscriptions | CRUD subscriptions, MRR/ARR tracking, cancel |
| Blog CMS | Create/edit/publish posts, SEO fields, categories |
| Settings | Site config, trial days, pricing, SMTP, Stripe, analytics |

---

## File Structure
```
solen/
├── config.php              ← API keys, site config (EDIT THIS)
├── index.php               ← Redirects to landing page
├── landing-page.html       ← Public sales/landing page
├── register.php            ← Sign up
├── login.php               ← Sign in
├── logout.php              ← Sign out
├── app.php                 ← Main wellness coach app (auth required)
├── dashboard.php           ← User dashboard
├── pricing.php             ← Pricing page (pulls from DB settings)
├── blog.php                ← Blog listing
├── post.php                ← Single blog post
├── 404.php                 ← Error page
├── .htaccess               ← URL rewriting + security
├── includes/
│   ├── db.php              ← SQLite + all schema
│   ├── auth.php            ← Login/register/session helpers
│   ├── functions.php       ← Utility functions
│   └── admin_layout.php    ← Admin sidebar/header/CSS
├── api/
│   ├── claude.php          ← Streaming Claude API proxy (auth gated)
│   └── data.php            ← Mood/memory/profile CRUD API
├── admin/
│   ├── index.php           ← Admin dashboard overview
│   ├── users.php           ← User management
│   ├── subscriptions.php   ← Subscription management
│   ├── blog.php            ← Blog CMS
│   └── settings.php        ← Full settings panel
└── database/
    └── solen.db            ← Auto-created SQLite database
```

---

## Default Admin Credentials
| Field | Value |
|---|---|
| Email | admin@getsolen.com |
| Password | admin123 |

⚠️ **Change these immediately after first login.**

---

## Adding Stripe Payments
1. Create products and prices in your Stripe dashboard
2. Add your Stripe keys in Admin → Settings → Integrations
3. Add a `webhook.php` to handle `checkout.session.completed` events and update the `subscriptions` table

---

## Blog Posts
Posts support full HTML content. Use the built-in editor in Admin → Blog Posts → New Post.

Each post auto-generates:
- SEO meta title + description
- Canonical URL
- Schema.org `Article` structured data
- Live Google preview in the editor

---

## Environment Variables (optional)
Instead of editing `config.php`, you can set:
```
CLAUDE_API_KEY=sk-ant-...
```
in your server's environment (`.env`, Apache `SetEnv`, etc.).

---

## Security Notes
- The `database/` directory is blocked by `.htaccess` from direct web access
- `config.php` is also blocked from direct access
- CSRF tokens are used on all forms
- Sessions are HTTP-only
- All user input is escaped with `h()` (htmlspecialchars)
- API endpoints require authentication

---

## Support
Built with PHP 8.1, SQLite3, React 18 (CDN), and the Anthropic Claude API.
