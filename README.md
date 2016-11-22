# Mollie Payment Gateway for WHMCS

[![Code Climate](https://img.shields.io/codeclimate/github/Cloudstek/whmcs-mollie-payment-gateway.svg)](https://codeclimate.com/github/Cloudstek/whmcs-mollie-payment-gateway) [![Code Climate Issues](https://img.shields.io/codeclimate/issues/github/Cloudstek/whmcs-mollie-payment-gateway.svg)](https://codeclimate.com/github/Cloudstek/whmcs-mollie-payment-gateway/issues) [![Github Total Downloads](https://img.shields.io/github/downloads/Cloudstek/whmcs-mollie-payment-gateway/total.svg)](https://github.com/Cloudstek/whmcs-mollie-payment-gateway)

## Features

* Uses [Mollie checkout](https://www.mollie.com/en/checkout) screen
* No need for updates to support the newest payment methods
* No need to set the webhook url in your Mollie website profile
* Refunds through payment gateway
* Automatically creates Mollie customer references for quick checkout and nice stats.
* Automatically updates invoice payment status
* Gateway logging for easy debugging

### Wishlist

#### Charge customer for transaction fee

Currently there is no way to get the fees for a payment method through the API. Once support for that has been added, support will be added for charging customers the transaction fee. For this to work, support must be added to select a payment method beforehand.

For now: to charge customers a certain fee independent of which payment method will be used, please use a plugin like [Payment Gateway Charges for WHMCS](http://www.modulesgarden.com/products/whmcs/payment_gateway_charges/features) by ModulesGarden.

## Installation

### Prerequisites

* Working WHMCS installation (v6.x or above)
* [Mollie](https://mollie.com) account with active website profile
* PHP 5.3 or above
* [Composer](https://getcomposer.org)

### Installation steps

1. Download the [latest release](github.com/Cloudstek/whmcs-mollie-payment-gateway/releases/latest) or clone the repository
2. Enter the repository directory and enter the `mollie` directory.
3. Run `composer install --no-dev --optimize-autoloader` to install vendor packages
4. Go back to the repository directory and copy/upload the following files and folders to `<whmcs dir>/modules/gateways`. Do not copy any other files!
   * callback/
   * mollie/
   * mollie.php
5. Go to the WHMCS admin area and go to `setup -> payments -> payment gateways`.
6. Click the tab `All Payment Gateways`
7. Click `Mollie` to activate the payment gateway
8. **(!)** Enter your API key(s) and set `Convert To For Processing` to EUR (or your identifier for EURO)

*Mollie currently does not support any other currency than EURO. Converting all amounts to EURO before processing is essential to avoid issues!*

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for instructions how to contribute to this project. By participating, you are expected to have read, understand and respect the [code of conduct](CODE_OF_CONDUCT.md). Please report unacceptable behavior to [info@cloudstek.nl](info@cloudstek.nl).
