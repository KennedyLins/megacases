<?php

namespace Webkul\Shipping\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Webkul\Shipping\CTT\CTTService;

class CTTController extends Controller
{
    protected $cttService;

    public function __construct(CTTService $cttService)
    {
        $this->cttService = $cttService;
    }

    public function createShipment(Request $request)
    {
        $data = $request->only(['customer_id', 'order_id']);

        try {
            $response = $this->cttService->createShipment($data['customer_id'], $data['order_id'], 'PT');
            return response()->json([
                'success' => true,
                'data' => $response,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function closeShipment(Request $request)
    {
        $shipmentId = $request->input('shipment_id');

        try {
            $response = $this->cttService->closeShipment($shipmentId);
            return response()->json([
                'success' => true,
                'data' => $response,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
