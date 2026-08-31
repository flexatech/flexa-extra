# Flexa Extra — Tests

Two suites, two purposes:

| Suite | Boots WordPress? | Runner | Speed | Covers |
|-------|------------------|--------|-------|--------|
| **Unit** (`tests/Unit`) | No — WP/WC stubbed | PHPUnit 11 (composer) | ~10 ms | Pure logic: sanitizer, pricing/validation engine, targeting resolver, field registry. |
| **Integration** (`tests/Integration`) | Yes — real WP + WooCommerce + test DB | PHPUnit 9 (phar) | ~300 ms | The hook glue end-to-end: add-to-cart fee/validation/anti-tampering, cart + order display, REST CRUD, product-page rendering. |

> Why two PHPUnit versions? The WordPress core test library still uses PHPUnit 9
> API (`parseTestMethodAnnotations`, removed in PHPUnit 10). The unit suite stays
> on modern PHPUnit 11; the integration suite runs against a pinned PHPUnit 9 phar
> so both stay clean. They never share a process.

## Unit suite

```bash
composer install
composer test              # or: vendor/bin/phpunit
```

DB-less: `tests/bootstrap.php` loads `tests/stubs/` (real-ish WP function stubs +
a minimal `WC_Product`). Fixtures are built through the **real**
`OptionSetSchema::sanitize()` (`tests/Support/OptionSetFactory`) so tests run
against the exact normalized shape production stores.

## Integration suite

One-time setup — install the WP test library + a **dedicated** test database
(never the live `local` DB; the script refuses that name), and fetch PHPUnit 9:

```bash
# 1. WP test library + test DB (Local example: MySQL via a space-free socket symlink)
ln -sf "$HOME/Library/Application Support/Local/run/<SITE_ID>/mysql/mysqld.sock" /tmp/flexa-mysql.sock
bin/install-wp-tests.sh flexa_extra_test root root 'localhost:/tmp/flexa-mysql.sock' nightly

# 2. PHPUnit 9 phar (git-ignored; ~9 MB)
curl -sSL https://phar.phpunit.de/phpunit-9.6.phar -o bin/phpunit-9.phar && chmod +x bin/phpunit-9.phar
```

Then run (point `WP_TESTS_DIR` at wherever step 1 installed it — it prints the path):

```bash
export WP_TESTS_DIR="$TMPDIR/wordpress-tests-lib"   # macOS: under /var/folders/...
composer test:integration                            # = php bin/phpunit-9.phar -c phpunit-integration.xml.dist
```

`tests/Integration/bootstrap.php` loads the WP test library, requires WooCommerce
(from the sibling plugins dir, override with `WC_PLUGINS_DIR`) and this plugin,
boots the plugin's engines, and installs WooCommerce's tables into the test DB.
`IntegrationTestCase` provides `create_product()`, `register_option_set()` and an
`add_to_cart()` helper that mirrors WooCommerce's form handler (it applies
`woocommerce_add_to_cart_validation` before adding — WC applies that filter in the
form handler, not in `WC_Cart::add_to_cart()`).

## Adding tests

- Pure logic → **unit** (`tests/Unit`), register fixtures via `OptionSetFactory`,
  `OptionSetFactory::reset()` in `setUp()`. Add a small guarded stub if the code
  reaches a new WP/WC function.
- Behaviour that depends on WordPress/WooCommerce (hooks, cart, REST, DB) →
  **integration** (`tests/Integration`), extend `IntegrationTestCase`.
