<?php

namespace Webkul\Shipping\CTT;

use Illuminate\Support\Facades\DB;

class CTTService
{
    public function createShipment($customerId, $orderId, $destination)
    {
        // Constrói os dados do envio
        $shipmentData = $this->buildRequestData($destination, $customerId, $orderId);

        // Envia a requisição SOAP para criar o envio
        return $this->sendSoapRequest('CreateShipment', $shipmentData);
    }

    public function closeShipment($shipmentId)
    {
        $shipmentData = [
            'AuthenticationID' => env('CTT_AUTH_ID'),
            'UserID' => env('CTT_USER_ID'),
            'ShipmentID' => $shipmentId,
        ];

        return $this->sendSoapRequest('CloseShipment', $shipmentData);
    }

    private function getCustomerAddress($customerId, $orderId)
    {
        // Obtém o cart_id relacionado ao pedido
        $order = DB::table('orders')
            ->where('id', $orderId)
            ->where('customer_id', $customerId)
            ->first();

        if (!$order) {
            throw new \Exception("Pedido não encontrado para o cliente ID: {$customerId}");
        }

        // Obtém o endereço com base no cart_id
        $address = DB::table('addresses')
            ->where('cart_id', $order->cart_id)
            ->where('address_type', 'cart_shipping')
            ->first();

        if (!$address) {
            throw new \Exception("Endereço de envio não encontrado para o cart_id: {$order->cart_id}");
        }

        $receiverPostcode = $this->processPostcode($address->postcode);

        return [
            'Address' => $address->address,
            'City' => $address->city,
            'ContactName' => "{$address->first_name} {$address->last_name}",
            'Country' => $address->country,
            'Door' => $address->door ?? '',
            'Email' => $address->email,
            'MobilePhone' => $address->phone,
            'Name' => "{$address->first_name} {$address->last_name}",
            'PTZipCode3' => $receiverPostcode['PTZipCode3'],
            'PTZipCode4' => $receiverPostcode['PTZipCode4'],
            'PTZipCodeLocation' => $address->city,
            'Phone' => $address->phone,
        ];
    }

    private function getOrderData($orderId)
    {
        $orderItems = DB::table('order_items')
            ->where('order_id', $orderId)
            ->get();

        if ($orderItems->isEmpty()) {
            throw new \Exception("Nenhum item encontrado para a ordem ID: {$orderId}");
        }

        $totalWeight = $orderItems->sum('weight');
        $totalQuantity = $orderItems->sum('qty_ordered');

        return [
            'total_weight' => $totalWeight,
            'total_quantity' => $totalQuantity,
        ];
    }

    private function buildRequestData($destination, $customerId, $orderId)
    {
        $customerData = $this->getCustomerAddress($customerId, $orderId);
        $orderData = $this->getOrderData($orderId);

        return [
            'AuthenticationID' => env('CTT_AUTH_ID'),
            'DeliveryNote' => [
                'ClientId' => $destination === 'PT' ? '100029519' : '200045678',
                'ContractId' => $destination === 'PT' ? '300321269' : '300654987',
                'DistributionChannelId' => '99',
                'ExtData' => '?',
                'ShipmentCTT' => [
                    'ShipmentCTT' => [
                        'HasSenderInformation' => 'true',
                        'ReceiverData' => $customerData,
                        'SenderData' => [
                            'Address' => 'Endereço do Remetente',
                            'City' => 'Cidade do Remetente',
                            'ContactName' => 'Nome do Remetente',
                            'Country' => 'PT',
                            'Email' => 'remetente@email.com',
                            'MobilePhone' => '+351910000000',
                            'Name' => 'Empresa Remetente',
                            'PTZipCode3' => '100',
                            'PTZipCode4' => '1000',
                            'PTZipCodeLocation' => 'Local Remetente',
                            'Phone' => '+351910000000',
                        ],
                        'ShipmentData' => [
                            'ATCode' => '',
                            'ClientReference' => 'DVMEGACASE',
                            'IsDevolution' => 'false',
                            'Observations' => 'Envio de pedido',
                            'Quantity' => (string) $orderData['total_quantity'],
                            'Weight' => (string) $orderData['total_weight'],
                        ],
                    ],
                ],
                'SubProductId' => $destination === 'PT' ? 'EMSF056.01' : 'INTL001',
            ],
            'RequestID' => (string) $this->generateGuid(),
            'UserID' => env('CTT_USER_ID'),
        ];
    }

    private function sendSoapRequest($action, $data)
    {
        $url = env('CTT_SOAP_URL');

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;

        $envelope = $dom->createElementNS('http://schemas.xmlsoap.org/soap/envelope/', 'soapenv:Envelope');
        $envelope->setAttribute('xmlns:tem', 'http://tempuri.org/');
        $envelope->setAttribute('xmlns:ctt', 'http://schemas.datacontract.org/2004/07/CTTExpressoWS');
        $envelope->setAttribute('xmlns:ctt1', 'http://schemas.datacontract.org/2004/07/CTTExpressoWS.Models.ShipmentProvider');
        $envelope->setAttribute('xmlns:ctt2', 'http://schemas.datacontract.org/2004/07/CTTExpressoWS.Models.ShipmentProvider.SEPs');

        $header = $dom->createElement('soapenv:Header');
        $body = $dom->createElement('soapenv:Body');

        $actionElement = $dom->createElement("tem:{$action}");
        $input = $dom->createElement('tem:Input');

        // AuthenticationID
        $input->appendChild($dom->createElement('ctt:AuthenticationID', $data['AuthenticationID']));

        // DeliveryNote
        $deliveryNote = $dom->createElement('ctt:DeliveryNote');
        $deliveryNote->appendChild($dom->createElement('ctt1:ClientId', $data['DeliveryNote']['ClientId']));
        $deliveryNote->appendChild($dom->createElement('ctt1:ContractId', $data['DeliveryNote']['ContractId']));
        $deliveryNote->appendChild($dom->createElement('ctt1:DistributionChannelId', $data['DeliveryNote']['DistributionChannelId']));
        $deliveryNote->appendChild($dom->createElement('ctt1:ExtData', $data['DeliveryNote']['ExtData']));

        // ShipmentCTT (Outer)
        $shipmentCTTOuter = $dom->createElement('ctt1:ShipmentCTT');

        // ShipmentCTT (Inner)
        $shipmentCTTInner = $dom->createElement('ctt1:ShipmentCTT');
        $shipmentCTTInner->appendChild($dom->createElement('ctt1:HasSenderInformation', 'true'));

        // ReceiverData
        $receiverData = $dom->createElement('ctt1:ReceiverData');
        foreach ($data['DeliveryNote']['ShipmentCTT']['ShipmentCTT']['ReceiverData'] as $key => $value) {
            $receiverData->appendChild($dom->createElement("ctt1:{$key}", $value));
        }

        // Adicionar manualmente o campo "Type" se não estiver presente
        if (!isset($data['DeliveryNote']['ShipmentCTT']['ShipmentCTT']['ReceiverData']['Type'])) {
            $receiverData->appendChild($dom->createElement('ctt1:Type', 'Receiver'));
        }

        $shipmentCTTInner->appendChild($receiverData);

        // SenderData
        $senderData = $dom->createElement('ctt1:SenderData');
        foreach ($data['DeliveryNote']['ShipmentCTT']['ShipmentCTT']['SenderData'] as $key => $value) {
            $senderData->appendChild($dom->createElement("ctt1:{$key}", $value));
        }

        // Adicionar manualmente o campo "Type" se não estiver presente
        if (!isset($data['DeliveryNote']['ShipmentCTT']['ShipmentCTT']['SenderData']['Type'])) {
            $senderData->appendChild($dom->createElement('ctt1:Type', 'Sender'));
        }

        $shipmentCTTInner->appendChild($senderData);

        // ShipmentData
        $shipmentData = $dom->createElement('ctt1:ShipmentData');
        foreach ($data['DeliveryNote']['ShipmentCTT']['ShipmentCTT']['ShipmentData'] as $key => $value) {
            $shipmentData->appendChild($dom->createElement("ctt1:{$key}", $value));
        }
        $shipmentCTTInner->appendChild($shipmentData);

        $shipmentCTTOuter->appendChild($shipmentCTTInner);
        $deliveryNote->appendChild($shipmentCTTOuter);

        // SubProductId
        $deliveryNote->appendChild($dom->createElement('ctt1:SubProductId', $data['DeliveryNote']['SubProductId']));

        $input->appendChild($deliveryNote);

        // RequestID
        $input->appendChild($dom->createElement('ctt:RequestID', $data['RequestID']));

        // UserID
        $input->appendChild($dom->createElement('ctt:UserID', $data['UserID']));

        $actionElement->appendChild($input);
        $body->appendChild($actionElement);

        $envelope->appendChild($header);
        $envelope->appendChild($body);
        $dom->appendChild($envelope);

        $xmlPayload = $dom->saveXML();

        $headers = [
            "Content-Type: text/xml; charset=utf-8",
            "SOAPAction: http://tempuri.org/ICTTShipmentProviderWS/{$action}",
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xmlPayload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \Exception("SOAP Request Error: {$error}");
        }

        return $response;
    }

    private function processPostcode($postcode)
    {
        // Divida o código postal em partes
        $parts = explode('-', $postcode);

        return [
            'PTZipCode3' => isset($parts[1]) ? intval($parts[1]) : null,
            'PTZipCode4' => isset($parts[0]) ? intval($parts[0]) : null,
        ];
    }

    private function generateGuid()
    {
        return sprintf(
            '%04X%04X-%04X-%04X-%04X-%04X%04X%04X',
            mt_rand(0, 0xFFFF),
            mt_rand(0, 0xFFFF),
            mt_rand(0, 0xFFFF),
            mt_rand(0, 0x0FFF) | 0x4000,
            mt_rand(0, 0x3FFF) | 0x8000,
            mt_rand(0, 0xFFFF),
            mt_rand(0, 0xFFFF),
            mt_rand(0, 0xFFFF)
        );
    }
}
