<?php

namespace Webkul\Shipping\CTT;

use Illuminate\Support\Facades\Http;

class CTTService
{
    protected $apiKey;
    protected $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('shipping.ctt.api_key');
        $this->baseUrl = config('shipping.ctt.base_url');
    }

    public function calculateShippingRate($parameters)
    {
        // Substitua 'endpoint/calculate-rate' pelo endpoint correto da CTT para cálculo de taxas
        $url = "{$this->baseUrl}/endpoint/calculate-rate";

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
        ])->post($url, $parameters);

        return $response->json(); // ou tratar os dados retornados
    }

    public function createShipment($shipmentData)
    {
        // Substitua 'endpoint/create-shipment' pelo endpoint de criação de remessa
        $url = "{$this->baseUrl}/endpoint/create-shipment";

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
        ])->post($url, $shipmentData);

        return $response->json();
    }

    public function trackShipment($trackingId)
    {
        // Substitua 'endpoint/track' pelo endpoint correto para rastreamento
        $url = "{$this->baseUrl}/endpoint/track/{$trackingId}";

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->apiKey}",
        ])->get($url);

        return $response->json();
    }
}
