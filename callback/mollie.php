<?php

/**
 * Mollie Payment Gateway
 * @version 1.0.0
 */

namespace Cloudstek\WHMCS\Mollie;

require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../mollie/vendor/autoload.php';

use Mollie\API\Mollie;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Webhook callback class
 */
class Callback
{
    /** @var array Gateway parameters */
    private $params;

    /** @var bool Sandbox mode */
    private $sandbox;

    /** @var string WHMCS version */
    private $whmcsVersion;

    /**
     * Callback constructor
     */
    public function __construct()
    {
        global $whmcs;

        // Load WHMCS functions
        $whmcs->load_function("gateway");
        $whmcs->load_function("invoice");

        // Store WHMCS version
        $this->whmcsVersion = $whmcs->get_config('Version');

        // Gateway parameters
        $this->params = getGatewayVariables('mollie');

        // Sandbox
        $this->sandbox = $this->params['sandbox'] == 'on';
    }

    /**
     * Get single value from database
     *
     * WHMCS v6 uses an older version of Eloquent. In v7 it has been replaced by a newer version which deprecates pluck
     * and causes different behaviour. Instead value is used, which does the same as the old pluck method.
     *
     * @param QueryBuilder $query
     * @param string $column
     * @return mixed
     */
    private function pluck(QueryBuilder $query, $column)
    {
        if (version_compare($this->whmcsVersion, '7.0.0', '<')) {
            return $query->pluck($column);
        }

        return $query->value($column);
    }

    /**
     * Log transaction
     *
     * Log a transaction in the gateway log and prepend the sandbox string when we're using sandbox mode. This makes it
     * easier to find transactions in the gateway log when debugging.
     *
     * @param string $description Transaction description
     * @param string $status Transaction status
     */
    private function logTransaction($description, $status = 'Success')
    {
        if ($this->sandbox) {
            $description = "[SANDBOX] " . $description;
        }

        logTransaction($this->params['name'], $description, ucfirst($status));
    }

    /**
     * Get Mollie API key
     * @return string|null
     */
    protected function getApiKey()
    {
        if (empty($this->params)) {
            return null;
        }

        return $this->sandbox ? $this->params['test_api_key'] : $this->params['live_api_key'];
    }

    /**
     * Handle paid transaction
     *
     * @param int $invoiceId Invoice ID
     * @param \Mollie\API\Model\Payment $transaction Transaction
     */
    private function handlePaid($invoiceId, $transaction)
    {
        // Quit if transaction exists
        checkCbTransID($transaction->id);

        // Log transaction
        $this->logTransaction(
            "Payment {$transaction->id} completed successfully - invoice {$invoiceId}.",
            'Success'
        );

        // Add payment
        addInvoicePayment(
            $invoiceId,                         // Invoice ID
            $transaction->id,                   // Transaction ID
            $transaction->amount,               // Transaction amount
            0.00,                               // Transaction fees
            $this->params['paymentmethod'],     // Payment method
            false                               // Don't send an email (false to send, true to not send, lol!)
        );
    }

    /**
     * Handle charged back transaction
     *
     * Charge back happens when a customer has paid the invoice but has charged it back. This marks the invoice unpaid
     * again and sends an email to the customer that the transaction has been charged back.
     *
     * @param int $invoiceId Invoice ID
     * @param \Mollie\API\Model\Payment $transaction Transaction
     */
    private function handleChargedBack($invoiceId, $transaction)
    {
        // Get invoice user ID
        $userId = $this->pluck(
            Capsule::table('tblinvoices')
                ->where('id', $invoiceId),
            'userid'
        );

        // Set invoice unpaid
        Capsule::table('tblinvoices')
            ->where('id', $invoiceId)
            ->update([
                'status' => 'Unpaid'
            ]);

        // Transaction description
        $transDescription = "Payment {$transaction->id} charged back by customer - invoice {$invoiceId}.";

        // Log transaction
        $this->logTransaction($transDescription, 'Charged Back');

        // Add transaction
        addTransaction(
            $userId,                            // User ID
            0,                                  // Currency ID (0 to use user default)
            $transDescription,                  // Transaction description
            0,                                  // Amount in
            0,                                  // Transaction fees
            $transaction->amount,               // Amount out
            $this->params['paymentmethod'],     // Payment method
            $transaction->id,                   // Transaction ID
            $invoiceId                          // Invoice ID
        );
    }

    /**
     * Check if gateway module is activated and API keys are configured
     *
     * If no API keys have been entered, we cannot handle any payments and we can skip initialisation steps.
     *
     * @return bool
     */
    public function isActive()
    {
        return !empty($this->getApiKey());
    }

    /**
     * Process transaction
     *
     * Main entry point of the callback. Mollie calls our callback with a POST request containing the id of our
     * transaction that has changed status. It's up to us to get the transaction by the id provided and handle it
     * according to its new status.
     *
     * @param int|null $transId Mollie transaction ID to process
     */
    public function process($transId)
    {
        // Don't do anything if we're not active
        if (!$this->isActive()) {
            return;
        }

        // API key
        $apiKey = $this->getApiKey();

        // Mollie API instance
        $mollie = new Mollie($apiKey);

        try {
            // Get transaction
            $transaction = $mollie->payment($transId)->get();

            // Get invoice ID from transaction metadata
            $invoiceId = $transaction->metadata->whmcs_invoice;

            if (empty($invoiceId)) {
                throw new \Exception('Invoice ID is missing from transaction metadata');
            }

            // Validate invoice ID
            checkCbInvoiceID($invoiceId, $this->params['name']);

            // Allow manually calling callback to set payment status with test mode payments
            if ($this->sandbox && $transaction->mode == "test" && !empty($_GET['status'])) {
                $transaction->status = $_GET['status'];
            }

            // Handle transaction status
            switch ($transaction->status) {
                case "paid":
                    $this->handlePaid($invoiceId, $transaction);
                    break;
                case 'charged_back':
                    $this->handleChargedBack($invoiceId, $transaction);
                    break;
            }

            // Remove pending payment
            Capsule::table('mod_mollie_transactions')
                ->where('invoiceid', $invoiceId)
                ->delete();
        } catch (\Exception $ex) {
            $exMessage = $ex->getMessage();

            // Log error
            $this->logTransaction(
                "Payment {$transId} failed with an error - {$exMessage}.",
                'Error'
            );
        }
    }
}

// Check our transaction ID
if (empty($_POST['id'])) {
    die();
}

// Initialize callback
$cb = new Callback();

// Check if payment gateway active
if (!$cb->isActive()) {
    http_response_code(501);
    die('Gateway not activated.');
}

// Process transaction
$cb->process($_POST['id']);

// Make sure we send no output
exit;
