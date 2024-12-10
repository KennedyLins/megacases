<?php

namespace Webkul\MoloniIntegration\Services;

use GuzzleHttp\Client;
use Webkul\Sales\Models\Invoice;
use Webkul\Sales\Models\InvoiceItem;

class MoloniService
{
    protected $client;
    protected $accessToken;
    protected $refreshToken;
    protected $tokenExpires;

    public function __construct()
    {
        $this->client = new Client(['base_uri' => env('MOLONI_BASE_URI')]);
        $this->accessToken = env('MOLONI_ACCESS_TOKEN');
        $this->refreshToken = env('MOLONI_REFRESH_TOKEN');
        $this->tokenExpires = env('MOLONI_TOKEN_EXPIRES');

        // Verificar se o token ainda é válido
        $this->ensureTokenIsValid();
    }

    /**
     * Verifica se o token atual é válido. Caso contrário, renova-o.
     */
    private function ensureTokenIsValid()
    {
        $this->loadToken();

        if (!$this->accessToken || $this->tokenExpires <= time()) {
            $this->refreshAccessToken();
        }
    }

    /**
     * Renova o access_token utilizando o refresh_token.
     */
    private function refreshAccessToken()
    {
        try {
            $response = $this->client->get('grant/', [
                'query' => [
                    'grant_type' => 'password',
                    'client_id' => env('MOLONI_CLIENT_ID'),
                    'client_secret' => env('MOLONI_CLIENT_SECRET'),
                    'username' => env('MOLONI_USERNAME'),
                    'password' => env('MOLONI_PASSWORD')
                ]
            ]);

            $data = json_decode($response->getBody(), true);

            if (isset($data['error'])) {
                throw new \Exception("Erro na autenticação: " . $data['error_description']);
            }

            // Atualizar credenciais
            $this->accessToken = $data['access_token'];
            $this->tokenExpires = time() + $data['expires_in'];

            // Persistir o token
            $this->storeToken($this->accessToken, $this->tokenExpires);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            throw new \Exception("Erro HTTP: " . $e->getResponse()->getBody()->getContents());
        } catch (\Exception $e) {
            throw new \Exception("Erro: " . $e->getMessage());
        }
    }

    /**
     * Cria um cliente no Moloni.
     */
    public function findOrCreateCustomer(array $customerData)
    {
        $this->ensureTokenIsValid();

        try {
            $response = $this->client->post('customers/getByVat/', [
                'query' => [
                    'access_token' => $this->accessToken,
                ],
                'form_params' => [
                    'company_id' => env('MOLONI_COMPANY_ID'),
                    'vat' => $customerData['vat'],
                ],
            ]);

            $result = json_decode($response->getBody(), true);

            if (!empty($result)) {
                return $result[0];
            }
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $responseBody = json_decode($e->getResponse()->getBody(), true);

            if (isset($responseBody['error']) && $responseBody['error'] === 'customer_not_found') {
                return $this->createCustomer($customerData);
            }

            throw new \Exception("Erro ao buscar cliente: " . $e->getResponse()->getBody()->getContents());
        }

        return $this->createCustomer($customerData);
    }

    private function createCustomer(array $customerData)
    {
        try {
            $response = $this->client->post('customers/insert/', [
                'query' => [
                    'access_token' => $this->accessToken,
                ],
                'form_params' => array_merge($customerData, [
                    'company_id' => env('MOLONI_COMPANY_ID'),
                    'language_id' => $customerData['language_id'] ?? 1,
                    'maturity_date_id' => $customerData['maturity_date_id'] ?? 1,
                    'document_type_id' => $customerData['document_type_id'] ?? 1,
                    'payment_method_id' => $customerData['payment_method_id'] ?? 1,
                    'delivery_method_id' => $customerData['delivery_method_id'] ?? 1,
                ]),
            ]);

            return json_decode($response->getBody(), true);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            throw new \Exception("Erro ao criar cliente: " . $e->getResponse()->getBody()->getContents());
        }
    }

    /**
     * Cria uma fatura no Moloni.
     */
    public function createInvoice(array $data)
    {
        $this->ensureTokenIsValid();

        $response = $this->client->post('invoices/insert', [
            'query' => [
                'access_token' => $this->accessToken,
            ],
            'form_params' => [
                'customer_id' => $data['customer_id'],
                'products' => json_encode($data['items']),
                'date' => $data['date'] ?? date('Y-m-d'),
                'total' => $data['total'],
                // 'status' => $data['status'] ?? 0, // Rascunho como padrão
                'status' => 0, // Rascunho como padrão
            ],
        ]);

        return json_decode($response->getBody(), true);
    }

    /**
     * Salva a fatura e os itens no banco de dados.
     */
    public function saveInvoiceAndItems(array $invoiceData)
    {
        // Salvar fatura
        $invoice = Invoice::create([
            'increment_id' => $invoiceData['number'] ?? null,
            'state' => $invoiceData['status'] == 1 ? 'paid' : 'draft',
            'email_sent' => 1,
            'total_qty' => count($invoiceData['products'] ?? []),
            'sub_total' => $invoiceData['gross_value'] ?? 0,
            'base_sub_total' => $invoiceData['gross_value'] ?? 0,
            'grand_total' => $invoiceData['net_value'] ?? 0,
            'base_grand_total' => $invoiceData['net_value'] ?? 0,
            'tax_amount' => $invoiceData['taxes_value'] ?? 0,
            'base_tax_amount' => $invoiceData['taxes_value'] ?? 0,
            'order_id' => $invoiceData['document_id'] ?? null,
            'transaction_id' => $invoiceData['rsa_hash'] ?? null,
        ]);

        // Salvar itens
        foreach ($invoiceData['products'] as $product) {
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'name' => $product['name'],
                'description' => $product['summary'] ?? '',
                'sku' => $product['reference'] ?? '',
                'qty' => $product['qty'] ?? 0,
                'price' => $product['price'] ?? 0,
                'base_price' => $product['price'] ?? 0,
                'total' => ($product['price'] ?? 0) * ($product['qty'] ?? 1),
                'base_total' => ($product['price'] ?? 0) * ($product['qty'] ?? 1),
                'tax_amount' => $product['taxes'][0]['total_value'] ?? 0,
                'base_tax_amount' => $product['taxes'][0]['total_value'] ?? 0,
            ]);
        }

        return $invoice;
    }

    /**
     * Persistência do Token.
     */
    private function storeToken($accessToken, $expiresAt)
    {
        $data = [
            'access_token' => $accessToken,
            'expires_at' => $expiresAt,
        ];

        file_put_contents(storage_path('moloni_token.json'), json_encode($data));
    }

    /**
     * Carregamento do Token.
     */
    private function loadToken()
    {
        $file = storage_path('moloni_token.json');

        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            $this->accessToken = $data['access_token'];
            $this->tokenExpires = $data['expires_at'];
        }
    }
}
