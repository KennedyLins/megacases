<?php

namespace Webkul\MoloniIntegration\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class MoloniService
{
    protected $client;
    protected $accessToken;
    protected $refreshToken;
    protected $tokenExpires;

    public function __construct()
    {
        Log::info('MoloniService: Inicializando serviço.');

        $this->client = new Client(['base_uri' => env('MOLONI_BASE_URI')]);
        $this->accessToken = env('MOLONI_ACCESS_TOKEN');
        $this->refreshToken = env('MOLONI_REFRESH_TOKEN');
        $this->tokenExpires = env('MOLONI_TOKEN_EXPIRES');

        $this->ensureTokenIsValid();
    }

    private function ensureTokenIsValid()
    {
        Log::info('MoloniService: Verificando validade do token.');
        $this->loadToken();

        if (!$this->accessToken || $this->tokenExpires <= time()) {
            Log::warning('MoloniService: Token inválido ou expirado. Renovando token.');
            $this->refreshAccessToken();
        } else {
            Log::info('MoloniService: Token válido.');
        }
    }

    private function refreshAccessToken()
    {
        Log::info('MoloniService: Renovando o token de acesso.');

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
                Log::error('MoloniService: Erro ao renovar token.', $data);
                throw new \Exception("Erro na autenticação: " . $data['error_description']);
            }

            $this->accessToken = $data['access_token'];
            $this->tokenExpires = time() + $data['expires_in'];

            $this->storeToken($this->accessToken, $this->tokenExpires);

            Log::info('MoloniService: Token renovado com sucesso.');
        } catch (\Exception $e) {
            Log::error('MoloniService: Erro ao renovar o token.', [
                'exception' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    private function getDefaultConfig()
    {
        return [
            'language_id' => 1,
            'maturity_date_id' => 1,
            'document_type_id' => 1,
            'payment_method_id' => 1,
            'delivery_method_id' => 1,
            'category_id' => 8643981,
            'unit_id' => 3051938,
            'country_id' => 1,
        ];
    }

    public function findOrCreateCustomer(array $customerData)
    {
        Log::info('MoloniService: Iniciando busca ou criação de cliente.', $customerData);

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
                Log::info('MoloniService: Cliente encontrado.', $result);
                return $result[0];
            }
        } catch (\Exception $e) {
            Log::warning('MoloniService: Cliente não encontrado. Tentando criar um novo.', [
                'exception' => $e->getMessage()
            ]);
        }

        $newCustomer = $this->createCustomer($customerData);
        Log::info('MoloniService: Cliente criado com sucesso.', $newCustomer);

        return $newCustomer;
    }

    private function createCustomer(array $customerData)
    {
        $defaults = $this->getDefaultConfig();

        try {
            $response = $this->client->post('customers/insert/', [
                'query' => [
                    'access_token' => $this->accessToken,
                ],
                'form_params' => array_merge($defaults, $customerData, [
                    'company_id' => env('MOLONI_COMPANY_ID'),
                ]),
            ]);

            $newCustomer = json_decode($response->getBody(), true);
            Log::info('MoloniService: Cliente criado com sucesso.', $newCustomer);

            return $newCustomer;
        } catch (\Exception $e) {
            Log::error('MoloniService: Erro ao criar cliente.', [
                'exception' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function createInvoice(array $data)
    {
        Log::info('MoloniService: Iniciando criação de fatura.', $data);

        try {
            $companyId = env('MOLONI_COMPANY_ID');

            // Preparar os produtos
            $products = [];
            foreach ($data['products'] as $index => $product) {
                if (empty($product['product_id'])) {
                    Log::warning("MoloniService: Produto sem product_id encontrado. Criando produto.", $product);

                    // Buscar ou criar o produto e garantir que o ID está correto
                    $createdProduct = $this->findOrCreateProduct($product);
                    $product['product_id'] = $createdProduct['product_id'] ?? null;

                    if (!$product['product_id']) {
                        throw new \Exception('Falha ao recuperar o product_id do Moloni.');
                    }
                }

                // Montar o formato products[index][key] esperado pelo Moloni
                $products["products[$index][product_id]"] = $product['product_id'];
                $products["products[$index][name]"] = $product['name'];
                $products["products[$index][summary]"] = $product['summary'];
                $products["products[$index][qty]"] = $product['qty'];
                $products["products[$index][price]"] = $product['price'];
                $products["products[$index][discount]"] = $product['discount'];
                $products["products[$index][exemption_reason]"] = $product['exemption_reason'];
                $products["products[$index][order]"] = $product['order'];
            }

            // Payload final
            $formParams = array_merge([
                'company_id' => $companyId,
                'date' => $data['date'],
                'expiration_date' => $data['expiration_date'],
                'document_set_id' => $data['document_set_id'],
                'customer_id' => $data['customer_id'],
                'status' => $data['status'],
            ], $products);

            Log::info('MoloniService: Payload final para criação de fatura.', $formParams);

            // Requisição ao Moloni
            $response = $this->client->post('invoices/insert/', [
                'query' => ['access_token' => $this->accessToken],
                'form_params' => $formParams,
            ]);

            $invoice = json_decode($response->getBody(), true);
            Log::info('MoloniService: Fatura criada com sucesso.', $invoice);

            return $invoice;
        } catch (\Exception $e) {
            Log::error('MoloniService: Erro ao criar fatura.', ['exception' => $e->getMessage()]);
            throw $e;
        }
    }

    private function storeToken($accessToken, $expiresAt)
    {
        Log::info('MoloniService: Armazenando token.');

        $data = [
            'access_token' => $accessToken,
            'expires_at' => $expiresAt,
        ];

        file_put_contents(storage_path('moloni_token.json'), json_encode($data));
        Log::info('MoloniService: Token armazenado com sucesso.');
    }

    private function loadToken()
    {
        Log::info('MoloniService: Carregando token armazenado.');

        $file = storage_path('moloni_token.json');

        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            $this->accessToken = $data['access_token'];
            $this->tokenExpires = $data['expires_at'];

            Log::info('MoloniService: Token carregado com sucesso.', $data);
        } else {
            Log::warning('MoloniService: Nenhum token armazenado encontrado.');
        }
    }

    public function findOrCreateProduct(array $productData)
    {
        Log::info('MoloniService: Iniciando busca ou criação de produto.', $productData);

        try {
            // Buscar produto pelo nome
            $response = $this->client->post('products/getByName/', [
                'query' => [
                    'access_token' => $this->accessToken,
                ],
                'form_params' => [
                    'company_id' => env('MOLONI_COMPANY_ID'),
                    'name' => $productData['name'],
                ],
            ]);

            $result = json_decode($response->getBody(), true);

            // Validar se o produto retornado é o correto
            if (!empty($result) && $result[0]['name'] === $productData['name']) {
                Log::info('MoloniService: Produto encontrado no Moloni.', $result[0]);
                return $result[0];
            } else {
                Log::warning('MoloniService: Produto não encontrado ou nome inconsistente. Criando produto.', $productData);
            }
        } catch (\Exception $e) {
            Log::warning('MoloniService: Erro ao buscar produto. Criando produto.', ['exception' => $e->getMessage()]);
        }

        // Criar o produto se não foi encontrado
        $newProduct = $this->createProduct($productData);
        Log::info('MoloniService: Produto criado com sucesso.', $newProduct);

        return $newProduct;
    }


    private function createProduct(array $productData)
    {
        Log::info('MoloniService: Criando produto.', $productData);

        try {
            $response = $this->client->post('products/insert/', [
                'query' => [
                    'access_token' => $this->accessToken,
                ],
                'form_params' => array_merge($productData, [
                    'company_id' => env('MOLONI_COMPANY_ID'),
                    'type' => 1, // Produto padrão
                    'unit_id' => $productData['unit_id'] ?? 1,
                    'has_stock' => $productData['has_stock'] ?? 1,
                    'stock' => $productData['stock'] ?? 0,
                    'price' => $productData['price'] ?? 0.0,
                    'category_id' => $productData['category_id'] ?? 8654779,
                    'exemption_reason' => $productData['exemption_reason'] ?? '0',
                ]),
            ]);

            $newProduct = json_decode($response->getBody(), true);
            Log::info('MoloniService: Produto criado com sucesso.', $newProduct);

            return $newProduct;
        } catch (\Exception $e) {
            Log::error('MoloniService: Erro ao criar produto.', [
                'exception' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function getInvoicePDF($documentId)
    {
        Log::info("MoloniService: Buscando PDF da fatura com ID {$documentId}");

        try {
            // Fazer a requisição para gerar o PDF
            $response = $this->client->post('invoices/getPDFLink/', [
                'query' => ['access_token' => $this->accessToken],
                'form_params' => [
                    'company_id' => env('MOLONI_COMPANY_ID'),
                    'document_id' => $documentId,
                ],
            ]);

            $result = json_decode($response->getBody(), true);

            if (!empty($result['url'])) {
                Log::info("MoloniService: Link do PDF obtido com sucesso.", ['url' => $result['url']]);

                // Buscar o conteúdo do PDF e retornar em Base64
                $pdfContent = file_get_contents($result['url']);
                $base64 = base64_encode($pdfContent);

                return [
                    'base64' => $base64,
                    'url' => $result['url'],
                ];
            }

            throw new \Exception('MoloniService: Falha ao obter o link do PDF.');
        } catch (\Exception $e) {
            Log::error("MoloniService: Erro ao buscar o PDF da fatura.", ['exception' => $e->getMessage()]);
            throw $e;
        }
    }
}
