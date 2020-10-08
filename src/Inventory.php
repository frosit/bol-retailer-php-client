<?php


namespace Picqer\BolRetailer;


use GuzzleHttp\Exception\ClientException;
use Picqer\BolRetailer\Exception\HttpException;
use Picqer\BolRetailer\Exception\OfferNotFoundException;
use Picqer\BolRetailer\Exception\RateLimitException;

class Inventory extends Model\Inventory
{

    /**
     * @var array
     * @see https://api.bol.com/retailer/public/redoc/v4#operation/get-inventory
     */
    private static $query = [
        'page' => 1,
        'quantity' => null,
        'stock' => null,
        'state' => null,
        'query' => null,
    ];

    const API_ENDPOINT = 'inventory';
    const STATE_REGULAR = 'REGULAR';
    const STATE_GRADED = 'GRADED';
    const STOCK_SUFFICIENT = 'SUFFICIENT';
    const STOCK_INSUFFICIENT = 'INSUFFICIENT';

    /**
     * Sets param for query
     *
     * @param $key
     * @param $value
     */
    public static function setDefaultQueryParam($key, $value)
    {
        static::$query[$key] = $value;
    }

    /**
     * Get inventory item(s)
     *
     * @param array $options [
     *  'query' => [...] // is merged with static default
     * ]
     *
     * @return Inventory
     */
    public static function get($options = []): Inventory
    {
        if (isset($options['query'])) {
            $options['query'] = array_merge(static::$query, $options['query']);
        }

        try {
            $response = Client::request('GET', self::API_ENDPOINT, $options);
        } catch (ClientException $e) {
            static::handleException($e);
        }

        $body = json_decode((string)$response->getBody(), true);

        return new Inventory(json_decode((string)$response->getBody(), true));
    }


    /**
     * @param array $inventoryItems
     */
    public function addInventoryItems(array $inventoryItems): void
    {
        $this->data['inventory'] = array_merge($this->data['inventory'], $inventoryItems);
    }

    /**
     * @param array $options
     * @return Inventory
     */
    public static function getAll($options = []): Inventory
    {
        $page = 1;
        $lastQueryResults = null;
        $inventory = new Inventory(['inventory' => []]);

        // set default
        if (!isset($options['query'])) {
            $options['query'] = static::$query;
        } else {
            $options['query'] = array_merge(static::$query, $options['query']);
        }

        while ($lastQueryResults > 0 || $lastQueryResults === null) {

            $options['query']['page'] = $page;
            $lastQueryResults = 0;

            try {
                $response = Client::request('GET', self::API_ENDPOINT, $options);
                if ($response->getStatusCode() === 429) { // sleep if rate limit
                    sleep(60);
                    continue;
                }

                $data = json_decode((string)$response->getBody(), true); // decode
                if (!isset($data['inventory'])) { // if no inventory, stop
                    break;
                }

                $inventory->addInventoryItems($data['inventory']);
                $lastQueryResults = count($data['inventory']);
                $page++;
                continue;

            } catch (ClientException $e) {
                static::handleException($e);
                break;
            }

            if ($lastQueryResults === null) { // safety stop
                break;
            }
        }

        return $inventory;
    }


    private static function handleException(ClientException $e): void
    {
        $response = $e->getResponse();

        if ($response && $response->getStatusCode() === 404) {
            throw new OfferNotFoundException(
                json_decode((string)$response->getBody(), true),
                404,
                $e
            );
        } elseif ($response && $response->getStatusCode() === 429) {
            throw new RateLimitException(
                json_decode((string)$response->getBody(), true),
                429,
                $e
            );
        } elseif ($response) {
            throw new HttpException(
                json_decode((string)$response->getBody(), true),
                $response->getStatusCode(),
                $e
            );
        }

        throw $e;
    }
}
