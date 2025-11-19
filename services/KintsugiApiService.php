<?php

class KintsugiApiService
{
    protected $apiKey;
    protected $orgId;

    public function __construct($apiKey, $orgId)
    {
        $this->apiKey = $apiKey;
        $this->orgId = $orgId;
    }

    public function estimateTax($cart, $shippingAddress)
    {
        $url = 'https://api.trykintsugi.com/v1/tax/estimate';
        $payload = [
            'amount' => $cart['subtotal'],
            'shipping_address' => [
                'street_1' => $shippingAddress['address1'],
                'city' => $shippingAddress['city'],
                'state' => $shippingAddress['state'],
                'zip' => $shippingAddress['zip'],
            ],
            'line_items' => array_map(function ($item) {
                return [
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                ];
            }, $cart['items']),
        ];
        $opts = [
            'http' => [
                'method' => 'POST',
                'header' => [
                    'Content-Type: application/json',
                    'x-api-key: ' . $this->apiKey,
                    'x-organization-id: ' . $this->orgId,
                ],
                'content' => json_encode($payload)
            ]
        ];
        $context = stream_context_create($opts);
        $result = file_get_contents($url, false, $context);
        if ($result === false) {
            throw new Exception('Kintsugi API call failed.');
        }
        return json_decode($result, true);
    }
}