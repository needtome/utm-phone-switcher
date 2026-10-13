# UTM Phone Switcher

Replace selected phone numbers on a WordPress site according to URL campaign parameters. The plugin changes matching text in the visitor's browser and updates matching `tel:` links without sending campaign data to a third-party service.

- Match exact parameter names or wildcard patterns such as `utm_*`.
- Leave the parameter value blank to match whenever the parameter is present, or set a value for an exact match.
- Use CSS selectors to target one phone number or several shared contact points.
- Test enabled rules on the public site for logged-in administrators before exposing them to visitors.
- Remember the selected rule IDs in a configurable first-party cookie.

## Screenshots

### Plugin settings

[![UTM Phone Switcher settings](./screenshot-1.png)](./screenshot-1.png)

### Campaign demo

[![Campaign-specific phone number demo](./screenshot-2.png)](./screenshot-2.png)

## Links

- [Plugin page and live demo](https://needtome.com/utm-phone-switcher/)

The WordPress.org submission is being prepared; its directory page will be linked here when available.

## Requirements

WordPress 6.3 or later and PHP 7.4 or later. The current release was checked with WordPress 7.1.3.

## Installation

Upload the `utm-phone-switcher` folder to `/wp-content/plugins/` or install the release ZIP from the WordPress admin Plugins screen. Activate the plugin, then configure rules in **Settings → UTM Phone Switcher**.

## Custom development and support

Custom WordPress plugin and website development is available at **$30/hour**. See [NeedToMe](https://needtome.com/) to get in touch.

Support further plugin development via [DonationAlerts](https://www.donationalerts.com/r/needtome) or [Patreon](https://patreon.com/needtome).

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
