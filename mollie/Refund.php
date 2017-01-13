<?php
/**
 * Mollie Payment Gateway
 * @version 1.0.2
 */

namespace Cloudstek\WHMCS\Mollie;

use Mollie\API\Mollie;

/**
 * Refund action
 */
class Refund extends ActionBase
{
    /** @var string $transactionId Transaction ID */
    private $transactionId;

    /** @var double $refundAmount */
    private $refundAmount;

    /** @var string $refundCurrency Currency sign */
    private $refundCurrency;

    /**
     * Refund action constructor
     * @param array $params Refund action parameters.
     */
    public function __construct(array $params)
    {
        parent::__construct($params);

        // Transaction ID.
        $this->transactionId = $params['transid'];

        // Refund amount and currency.
        $this->refundAmount = $params['amount'];
        $this->refundCurrency = $params['currency'];
    }

    /**
     * Generate status message
     *
     * @param string     $status  Status message type.
     * @param string     $message Status message content.
     * @param array|null $data    Additional data to include.
     * @return array
     */
    private function statusMessage($status, $message, array $data = null)
    {
        // Build message.
        $msg = array(
            'status' => $status,
            'rawdata' => array(
                'message' => $message
            )
        );

        // Merge with additional data.
        if (!empty($data)) {
            $msg['rawdata'] = array_merge($msg['rawdata'], $data);
        }

        return $msg;
    }

    /**
     * Run refund action
     * @return array
     */
    public function run()
    {
        // Initialize.
        if (!$this->initialize()) {
            return $this->statusMessage(
                'error',
                "Failed to create refund for transaction {$this->transactionId} - API key is missing!"
            );
        }

        // Get API key.
        $apiKey = $this->getApiKey();

        try {
            // Mollie API.
            $mollie = new Mollie($apiKey);

            // Create refund.
            $refund = $mollie->payment($this->transactionId)->refund()->create($this->refundAmount);

            // Return status message.
            return $this->statusMessage(
                'success',
                "Successfully refunded {$this->refundCurrency} {$this->refundAmount} of {$this->transactionId}",
                array(
                    'refund_id' => $refund->id
                )
            );
        } catch (\Exception $ex) {
            return $this->statusMessage(
                'error',
                "Failed to create refund for transaction {$this->transactionId}.",
                array(
                    'exception' => $ex->getMessage()
                )
            );
        }
    }
}
