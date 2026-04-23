# Kask SEO

A lightweight WordPress SEO plugin for per-page meta titles, descriptions, and noindex control — plus a sitewide redirects manager and CPT archive meta. No account required, no upsells, no bloat.

Built by [Kask Creativity LLC](https://kaskcreativity.com).

---

## Features

- **Per-page meta title & description** — overrides on every public post type via a meta box in the editor
- **Per-page noindex** — outputs `noindex, follow` on any singular post or page
- **Automatic CPT support** — meta box appears on all public post types with zero configuration; new CPTs are picked up automatically
- **CPT archive meta** — set title, description, and noindex for post type archive pages (e.g. `/resources/`)
- **Redirects manager** — add, edit, and delete 301/302 redirects from a simple admin table; cached for performance
- **Character counters** — live feedback in the meta box (amber at 50/140, red past 60/160)

---

## Installation

1. Upload the `kask-seo` folder to `/wp-content/plugins/`
2. Activate the plugin via **Plugins → Installed Plugins**
3. On first activation the redirects database table is created automatically

```
wp-content/
  plugins/
    kask-seo/
      kask-seo.php   ← the whole plugin is this single file
```

---

## Usage

### Per-page meta (posts, pages, CPTs)

Open any post, page, or CPT item in the editor. An **SEO** meta box appears in the editor (above the publish panel by default).

| Field | Notes |
|---|---|
| **Meta Title** | Overrides the `<title>` tag. Leave blank to use the WordPress default. |
| **Meta Description** | Outputs a `<meta name="description">` tag. Leave blank to omit. |
| **Noindex this page** | Outputs `<meta name="robots" content="noindex, follow">`. |

Character counters update live. Recommended lengths are 60 characters for titles and 160 for descriptions.

---

### Archive meta (CPT archive pages)

Go to **Kask SEO → Archive Meta**.

Each public post type that has `has_archive => true` is listed here. Set a custom title, description, and/or noindex toggle per archive. Leave fields blank to fall back to WordPress defaults.

If no CPT archives exist on a given site, the page will say so rather than showing an empty form.

---

### Redirects

Go to **Kask SEO → Redirects**.

**Adding a redirect:**

| Field | Notes |
|---|---|
| **Source Path** | Relative path only — e.g. `/old-page/`. Trailing slash and lowercase normalisation are applied automatically. |
| **Destination** | Full URL for external redirects (`https://example.com/new-page/`), or a relative path for internal ones. |
| **Type** | `301` Permanent (default, use for most redirects) or `302` Temporary. |

Redirects are loaded from a transient cache (1-day TTL) so there is only one database query per day rather than one per pageload. The cache is automatically cleared whenever a redirect is added, edited, or deleted.

The redirect processor skips admin, AJAX, and REST requests so it will not interfere with WordPress internals.

---

## Compatibility

- **WordPress:** 6.0+
- **PHP:** 8.0+
- **Multisite:** Not tested; use on individual sites only
- **Other SEO plugins:** Do not run alongside Rank Math, Yoast, The SEO Framework, or similar — output will conflict

---

## Does this handle everything an SEO plugin needs?

This plugin covers the essentials for most small business and nonprofit sites:

| Feature | Included |
|---|---|
| Per-page title & description | ✅ |
| Noindex per page | ✅ |
| CPT archive meta | ✅ |
| Sitewide redirects (301/302) | ✅ |
| XML sitemap | ❌ |
| Schema / structured data | ❌ |
| Open Graph / social meta | ❌ |
| Canonical tags | ❌ |
| Breadcrumbs | ❌ |

If you need a sitemap, the built-in WordPress sitemap (`/wp-sitemap.xml`, enabled by default since WP 5.5) is a reasonable lightweight option. For Open Graph and schema, consider adding those selectively rather than pulling in a full SEO suite.

---

## Changelog

### 1.0.0
- Initial release
- Per-page meta box on all public post types
- CPT archive meta admin page
- Redirects manager with transient caching
- Live character counters in the meta box

---

## License

GPL-2.0-or-later — see [https://www.gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html)
