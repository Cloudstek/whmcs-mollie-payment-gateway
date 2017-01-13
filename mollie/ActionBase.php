<?php
/**
 * Mollie Payment Gateway
 * @version 1.0.2
 */

namespace Cloudstek\WHMCS\Mollie;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Base class for gateway actions capture, refund, link
 */
abstract class ActionBase
{
    /** @var array $actionParams Action (_link, _refund) parameters */
    protected $actionParams;

    /** @var array $gatewayParams Gateway parameters */
    protected $gatewayParams;

    /** @var string $whmcsVersion WHMCS version */
    protected $whmcsVersion;

    /** @var bool $sandbox Sandbox mode */
    protected $sandbox;

    /** @var string $textDomain Gettext text domain */
    protected $textDomain = 'MolliePaymentGateway';

    /**
     * Action constructor
     * @param array $params Action parameters.
     */
    protected function __construct(array $params)
    {
        global $whmcs;

        // Parameters.
        $this->actionParams = $params;
        $this->gatewayParams = getGatewayVariables('mollie');

        // WHMCS version.
        $this->whmcsVersion = $whmcs->get_config('Version');

        // Sandbox mode.
        $this->sandbox = $this->gatewayParams['sandbox'] == 'on';
    }

    /**
     * Get single value from database
     *
     * WHMCS v6 uses an older version of Eloquent. In v7 it has been replaced by a newer version which deprecates pluck
     * and causes different behaviour. Instead value is used, which does the same as the old pluck method.
     *
     * @param QueryBuilder $query  Query to execute.
     * @param string       $column Column to get the value from.
     * @return mixed
     */
    protected function pluck(QueryBuilder $query, $column)
    {
        // WHMCS 6.0.
        if (version_compare($this->whmcsVersion, '7.0.0', '<')) {
            return $query->pluck($column);
        }

        return $query->value($column);
    }

    /**
     * Get Mollie customer ID for WHMCS client
     *
     * @param integer $clientId WHMCS client ID.
     * @return string|bool Mollie customer ID or false if none defined
     */
    protected function getCustomerId($clientId)
    {
        $customerId = $this->pluck(
            Capsule::table('mod_mollie_customers')
                ->where('clientid', $clientId),
            'customerid'
        );

        try {
            $customerId = decrypt($customerId);

            if (empty($customerId)) {
                return false;
            }

            return $customerId;
        } catch (\Exception $ex) {
            return false;
        }
    }

    /**
     * Set Mollie customer ID for WHMCS client
     *
     * @param integer $clientId   WHMCS client ID.
     * @param string  $customerId Mollie customer ID.
     * @return void
     */
    protected function setCustomerId($clientId, $customerId)
    {
        $exists = Capsule::table('mod_mollie_customers')
                    ->where('clientid', $clientId)
                    ->count();

        // Update customer ID.
        if ($exists) {
            Capsule::table('mod_mollie_customers')
                ->where('clientid', $clientId)
                ->update(array(
                    'customerid' => encrypt($customerId)
                ));

            return;
        }

        // Insert customer ID.
        Capsule::table('mod_mollie_customers')
            ->insert(array(
                'clientid' => $clientId,
                'customerid' => encrypt($customerId)
            ));
    }

    /**
     * Get full URL to callback for use by the Mollie webhookUrl parameter
     * @return string|null
     */
    protected function getWebhookUrl()
    {
        global $CONFIG;

        // Get WHMCS URL.
        $whmcsUrl = $CONFIG['SystemURL'];

        // WHMCS 6.0 compat SSL URL.
        if (version_compare($this->whmcsVersion, '7.0.0', '<') && !empty($CONFIG['SystemSSLURL'])) {
            $whmcsUrl = $CONFIG['SystemSSLURL'];
        }

        // Don't set callback when developing.
        if (array_key_exists('develop', $this->gatewayParams) && $this->gatewayParams['develop'] == "on") {
            return null;
        }

        // Build URL.
        return "{$whmcsUrl}/modules/gateways/callback/mollie.php";
    }

    /**
     * Get Mollie API key
     * @return string|null
     */
    protected function getApiKey()
    {
        if (empty($this->gatewayParams)) {
            return null;
        }

        return $this->sandbox ? $this->gatewayParams['test_api_key'] : $this->gatewayParams['live_api_key'];
    }

    /**
     * Check for pending transactions
     *
     * @param integer $invoiceId Invoice ID.
     * @return boolean
     */
    protected function hasPendingTransactions($invoiceId)
    {
        return Capsule::table('mod_mollie_transactions')
                        ->where('invoiceid', $invoiceId)
                        ->where('status', 'pending')
                        ->count();
    }

    /**
     * Check for failed transactions
     *
     * @param integer $invoiceId Invoice ID.
     * @return boolean
     */
    protected function hasFailedTransactions($invoiceId)
    {
        return Capsule::table('mod_mollie_transactions')
                        ->where('invoiceid', $invoiceId)
                        ->where('status', 'failed')
                        ->count();
    }

    /**
     * Set transaction status
     *
     * @param integer $invoiceId     Invoice ID.
     * @param string  $status        Status of transaction, failed or pending.
     * @param string  $transactionId Transaction ID when pending.
     * @return void
     */
    protected function updateTransactionStatus($invoiceId, $status, $transactionId = null)
    {
        // Check for existing transaction.
        $exists = Capsule::table('mod_mollie_transactions')
            ->where('invoiceid', $invoiceId)
            ->count();

        // Update transaction status.
        if ($exists) {
            Capsule::table('mod_mollie_transactions')
                ->where('invoiceid', $invoiceId)
                ->update(array(
                    'transid'   => $transactionId,
                    'status'    => $status
                ));

            return;
        }

        Capsule::table('mod_mollie_transactions')
            ->insert(array(
                'invoiceid' => $invoiceId,
                'transid'   => $transactionId,
                'status'    => $status
            ));
    }

    /**
     * Log transaction
     *
     * @param string $description Transaction description.
     * @param string $status      Transaction status.
     * @return void
     */
    protected function logTransaction($description, $status = 'Success')
    {
        if ($this->sandbox) {
            $description = "[SANDBOX] " . $description;
        }

        logTransaction($this->gatewayParams['name'], $description, ucfirst($status));
    }

    /**
     * Initialization
     *
     * Initializes text domain, databases tables and checks if API keys are entered.
     *
     * @return bool Returns true if initialization is complete and API key is entered
     */
    protected function initialize()
    {
        global $_LANG;

        // Set locale.
        putenv('LC_ALL='. $_LANG['locale']);
        setlocale(LC_ALL, $_LANG['locale']);

        // Bind text domain.
        bindtextdomain($this->textDomain, __DIR__ . '/lang');

        // Create database.
        if (!Capsule::schema()->hasTable('mod_mollie_transactions')) {
            Capsule::schema()->create('mod_mollie_transactions', function ($table) {
                $table->increments('id');
                $table->integer('invoiceid')->unsigned()->unique();
                $table->string('transid')->unique()->nullable();
                $table->string('status');
            });
        }

        if (!Capsule::schema()->hasTable('mod_mollie_customers')) {
            Capsule::schema()->create('mod_mollie_customers', function ($table) {
                $table->increments('id');
                $table->integer('clientid')->unsigned()->unique();
                $table->string('customerid')->unique();
            });
        }

        // Check API key.
        if (!empty($this->gatewayParams)) {
            $apiKey = $this->sandbox ? $this->gatewayParams['test_api_key'] : $this->gatewayParams['live_api_key'];

            // Return true if API key is entered for current mode.
            return !empty($apiKey);
        }

        return false;
    }

    /**
     * Run action
     * @return void
     */
    abstract public function run();
}
