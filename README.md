# m1-bold-checkout-payment-booster

## Description

Use this repository to implement Bold Booster for PayPal on Magento 1 (M1).

## Installation

1. Download the repository contents (click the **Code** drop-down menu and select **Download ZIP**).
2. Unzip the downloaded directory.
3. Copy contents of the repository to `app/code/community/Bold/CheckoutPaymentBooster`
4. Run `cp app/code/community/Bold/CheckoutPaymentBooster/etc/modules/Bold_CheckoutPaymentBooster.xml app/etc/modules/`
5. Run `cp -r app/code/community/Bold/CheckoutPaymentBooster/design/* app/design/`
6. Run `  cp -r ./app/code/community/Bold/CheckoutPaymentBooster/skin/* ./skin/`
7. If you're using OpenMage, run `composer config use-include-path true`
8. Clean your cache.

To continue setting up Bold Booster for PayPal, continue following the steps [Bold's developer documentation](https://developer.boldcommerce.com/guides/checkout/bold-boosters/m1-bold-booster-for-paypal#step-3-complete-bold-booster-for-paypal-onboarding).


## Documentation

* [Set up Bold Booster for PayPal on Magento 1](https://developer.boldcommerce.com/guides/checkout/bold-boosters/m1-bold-booster-for-paypal)
* [Upgrade Bold on Magento 1](https://developer.boldcommerce.com/guides/platform-integration/magento-1/versions)
* [Supported extensions on Magento 1](https://developer.boldcommerce.com/guides/platform-integration/magento-1/extensions)
* [Troubleshooting](https://developer.boldcommerce.com/guides/platform-integration/magento-1/troubleshooting)

## Support

If you have any issues with your onboarding, please [submit a support ticket](https://support.boldcommerce.com/hc/en-us/requests/new?ticket_form_id=1900000280347). Or, if you have a Bold representative working with you, [add them as a support user on your store](https://support.boldcommerce.com/hc/en-us/articles/16250051240596-Invite-or-Deactivate-a-User-in-Account-Center) and contact them for further help.