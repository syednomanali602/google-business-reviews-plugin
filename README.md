# Google Business Reviews

A lightweight WordPress plugin that fetches **Google Business reviews by business name** and displays them as a responsive, filterable carousel via a simple shortcode — without using Google's paid Places API.

## Features

- Fetches reviews from your Google Business Profile by searching for the business name (no Google Cloud account, no Places API billing)
- Works with either **SerpApi** or **Outscraper** as the data source — both have a free monthly allowance
- Caches reviews locally in WordPress and merges new reviews into the existing collection on every sync, so the stored set grows past what a single API call returns (up to 300 reviews)
- Automatic background sync via WP-Cron — daily, twice weekly, weekly, or manual — so site visitors never trigger an external API call
- Clean, Google-style review carousel: avatar (with initials fallback), star rating, verified badge, relative "time ago" timestamps, and a "Read more" clamp for long reviews
- Filter by minimum star rating and hide text-less reviews, either globally or per shortcode
- Optional ratings summary header (label, stars, review count, source logo)
- Built-in "Find your business" search so you pick the exact listing from Google Maps results — no manual Place ID lookup
- Live API quota display (searches used / remaining, renewal date) right in the settings page
- Fully responsive carousel with swipe support on mobile, and CSS custom properties for easy restyling

## Requirements

- WordPress 5.8+
- PHP 7.4+
- A free API key from [SerpApi](https://serpapi.com/manage-api-key) or [Outscraper](https://app.outscraper.com/profile)

## Installation

1. Download or clone this repository into `wp-content/plugins/google-business-reviews`.
2. Activate **Google Business Reviews** from the WordPress Plugins screen.
3. Go to **Settings → Google Reviews**.
4. Choose a review source (SerpApi or Outscraper) and paste its API key, then **Save Changes**.
5. Under **Find your business**, search by business name (adding the city improves accuracy) and click **Use this** on the correct listing. The first sync runs immediately.
6. Add the shortcode `[google_reviews]` to any page, post, or Elementor Shortcode widget.

## Usage

```
[google_reviews]
[google_reviews limit="9" min_rating="5" header="yes" lines="5"]
```

### Shortcode attributes

| Attribute    | Default       | Description                                             |
|--------------|---------------|-----------------------------------------------------------|
| `limit`      | `12`          | Maximum number of review cards to display                 |
| `min_rating` | plugin setting (`4`) | Hide reviews below this star rating                 |
| `header`     | `no`          | Show the ratings summary block above the carousel          |
| `lines`      | `4`           | Number of text lines before truncating with "Read more"    |
| `hide_empty` | plugin setting (`yes`) | Hide reviews that have a rating but no written text |

## How syncing works

1. Reviews are pulled from SerpApi or Outscraper, not scraped directly — these services handle Google's anti-bot measures and absorb markup changes, so the plugin doesn't break every time Google updates its page structure.
2. Every sync merges newly fetched reviews into what's already stored (deduplicated by review ID), so the local collection accumulates over time instead of being overwritten.
3. WP-Cron runs the sync on the schedule set in Settings. Because WP-Cron is visit-triggered, low-traffic sites should point a real server cron at `wp-cron.php` for reliable timing.
4. If a sync fails (bad key, exhausted quota, API downtime), the carousel keeps showing the last successfully synced reviews — the front end never goes blank, and the error is logged under **Settings → Google Reviews**.

## Checking your API quota

The settings page includes an **API quota** card:

- **SerpApi** — searches used this month, searches remaining, and the plan's renewal date. (Checking this does not use a search credit; it's a free account endpoint.)
- **Outscraper** — remaining credits, since Outscraper issues a one-time signup credit pool rather than a monthly allowance.

The quota is cached for 10 minutes and has its own **Refresh** button.

### Typical usage

- Business search: 1 credit (one-time, per business change)
- Each sync: 1 credit per review page fetched (1–5 pages, configurable)
- Weekly sync at 1 page/sync ≈ 4–5 credits per month — comfortably inside SerpApi's free 250/month plan

## Styling

All markup is scoped under `.d360-gbr`. Override the CSS custom properties to match your theme:

```css
.d360-gbr {
  --gbr-accent: #1c9a6c;   /* stars + verified check */
  --gbr-radius: 12px;      /* card corner radius */
  --gbr-per-view: 3;       /* cards visible at once on desktop */
}
```

For widgets injected after page load (AJAX tabs, popups), call `window.d360GbrInit()` once the markup is in the DOM.

## Notes

- One Google Business Profile / location per site install.
- Google reviews deleted on the source listing are not automatically removed from the local cache — only new and edited reviews are merged in on each sync.
- Review replies, photos, and translations are not currently fetched.

## License

GPL-2.0-or-later

## Author

**Syed Noman Ali**
[syednomanali.vercel.app](https://syednomanali.vercel.app/)
