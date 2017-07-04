<?php
/**
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ShopwarePlugins\Connect\Components\Utils;

use Shopware\Connect\Struct\Order as OrderStruct;

/**
 * Class OrderPaymentMapper
 * @package ShopwarePlugins\Connect\Components\Utils
 */
class OrderPaymentMapper
{
    private $mapping;

    /**
     * Returns an array of mappings from sw order payment to connect order states
     *
     * Can be modified and extended by using the Connect_OrderPayment_Mapping filter event
     *
     * @return mixed
     */
    protected function getMapping()
    {
        if (!$this->mapping) {
            $this->mapping = [
                'debit' => OrderStruct::PAYMENT_DEBIT,
                'cash' => OrderStruct::PAYMENT_OTHER,
                'invoice' => OrderStruct::PAYMENT_INVOICE,
                'prepayment' => OrderStruct::PAYMENT_ADVANCE,
                'sepa' => OrderStruct::PAYMENT_DEBIT,
                'paypal' => OrderStruct::PAYMENT_PROVIDER,
            ];

            $this->mapping = Shopware()->Events()->filter('Connect_OrderStatus_Mapping', $this->mapping);
        }

        return $this->mapping;
    }

    /**
     * Helper to map shopware order payment to connect order states
     *
     * @param $swOrderPayment
     * @return string
     */
    public function mapShopwareOrderPaymentToConnect($swOrderPayment)
    {
        $swOrderPayment = (string) $swOrderPayment;

        $mapping = $this->getMapping();

        if (!array_key_exists($swOrderPayment, $mapping)) {
            return OrderStruct::PAYMENT_UNKNOWN;
        }

        return $mapping[$swOrderPayment];
    }
}
