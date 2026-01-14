<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TransactionProduct;
use App\Models\TransactionProductDetail;
use App\Services\InventoryProductService;

class TransactionDetailsAPIController extends Controller
{
    /**
     * GET /api/transaction-details/transaction/{transactionId}?perPage=5&page=1
     * Devuelve los detalles de la transacción paginados, con relaciones necesarias.
     * Si la transacción está posteada, añade el inventario de cada detalle.
     */
    public function getAllByTransaction(int $transactionId, Request $request)
    {
        try {
            $perPage = (int) $request->query('perPage', 5);
            $perPage = $perPage > 0 ? $perPage : 5;
            $page = (int) $request->query('page', 1);
            $page = $page > 0 ? $page : 1;

            $transaction = TransactionProduct::with(['movementType'])
                ->findOrFail($transactionId);

            $paginator = TransactionProductDetail::with([
                'product.company',
                'warehouseFrom',
                'locationFrom',
                'binFrom',
                'warehouseTo',
                'locationTo',
                'binTo',
                'lotNumbers',
                'serialNumbers',
            ])
                ->where('transaction_id', $transactionId)
                ->orderBy('id')
                ->paginate($perPage, ['*'], 'page', $page);

            // Transformar items para incluir inventory cuando corresponda
            $data = array_map(function ($detail) use ($transaction) {
                // $detail es un modelo; lo convertimos a array y añadimos inventory
                $arr = $detail->toArray();
                if ((int) $transaction->posted === 1) {

                    try {
                        // InventoryProductService acepta $detail como array y $transaction como modelo
                        $inventory = InventoryProductService::getInventoryProductByTransaction($arr, $transaction);
                        $arr['inventory'] = $inventory ? $inventory->toArray() : null;
                    } catch (\Throwable $th) {
                        // Si hay error de inventario, lo devolvemos como mensaje simple
                        $arr['inventory_error'] = $th->getMessage();
                    }
                }

                return $arr;
            }, $paginator->items());

            return response()->json([
                'data' => $data,
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page'    => $paginator->lastPage(),
                    'per_page'     => $paginator->perPage(),
                    'total'        => $paginator->total(),
                ],
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Transaction not found',
                'message' => $e->getMessage(),
            ], 404);
        } catch (\Throwable $th) {
            return response()->json([
                'error' => 'Unexpected error',
                'message' => $th->getMessage(),
            ], 500);
        }
    }
}
