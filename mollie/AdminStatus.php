<?php
/**
 * Mollie Payment Gateway
 * @version 1.0.0
 */

namespace Cloudstek\WHMCS\Mollie;

/**
 * Admin status message action
 */
class AdminStatus extends ActionBase
{
    /** @var int $invoiceId Invoice ID */
    private $invoiceId;

    /** @var string $invoiceStatus Invoice status */
    private $invoiceStatus;

    /**
     * Admin status message action constructor
     * @param array $params Admin status message parameters.
     */
    public function __construct(array $params)
    {
        parent::__construct($params);

        // Invoice data.
        $this->invoiceId = $this->actionParams['invoiceid'];
        $this->invoiceStatus = $this->actionParams['status'];
    }

    /**
     * Generate status message
     *
     * @param string      $status  Status message type.
     * @param string      $message Status message content.
     * @param string|null $title   Status message title.
     * @return array
     */
    private function statusMessage($status, $message, $title = null)
    {
        return array(
            'type' => $status,
            'msg' => $message,
            'title' => empty($title) ? $this->gatewayParams['name'] : $title
        );
    }

    /**
     * Run admin status message action
     * @return array|null
     */
    public function run()
    {
        // Initialize.
        if (!$this->initialize()) {
            return $this->statusMessage(
                'error',
                dgettext($this->textDomain, 'Please enter your API key(s) to use this payment gateway.')
            );
        }

        // Check for pending transaction.
        if ($this->invoiceStatus == "Unpaid") {
            // Get customer ID.
            $customerId = $this->getCustomerId($this->actionParams['userid']);

            // Check for customer ID.
            if (!$customerId) {
                return;
            }

            // Check for pending transactions.
            if ($this->hasPendingTransactions($this->invoiceId)) {
                return $this->statusMessage(
                    'info',
                    dgettext(
                        $this->textDomain,
                        'There is a payment pending for this invoice. Status will be automatically updated once a '.
                        'confirmation is received from Mollie.'
                    )
                );
            }

            // Check for failed transactions.
            if ($this->hasFailedTransactions($this->invoiceId)) {
                return $this->statusMessage(
                    'error',
                    dgettext(
                        $this->textDomain,
                        'Automatic payment for this invoice has failed. Please check the gateway logs for details.'
                    )
                );
            }
        }
    }
}
