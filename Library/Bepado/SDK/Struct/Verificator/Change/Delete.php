<?php
/**
 * This file is part of the Bepado SDK Component.
 *
 * The SDK is licensed under MIT license. (c) Shopware AG and Qafoo GmbH
 */

namespace Bepado\SDK\Struct\Verificator\Change;

use Bepado\SDK\Struct\Verificator\Change;
use Bepado\SDK\Struct\VerificatorDispatcher;
use Bepado\SDK\Struct;

/**
 * Visitor verifying integrity of struct classes
 *
 * The SDK is licensed under MIT license. (c) Shopware AG and Qafoo GmbH
 */
class Delete extends Change
{
    /**
     * Method to verify a structs integrity
     *
     * Throws a RuntimeException if the struct does not verify.
     *
     * @param VerificatorDispatcher $dispatcher
     * @param Struct $struct
     * @return void
     */
    protected function verifyDefault(VerificatorDispatcher $dispatcher, Struct $struct)
    {
        parent::verifyDefault($dispatcher, $struct);

        if ($struct->shopId === null) {
            throw new \RuntimeException('Property $shopId must be set.');
        }
    }
}
