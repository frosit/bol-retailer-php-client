<?php

namespace Picqer\BolRetailer\Model;

/**
 * @property string $offerId
 * @property string $ean
 * @property string $referenceCode
 * @property bool $onHoldByRetailer
 * @property string $unknownProductTitle
 * @property Pricing[] $pricing
 * @property Stock $stock
 * @property array $fulfilment
 * @property array $store
 * @property array $condition
 * @property array $notPublishableReasons
 */
class Inventory extends AbstractModel
{
    protected function getInventory()
    {
        return $this->data['inventory'] ?? [];
    }

}
