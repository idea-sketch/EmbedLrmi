# EmbedLrmi

# Purpose

EmbedLRMI is a MediaWiki extension designed to embed **Learning Resource Metadata Initiative (LRMI)** data as JSON-LD metadata in wiki pages. The metadata is retrieved from an external LRMI-compatible repository, enabling better discoverability and interoperability of educational resources.

**Features:**

1. **Automatic embedding of LRMI metadata** as JSON-LD in the `<head>` section of wiki pages.
2. **Dynamic retrieval of metadata** from LRMI repositories via API.
3. **Configurable endpoint and URL transformations** for flexible integration.
4. **Visual display of metadata** via `?action=lrmi` and page for debugging and transparency.
5. **Toolbox link** for easy access to LRMI data on each page.
6. **Caching mechanism** to reduce API calls and improve performance.
7. **Cache management** through a dedicated special page.

---

# How does it work

The extension hooks into MediaWiki's rendering pipeline and retrieves LRMI metadata for the current page from an external LRMI repository. The metadata is then embedded as a JSON-LD `<script>` tag in the page's HTML head.

The extension can replace parts of the page URL (e.g., domain or underscores) to match the format expected by your LRMI repository API.

---

# Requirements

- **MediaWiki 1.35+** (tested with 1.43).
- **PHP 8.0+** (required for compatibility with modern LRMI APIs and JSON processing).
- Access to an LRMI-compatible repository API endpoint.

---

# Usage

The extension works automatically for all content pages. No parser functions are required.

## Viewing LRMI Data

There are several ways to access and view LRMI metadata:

### 1. Toolbox Link

A "Show LRMI data" link appears in the toolbox (sidebar) on all content pages. Clicking this link displays the LRMI metadata for the current page in a structured, human-readable format.

### 2. Action Parameter

You can append `?action=lrmi` to any page URL to view its LRMI metadata:

```
https://your-wiki.example.com/wiki/Page_Name?action=lrmi
```

### 3. Special Page for Cache Management

Users with the `purgelrmicache` permission (by default: sysops and bureaucrats) can access `Special:PurgeLrmiCache` to clear all cached LRMI metadata. This forces the extension to fetch fresh data from the repository on the next page view.

## Configuration

The extension requires configuration in your `LocalSettings.php`:

| Variable                      | Description                                                                         | Required | Default           |
| ----------------------------- | ----------------------------------------------------------------------------------- | -------- | ----------------- |
| `$wgEmbedLrmiEndpoint`        | The LRMI repository API endpoint URL.                                               | Yes      | -                 |
| `$wgEmbedLrmiUrlReplacements` | An associative array to replace parts of the page URL before sending it to the API. | No       | -                 |
| `$wgEmbedLrmiCacheExpiry`     | Cache duration in seconds. Set to `0` to disable caching.                           | No       | 2592000 (30 days) |

### Example Configuration

```php
# EmbedLRMI Extension Configuration
wfLoadExtension( 'EmbedLrmi' );

$wgEmbedLrmiEndpoint = 'https://repository.example.org/api/v1/lrmi/search';

# Optional: Transform URLs before querying the API
$wgEmbedLrmiUrlReplacements = [
    'from' => ['internal-wiki.local', '_'],
    'to'   => ['public-wiki.org', '%20']
];

# Optional: Set cache expiry (default: 30 days)
$wgEmbedLrmiCacheExpiry = 604800; // 7 days

# Optional: Grant cache purge permission to additional user groups
$wgGroupPermissions['sysop']['purgelrmicache'] = true;
$wgGroupPermissions['bureaucrat']['purgelrmicache'] = true;
```

### Configuration Notes:

- **`$wgEmbedLrmiEndpoint`**: The API endpoint for fetching LRMI metadata. Must be accessible from your MediaWiki server. The endpoint should accept POST requests with a JSON body containing the page URL.
- **`$wgEmbedLrmiUrlReplacements`**: Used to transform the page URL to match the format expected by your LRMI repository API. For example, replacing underscores (`_`) with `%20` (URL-encoded spaces) or adjusting the domain for external repositories.
- **`$wgEmbedLrmiCacheExpiry`**: Metadata is cached to reduce API calls. Adjust this value based on how frequently your LRMI data changes.

---

# Installation

### Download the Extension

Clone or download the extension into your MediaWiki `extensions/` directory:

```bash
cd extensions/
git clone <repository-url> EmbedLrmi
```

### Enable the Extension

Add the following line to your `LocalSettings.php`:

```php
wfLoadExtension( 'EmbedLrmi' );
```

### Configure the Extension

Add the required configuration variables to your `LocalSettings.php` (see [Configuration](#configuration)).

### Verify Installation

Navigate to `Special:Version` on your wiki to confirm the extension is listed under "Installed extensions".

---

# Features in Detail

## Automatic Metadata Embedding

LRMI metadata is automatically embedded as JSON-LD in the `<head>` section of every content page. This makes your educational content discoverable by search engines and LRMI-aware tools.

## Viewing Metadata

Use the toolbox link or `?action=lrmi` parameter to view the retrieved metadata in a human-readable tree structure. This is useful for:

- Debugging API integration
- Verifying metadata accuracy
- Transparency for content editors

## Cache Management

The extension caches API responses to improve performance and reduce load on your LRMI repository. Cache entries are:

- Automatically cleared when a page is saved
- Manually clearable via `Special:PurgeLrmiCache`
- Configurable via `$wgEmbedLrmiCacheExpiry`

## Permissions

- The cache purge special page requires the `purgelrmicache` permission (by default granted to sysops and bureaucrats)
- All users can view LRMI data via the toolbox link or action parameter
- You can grant the `purgelrmicache` permission to additional user groups in `LocalSettings.php`:

```php
$wgGroupPermissions['your-group']['purgelrmicache'] = true;
```

---

# Example

If a wiki page has the URL `https://wiki.internal.local/wiki/Example_Page`, and you configure:

```php
$wgEmbedLrmiUrlReplacements = [
    'from' => ['internal.local', '_'],
    'to'   => ['external.org', '%20']
];
```

The extension will:

1. Transform the URL to `https://wiki.external.org/wiki/Example%20Page`
2. Query the LRMI repository API for metadata associated with this URL
3. Embed the metadata as JSON-LD in the page's `<head>` section
4. Make the metadata viewable via the toolbox link

---

# API Integration

The extension sends POST requests to your configured endpoint with the following JSON structure:

```json
{
  "criteria": [
    {
      "property": "ccm:wwwurl",
      "values": ["https://wiki.example.org/wiki/Page_Name"]
    }
  ]
}
```

Your LRMI repository should return a JSON response with a `nodes` array containing LRMI metadata objects.

---

# Troubleshooting

### No metadata appears

- Check that `$wgEmbedLrmiEndpoint` is correctly set and accessible
- Verify your LRMI repository returns data for the page URL
- Check PHP error logs for API connection issues
- Try clearing the cache via `Special:PurgeLrmiCache`

### Toolbox link not appearing

- Ensure the page is a content page (not a special page or talk page)
- Check that i18n messages are properly loaded
- Try rebuilding the localization cache: `php maintenance/run.php rebuildLocalisationCache`

### Cannot access Special:PurgeLrmiCache

- Verify you have the `purgelrmicache` permission
- Check `Special:ListGroupRights` to see which groups have this permission
- Sysops and bureaucrats have this permission by default

### URL transformations not working

- Verify `$wgEmbedLrmiUrlReplacements` syntax matches the example
- Check error logs for transformation issues
- Use `?action=lrmi` to see the actual URL being sent to the API

---

# Compatibility with LRMI Repositories

This extension is designed to work with any LRMI-compatible repository that:

- Accepts POST requests with JSON-encoded search criteria
- Returns LRMI metadata in JSON-LD format
- Supports URL-based content identification

**Tested with:**

- edu-sharing repositories
- Other LRMI repositories may work but have not been extensively tested

---

# Limitations

- The extension expects a specific API request/response format (see [API Integration](#api-integration))
- If the API returns no data for a page, no metadata will be embedded
- Cache invalidation is automatic on page save but may need manual intervention for external metadata changes

---

# TODO

- Support for additional API authentication methods
- Batch processing for multiple pages
- Alternative metadata formats beyond JSON-LD
- Configurable API request structure
- Statistics dashboard for cached vs. fresh data

---

# License

The software is licensed under the **GNU General Public License v2.0 or later**. For details, see [LICENSE](https://www.gnu.org/licenses/old-licenses/gpl-2.0.html).

---

# Compatibility

The extension is designed for **MediaWiki 1.39+** and **PHP 8.0+**. It has been tested with MediaWiki 1.39.

---

# History

- **2025-11-25**: Initial version with automatic embedding, caching, action view, toolbox link, and cache management special page.
