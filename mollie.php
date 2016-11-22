<?php
/**
 * Mollie Payment Gateway
 * @version 1.0.0
 */

if (!defined("WHMCS")) {
    die('This file cannot be accessed directly');
}

require_once __DIR__ . '/mollie/vendor/autoload.php';

use Cloudstek\WHMCS\Mollie\AdminStatus as MollieAdminStatus;
use Cloudstek\WHMCS\Mollie\Link as MollieLink;
use Cloudstek\WHMCS\Mollie\Refund as MollieRefund;

/**
 * Payment gateway metadata
 * @return array
 */
function mollie_MetaData()
{
    return array(
        'DisplayName'   => 'Mollie',
        'APIVersion'    => '1.1'
    );
}

/**
 * Payment gateway configuration
 * @return array
 */
function mollie_config()
{
    global $_LANG;

    // Set locale.
    putenv('LC_ALL='. $_LANG['locale']);
    setlocale(LC_ALL, $_LANG['locale']);

    // Text domain.
    $textDomain = 'MolliePaymentGateway';

    // Bind text domain.
    bindtextdomain($textDomain, __DIR__ . '/mollie/lang');

    // Visible options.
    return array(
        'FriendlyName'  => array(
            'Type'  => 'System',
            'Value' => 'Mollie'
        ),
        'live_api_key'  => array(
            'FriendlyName' => dgettext($textDomain, 'Mollie Live API Key'),
            'Type' => 'text',
            'Size' => '25',
            'Description' => dgettext($textDomain, 'Please enter your live API key.')
        ),
        'test_api_key'  => array(
            'FriendlyName' => dgettext($textDomain, 'Mollie Test API Key'),
            'Type' => 'text',
            'Size' => '25',
            'Description' => dgettext($textDomain, 'Please enter your test API key.')
        ),
        'sandbox'       => array(
            'FriendlyName' => dgettext($textDomain, 'Sandbox Mode'),
            'Type' => 'yesno',
            'Size' => '25',
            'Description' => dgettext(
                $textDomain,
                'Enable sandbox mode with test API key. No real transactions will be made.'
            )
        )
    );
}

/**
 * Refund transaction
 *
 * @see mollie/Refund.php
 * @param array $params Refund parameters.
 * @return array
 */
function mollie_refund(array $params)
{
    return (new MollieRefund($params))->run();
}

/**
 * Invoice page payment form output
 *
 * @see mollie/Link.php
 * @param array $params Link parameters.
 * @return string|null
 */
function mollie_link(array $params)
{
    return (new MollieLink($params))->run();
}

/**
 * Display admin message
 *
 * @see mollie/AdminStatus.php
 * @param array $params Admin status message parameters.
 * @return array|null
 */
function mollie_adminstatusmsg(array $params)
{
    return (new MollieAdminStatus($params))->run();
}
