<?php
/**
 * Mollie Payment Gateway
 * @version 1.0.0
 */

namespace Cloudstek\WHMCS\Mollie;

use Mollie\API\Mollie;
use Mollie\API\Exception\RequestException;
use Wukka\Nonce;

/**
 * Link action
 */
class Link extends ActionBase
{
    /** @var int $invoiceId Invoice ID */
    private $invoiceId;

    /** @var array $clientDetails */
    private $clientDetails;

    /** @var Nonce $nonce */
    private $nonce;

    /** @var string $nonceToken Nonce token */
    private $nonceToken;

    /**
     * Link action constructor
     * @param array $params Link action parameters.
     */
    public function __construct(array $params)
    {
        parent::__construct($params);

        // Store invoice ID, you'll need it.
        $this->invoiceId = $params['invoiceid'];

        // Client details.
        $this->clientDetails = $params['clientdetails'];

        // Nonce generator.
        $this->nonce = new Nonce('Egybg6fQSVtMHfSKVUrB9Q4YZpH5q5cNwwZwrRMgtmH5EvHgG4n2KBegnfBZFP5B', 40);

        // Nonce token.
        $this->nonceToken = $this->clientDetails['userid'] . session_id();
    }

    /**
     * Return an HTML formatted and translated message
     *
     * @param string $message Untranslated message.
     * @return string HTML formatted and translated message
     */
    private function statusMessage($message)
    {
        return
            ($this->sandbox ? '<strong style="color: red;">SANDBOX MODE</strong><br />' : null)
            . '<p>'.$message.'</p>';
    }

    /**
     * Pay now button form
     * @return string HTML form
     */
    private function payNowForm()
    {
        // Create nonce.
        $nonce = $this->nonce->create($this->nonceToken);

        // Store nonce.
        $_SESSION['paynow_nonce'] = $nonce;

        // Form.
        $form = <<<FORM
            <form action="" method="POST">
                <input type="hidden" name="action" value="paynow" />
                <input type="hidden" name="nonce" value="%s" />
                <input type="submit" value="%s" />
            </form>
FORM;

        // Replace variables.
        $form = sprintf($form, $nonce, $this->actionParams['langpaynow']);

        // Pending payments.
        if ($this->hasPendingTransactions($this->invoiceId)) {
            $form = '<p>'
                . dgettext($this->textDomain, 'Your payment is currently pending and will be processed automatically.')
                . '</p>'
                . $form;
        }

        // Add sandbox message.
        if ($this->sandbox) {
            $form = '<strong style="color: red;">SANDBOX MODE</strong><br />' . $form;
        }

        return $form;
    }

    /**
     * Get or create Mollie customer
     *
     * @param Mollie $mollie Mollie API instance.
     * @return Mollie\API\Model\Customer
     */
    private function getOrCreateCustomer(Mollie $mollie)
    {
        // Get customer ID or create customer.
        if (!$customerId = $this->getCustomerId($this->clientDetails['userid'])) {
            // Create customer ID.
            $customer = $mollie->customer()->create(
                $this->clientDetails['fullname'],
                $this->clientDetails['email'],
                array(
                    'whmcs_id' => $this->clientDetails['userid']
                )
            );

            // Store customer ID.
            $this->setCustomerId($this->clientDetails['userid'], $customer->id);

            return $customer;
        }

        // Get customer.
        return $mollie->customer($customerId)->get();
    }

    /**
     * Run link action
     * @return string
     */
    public function run()
    {
        // Initialize.
        if (!$this->initialize()) {
            return $this->statusMessage(
                dgettext(
                    $this->textDomain,
                    'This payment gateway is currently disabled. Please contact the administrator.'
                )
            );
        }

        // Get API key.
        $apiKey = $this->getApiKey();

        try {
            // Mollie API.
            $mollie = new Mollie($apiKey);

            // Get customer.
            $customer = $this->getOrCreateCustomer($mollie);

            // Handle form submission.
            if ($_SERVER['REQUEST_METHOD'] == "POST" && $_POST['action'] == "paynow") {
                // Get nonce and remove it from session.
                $nonce = $_SESSION['paynow_nonce'];
                unset($_SESSION['paynow_nonce']);

                // Check nonce and create payment.
                if (!empty($nonce) && $this->nonce->check($nonce, $this->nonceToken)) {
                    $transaction = $customer->payment()->create(
                        $this->actionParams['amount'],
                        $this->actionParams['description'],
                        $this->actionParams['returnurl'],
                        array(
                            'whmcs_invoice' => $this->invoiceId
                        ),
                        array(
                            'webhookUrl' => $this->getWebhookUrl()
                        )
                    );

                    // Store pending transaction.
                    $this->updateTransactionStatus($this->invoiceId, 'pending', $transaction->id);

                    // Log transaction.
                    $this->logTransaction(
                        "Payment attempted for invoice {$this->invoiceId}. " .
                            "Awaiting payment confirmation from callback for transaction {$transaction->id}.",
                        'Success'
                    );

                    // Redirect to payment page.
                    $transaction->gotoPaymentPage();
                }
            }

            // Show payment form.
            return $this->payNowForm();
        } catch (RequestException $ex) {
            // Get response.
            $resp = $ex->getResponse();

            // Handle customer not found error.
            if ($resp->code == 404) {
                // Remove customer ID from database.
                $this->setCustomerId($this->clientDetails['userid'], '');

                // Refresh the current page.
                header('Refresh: 0');
            }

            return $this->statusMessage(
                dgettext(
                    $this->textDomain,
                    'Error occurred, please select a different payment method or try again later.'
                )
            );
        } catch (\Exception $ex) {
            return $this->statusMessage(
                dgettext(
                    $this->textDomain,
                    'Error occurred, please select a different payment method or try again later.'
                )
            );
        }
    }
}
