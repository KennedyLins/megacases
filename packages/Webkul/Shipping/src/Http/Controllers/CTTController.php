<?php

namespace Webkul\Shipping\Http\Controllers;

use App\Http\Controllers\Controller;
use Webkul\Shipping\CTT\CTTService;
use Illuminate\Http\Request;

class CTTController extends Controller
{
    protected $cttService;

    public function __construct(CTTService $cttService)
    {
        $this->cttService = $cttService;
    }

    public function calculateShipping(Request $request)
    {
        $parameters = $request->all();
        $rate = $this->cttService->calculateShippingRate($parameters);

        return response()->json($rate);
    }

    public function createShipment(Request $request)
    {
        $shipmentData = $request->all();
        $shipment = $this->cttService->createShipment($shipmentData);

        return response()->json($shipment);
    }

    public function trackShipment($trackingId)
    {
        $trackingInfo = $this->cttService->trackShipment($trackingId);

        return response()->json($trackingInfo);
    }
}
