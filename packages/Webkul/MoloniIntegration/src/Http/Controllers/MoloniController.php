<?php

namespace Webkul\MoloniIntegration\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Webkul\MoloniIntegration\Services\MoloniService;
use Webkul\Checkout\Models\Cart;
use Webkul\Sales\Models\Invoice;

class MoloniController extends Controller
{
    protected $moloniService;

    public function __construct(MoloniService $moloniService)
    {
        $this->moloniService = $moloniService;
    }

    /**
     * Endpoint para processar carrinho e criar fatura no Moloni.
     */
    public function processCart(Request $request)
    {
        // Validar entrada
        $validated = $request->validate([
            'cart_id' => 'required|integer|exists:cart,id',
        ]);

        try {
            // Buscar carrinho
            $cart = Cart::with(['customer', 'items.product', 'billing_address'])->findOrFail($validated['cart_id']);
            $customer = $cart->customer;
            $billingAddress = $cart->billing_address;

            // Preparar dados do cliente
            $customerData = [
                'vat' => $billingAddress->vat_id ?? '999999990', // Fallback para NIF genérico
                'number' => $customer->id,
                'name' => "{$customer->first_name} {$customer->last_name}",
                'email' => $customer->email,
                'address' => $billingAddress->address ?? 'Endereço padrão',
                'zip_code' => $billingAddress->postcode ?? '0000-000',
                'city' => $billingAddress->city ?? 'Cidade padrão',
                'country_id' => 1, // Portugal
            ];

            // Criar cliente no Moloni
            $moloniCustomer = $this->moloniService->findOrCreateCustomer($customerData);

            // Preparar produtos e atualizar os IDs dos produtos no Moloni
            $productsData = $cart->items->map(function ($item) {
                return [
                    'product_id' => $item->product->moloni_product_id ?? null,
                    'name' => $item->product->name ?? $item->name,
                    'summary' => $item->product->description ?? 'Sem descrição',
                    'qty' => $item->quantity,
                    'price' => $item->price,
                    'discount' => 0,
                    'exemption_reason' => "M01",
                    'order' => $item->id,
                ];
            })->map(function ($product) {
                // Se o product_id estiver vazio ou inválido, cria o produto no Moloni
                if (!$product['product_id']) {
                    $createdProduct = $this->moloniService->findOrCreateProduct($product);
                    $product['product_id'] = $createdProduct['product_id'];
                }
                return $product;
            })->toArray();

            // Criar fatura no Moloni
            $invoiceData = [
                'company_id' => env('MOLONI_COMPANY_ID'),
                'customer_id' => $moloniCustomer['customer_id'],
                'date' => date('Y-m-d'),
                'expiration_date' => now()->addDays(30)->format('Y-m-d'),
                'document_set_id' => 774008,
                'products' => $productsData,
                'status' => 0,
            ];

            $invoiceResponse = $this->moloniService->createInvoice($invoiceData);

            // Salvar a fatura no banco de dados
            $invoice = new Invoice();
            $invoice->increment_id = 'INV-' . time();
            $invoice->order_id = $cart->id;
            $invoice->transaction_id = $invoiceResponse['document_id'];
            $invoice->state = Invoice::STATUS_PAID;
            $invoice->total_qty = $cart->items->sum('qty');
            $invoice->grand_total = $cart->grand_total;
            $invoice->sub_total = $cart->sub_total;
            $invoice->created_at = now();
            $invoice->updated_at = now();
            $invoice->save();

            return response()->json([
                'success' => true,
                'document_id' => $invoiceResponse['document_id'],
                'invoice_id' => $invoice->id,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function getInvoicePDF(Request $request)
    {
        $validated = $request->validate([
            'transaction_id' => 'required|string|exists:invoices,transaction_id',
        ]);

        try {
            // Buscar a fatura no banco usando o transaction_id
            $invoice = \Webkul\Sales\Models\Invoice::where('transaction_id', $validated['transaction_id'])->firstOrFail();

            // Chamar o serviço para buscar o PDF
            $pdfData = $this->moloniService->getInvoicePDF($invoice->transaction_id);

            return response()->json([
                'success' => true,
                'base64_pdf' => $pdfData['base64'],
                'pdf_url' => $pdfData['url'],
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
