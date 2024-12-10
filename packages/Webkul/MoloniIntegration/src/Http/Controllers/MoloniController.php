<?php

namespace Webkul\MoloniIntegration\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Webkul\MoloniIntegration\Services\MoloniService;

class MoloniController extends Controller
{
    protected $moloniService;

    public function __construct(MoloniService $moloniService)
    {
        $this->moloniService = $moloniService;
    }

    /**
     * Cria uma nova fatura no Moloni com base em uma compra do e-commerce.
     */
    public function createInvoice(Request $request)
    {
        // Validar os dados de entrada
        $validated = $request->validate([
            'customer' => 'required|array',
            'items' => 'required|array',
            'total' => 'required|numeric',
        ]);

        // Garantir que o cliente existe no Moloni
        $customer = $this->moloniService->findOrCreateCustomer($validated['customer']);

        // Garantir que os produtos existem no Moloni
        foreach ($validated['items'] as &$item) {
            $product = $this->moloniService->findOrCreateProduct($item);
            $item['product_id'] = $product['product_id'];
        }

        // Criar a fatura no Moloni
        $invoiceData = [
            'customer_id' => $customer['customer_id'],
            'items' => $validated['items'],
            'total' => $validated['total'],
            'date' => date('Y-m-d'),
        ];
        $response = $this->moloniService->createInvoice($invoiceData);

        return response()->json($response);
    }

    public function processCart(Request $request)
    {
        // Validar e buscar o carrinho
        $validated = $request->validate([
            'cart_id' => 'required|integer|exists:cart,id',
        ]);

        $cart = Cart::with('customer', 'items.product')->findOrFail($validated['cart_id']);

        // Buscar o cliente
        $customer = $cart->customer;

        // Buscar os itens do carrinho
        $items = $cart->items;

        // Preparar os dados do cliente para o Moloni
        $customerData = [
            'vat' => $customer->vat ?? '999999990', // Exemplo de NIF, ajuste conforme necessidade
            'number' => $customer->id,
            'name' => $customer->name,
            'email' => $customer->email,
            'address' => $customer->address ?? 'Endereço padrão',
            'zip_code' => $customer->zip_code ?? '0000-000',
            'city' => $customer->city ?? 'Cidade padrão',
            'country_id' => 1, // ID do país (1 para Portugal)
        ];

        // Preparar os dados dos produtos para o Moloni
        $productsData = $items->map(function ($item) {
            return [
                'category_id' => $item->product->category_id,
                'type' => 1, // Produto
                'name' => $item->product->name,
                'reference' => $item->product->sku ?? $item->product->id,
                'price' => $item->price,
                'unit_id' => $item->product->unit_id ?? 1,
                'has_stock' => 1,
                'stock' => $item->quantity,
                'summary' => $item->product->description ?? 'Sem descrição',
                'exemption_reason' => '0', // Sem isenção
            ];
        })->toArray();

        // Chamar o serviço para criar cliente e produtos no Moloni
        $moloniService = new MoloniService();

        $moloniCustomer = $moloniService->findOrCreateCustomer($customerData);

        foreach ($productsData as $productData) {
            $moloniService->findOrCreateProduct($productData);
        }

        // Criar a fatura no Moloni
        $invoiceData = [
            'customer_id' => $moloniCustomer['customer_id'],
            'items' => $productsData,
            'total' => $cart->total,
            'date' => date('Y-m-d'),
        ];

        $invoice = $moloniService->createInvoice($invoiceData);

        return response()->json([
            'success' => true,
            'invoice' => $invoice,
        ]);
    }
}
