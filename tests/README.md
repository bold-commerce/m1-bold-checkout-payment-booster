# Bold CheckoutPaymentBooster tests

## Unit tests (no full Magento install)

From this directory:

```bash
cd app/code/community/Bold/CheckoutPaymentBooster/tests/unit
composer require --dev phpunit/phpunit:^9
./vendor/bin/phpunit -c phpunit.xml.dist
```

Or with a global PHPUnit 9+:

```bash
phpunit -c app/code/community/Bold/CheckoutPaymentBooster/tests/unit/phpunit.xml.dist
```

## Security verification on staging

See [SECURITY.md](SECURITY.md) and the curl helpers in [curl/](curl/).