# Block Visibility Edge Cache

[![Test](https://github.com/xwp/block-visibility-edge-cache/actions/workflows/test.yml/badge.svg)](https://github.com/xwp/block-visibility-edge-cache/actions/workflows/test.yml)

A WordPress plugin that integrates the [Block Visibility](https://wordpress.org/plugins/block-visibility/) plugin with WordPress.com VIP edge caching. It ensures that edge caches are automatically purged at the exact moment a block's visibility is scheduled to change.

## How it Works

When using edge caching, page content is cached at the "edge" (servers closer to the user). Traditional "Date/Time" visibility rules in the Block Visibility plugin usually rely on PHP executing during the page load to decide whether to show or hide a block. With edge caching, PHP might not run for every request, leading to stale content being served even after a visibility transition should have occurred.

This plugin solves this by:
1. Parsing the blocks in a post when it is saved or published.
2. Calculating all future "transition" timestamps (when a block should appear or disappear) based on the Date/Time settings.
3. Scheduling background tasks using [Action Scheduler](https://actionscheduler.org/) to purge the post's edge cache at those specific timestamps.

## Requirements

- PHP 8.4+
- WordPress 6.0+
- [Block Visibility](https://wordpress.org/plugins/block-visibility/) plugin
- [Action Scheduler](https://actionscheduler.org/) (bundled with the plugin or available via WooCommerce)

## Installation

1. Upload the plugin folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Ensure the Block Visibility plugin is also active.

## Supported Visibility Controls

To maintain compatibility with edge caching, only **time-based** visibility controls are supported:

- **Date Range:** Specific start and end dates/times.
- **Seasonal:** Recurring date ranges every year.
- **Day of Week:** Showing/hiding blocks on specific days.
- **Time of Day:** Showing/hiding blocks during specific hours.

### Disabled Controls

The following controls are automatically disabled because they depend on dynamic request data (visitor-specific) which is incompatible with static edge caching:

- Browser / Device
- Cookie
- Location
- Role / User
- Screen Size
- URL Path / Query String
- Referral Source
- WooCommerce / EDD / WP Fusion integration
- ACF fields

## Developer Hooks

### `xwp_block_visibility_edge_cache_purged`
Triggered after a post's edge cache has been purged. Useful for adding custom purging logic for other cache layers.

```php
add_action( 'xwp_block_visibility_edge_cache_purged', function( $post_id ) {
    // Custom purging logic here.
} );
```

## Local Development & Testing

### Installation
```bash
composer install
```

### Running Tests
The project includes a comprehensive test suite using PHPUnit.
```bash
# Run PHPUnit tests
composer test

# Run PHP Code Sniffer
composer lint

# Run PHPStan
composer analyze
```

## License

GPLv2 or later.
