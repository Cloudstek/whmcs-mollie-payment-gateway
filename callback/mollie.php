<?php
/**
 * Mollie Payment Gateway
 * @version 1.0.0
 */

namespace Cloudstek\WHMCS\Mollie;

require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../mollie/vendor/autoload.php';

use Mollie\API\Mollie;
use Mollie\API\Model\Payment;
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

        // Load WHMCS functions.
        $whmcs->load_function("gateway");
        $whmcs->load_function("invoice");

        // Store WHMCS version.
        $this->whmcsVersion = $whmcs->get_config('Version');

        // Gateway parameters.
        $this->params = getGatewayVariables('mollie');

        // Sandbox.
        $this->sandbox = $this->params['sandbox'] == 'on';
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
     * @param string $description Transaction description.
     * @param string $status      Transaction status.
     * @return void
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
     * @param integer $invoiceId   Invoice ID.
     * @param Payment $transaction Transaction.
     * @return void
     */
    private function handlePaid($invoiceId, Payment $transaction)
    {
        // Quit if transaction exists.
        checkCbTransID($transaction->id);

        // Log transaction.
        $this->logTransaction(
            "Payment {$transaction->id} completed successfully - invoice {$invoiceId}.",
            'Success'
        );

        // Add payment.
        addInvoicePayment(
            $invoiceId,
            $transaction->id,
            $transaction->amount,
            0.00,
            $this->params['paymentmethod'],
            false
        );
    }

    /**
     * Handle charged back transaction
     *
     * Charge back happens when a customer has paid the invoice but has charged it back. This marks the invoice unpaid
     * again and sends an email to the customer that the transaction has been charged back.
     *
     * @param integer $invoiceId   Invoice ID.
     * @param Payment $transaction Transaction.
     * @return void
     */
    private function handleChargedBack($invoiceId, Payment $transaction)
    {
        // Get invoice user ID.
        $userId = $this->pluck(
            Capsule::table('tblinvoices')
                ->where('id', $invoiceId),
            'userid'
        );

        // Set invoice unpaid.
        Capsule::table('tblinvoices')
            ->where('id', $invoiceId)
            ->update(array(
                'status' => 'Unpaid'
            ));

        // Transaction description.
        $transDescription = "Payment {$transaction->id} charged back by customer - invoice {$invoiceId}.";

        // Log transaction.
        $this->logTransaction($transDescription, 'Charged Back');

        // Add transaction.
        addTransaction(
            $userId,
            0,
            $transDescription,
            0,
            0,
            $transaction->amount,
            $this->params['paymentmethod'],
            $transaction->id,
            $invoiceId
        );
    }

    /**
     * Check if gateway module is activated and API keys are configured
     *
     * If no API keys have been entered, we cannot handle any payments and we can skip initialisation steps.
     *
     * @return boolean
     */
    public function isActive()
    {
        $apiKey = $this->getApiKey();
        return !empty($apiKey);
    }

    /**
     * Process transaction
     *
     * Main entry point of the callback. Mollie calls our callback with a POST request containing the id of our
     * transaction that has changed status. It's up to us to get the transaction by the id provided and handle it
     * according to its new status.
     *
     * @param integer|null $transId Mollie transaction ID to process.
     * @throws \Exception Invoice ID is missing from transaction metadata.
     * @return void
     */
    public function process($transId)
    {
        // Don't do anything if we're not active.
        if (!$this->isActive()) {
            return;
        }

        // API key.
        $apiKey = $this->getApiKey();

        // Mollie API instance.
        $mollie = new Mollie($apiKey);

        try {
            // Get transaction.
            $transaction = $mollie->payment($transId)->get();

            // Get invoice ID from transaction metadata.
            $invoiceId = $transaction->metadata->whmcs_invoice;

            if (empty($invoiceId)) {
                throw new \Exception('Invoice ID is missing from transaction metadata');
            }

            // Validate invoice ID.
            checkCbInvoiceID($invoiceId, $this->params['name']);

            // Allow manually calling callback to set payment status with test mode payments.
            if ($this->sandbox && $transaction->mode == "test" && !empty($_GET['status'])) {
                $transaction->status = $_GET['status'];
            }

            // Handle transaction status.
            switch ($transaction->status) {
                case "paid":
                    $this->handlePaid($invoiceId, $transaction);
                    break;
                case 'charged_back':
                    $this->handleChargedBack($invoiceId, $transaction);
                    break;
            }

            // Remove pending payment.
            Capsule::table('mod_mollie_transactions')
                ->where('invoiceid', $invoiceId)
                ->delete();
        } catch (\Exception $ex) {
            $exMessage = $ex->getMessage();

            // Log error.
            $this->logTransaction(
                "Payment {$transId} failed with an error - {$exMessage}.",
                'Error'
            );
        }
    }
}

// Check our transaction ID.
if (empty($_POST['id'])) {
    die();
}

// Initialize callback.
$cb = new Callback();

// Check if payment gateway active.
if (!$cb->isActive()) {
    // Get protocol.
    $protocol = (isset($_SERVER['SERVER_PROTOCOL']) ? $_SERVER['SERVER_PROTOCOL'] : 'HTTP/1.0');

    // Set headers.
    header("{$protocol} 503 Service Unavailable");
    header('Content-type: application/json');

    // Show JSON error message.
    echo json_encode(array(
        'status' => 'error',
        'message' => 'Gateway not activated. Please try again later.'
    ));

    exit;
}

// Process transaction.
$cb->process($_POST['id']);

// Make sure we send no output.
exit;
