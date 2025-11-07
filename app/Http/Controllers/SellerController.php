<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Category;
use App\Models\Order;
use Carbon\Carbon;

class SellerController extends Controller
{
    public function dashboard(Request $request)
    {
        // Aceptar tanto el usuario puesto en attributes por middleware como el auth() estándar
        $user = $request->attributes->get('auth_user') ?? $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'unauthenticated'], 401);
        }

        // Cargar pedidos del vendedor con relaciones necesarias
        $orders = Order::with(['client', 'items.product'])
            ->where('seller_id', $user->id)
            ->get();

        // Contar pendientes (mismos estados que en Java)
        $pending = $orders->filter(function ($o) {
            return in_array($o->status, ['Confirmado', 'En preparación'], true);
        })->count();

        // Ordenar por created_at desc (usar strtotime si created_at no es Carbon)
        $sorted = $orders->sortByDesc(function ($o) {
            return $o->created_at ? strtotime($o->created_at) : 0;
        })->values();

        // Recent: 10 más recientes
        $recentOrders = $sorted->slice(0, 10)->values();

        // Rango "hoy" según la zona horaria del servidor / app
        $appTz = config('app.timezone') ?? date_default_timezone_get();
        $now = Carbon::now($appTz);
        $startOfDay = $now->copy()->startOfDay();
        $endOfDay = $startOfDay->copy()->addDay(); // exclusivo

        // Filtrar pedidos de hoy
        $todayOrdersCollection = $sorted->filter(function ($o) use ($startOfDay, $endOfDay, $appTz) {
            if (!$o->created_at)
                return false;

            // created_at puede ser Carbon (Eloquent) o string; siempre obtener Carbon en tz de la app
            try {
                $created = $o->created_at instanceof Carbon
                    ? $o->created_at->copy()
                    : Carbon::parse($o->created_at, $appTz);
            } catch (\Exception $e) {
                return false;
            }

            // normalizar timezone a la del startOfDay
            $created->setTimezone($startOfDay->getTimezone());

            return $created->greaterThanOrEqualTo($startOfDay) && $created->lt($endOfDay);
        })->values();

        // Sumar totales (usar float; para precisión absoluta usa string/bcmath)
        // Per requested simplified logic: return the total count of orders for this seller
        // and the total sum of all those orders' totals. (Frontend expects these two values.)
        $totalOrdersCount = $orders->count();
        $totalOrdersSum = (float) $orders->sum(function ($o) {
            return $o->total_amount ? (float) $o->total_amount : 0.0;
        });

        // Mapeadores para respuesta (completa y corta)
        $mapOrderFull = function ($o) use ($appTz) {
            // createdAt en formato ISO Z (UTC) para coincidir con Java output "2025-11-06T09:12:34Z"
            $createdAtUtc = null;
            if ($o->created_at) {
                $c = $o->created_at instanceof Carbon ? $o->created_at->copy() : Carbon::parse($o->created_at, $appTz);
                $createdAtUtc = $c->setTimezone('UTC')->format('Y-m-d\TH:i:s\Z');
            }

            $items = [];
            if ($o->items) {
                foreach ($o->items as $it) {
                    $unitPrice = isset($it->unit_price) ? (float) $it->unit_price : 0.0;
                    $qty = isset($it->quantity) ? (int) $it->quantity : 0;
                    $items[] = [
                        'productId' => $it->product_id,
                        'productName' => isset($it->product) ? $it->product->name : null,
                        'quantity' => $qty,
                        'unitPrice' => $unitPrice,
                        'total' => $unitPrice * $qty
                    ];
                }
            }

            return [
                'id' => $o->id,
                'status' => $o->status,
                'totalAmount' => (float) ($o->total_amount),
                'paymentMethod' => $o->payment_method ?? null,
                'deliveryAddress' => $o->delivery_address ?? null,
                'createdAt' => $createdAtUtc,
                'client' => $o->client ? [
                    'id' => $o->client->id,
                    'name' => $o->client->name,
                    'email' => $o->client->email,
                    'address' => $o->client->address ?? null
                ] : null,
                'items' => $items
            ];
        };

        $mapOrderShort = function ($o) use ($appTz) {
            $createdAtUtc = null;
            if ($o->created_at) {
                $c = $o->created_at instanceof Carbon ? $o->created_at->copy() : Carbon::parse($o->created_at, $appTz);
                $createdAtUtc = $c->setTimezone('UTC')->format('Y-m-d\TH:i:s\Z');
            }
            return [
                'id' => $o->id,
                'status' => $o->status,
                'totalAmount' => (float) ($o->total_amount ?? 0.0),
                'createdAt' => $createdAtUtc,
                'client' => $o->client ? [
                    'id' => $o->client->id,
                    'name' => $o->client->name
                ] : null
            ];
        };

        $recent = $recentOrders->map($mapOrderFull)->toArray();
        $todayShort = $todayOrdersCollection->map($mapOrderShort)->toArray();

        // Contador de productos disponibles (global). Cambia la query si quieres por vendedor.
        $productsCount = Product::where('is_available', 1)->count();

        $data = [
            'pending' => $pending,
            'recent_orders' => $recent,
            // simplified: count of orders for this seller
            'today_orders' => (int) $totalOrdersCount,
            // simplified: total sum of all orders for this seller
            'today_sales_total' => round($totalOrdersSum, 2),
            'products_count' => $productsCount
        ];

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function productsStock(Request $request)
    {
        $products = Product::all();
        return response()->json(['success' => true, 'products' => $products]);
    }

    public function products(Request $request)
    {
        return $this->productsStock($request);
    }

    public function categories()
    {
        return response()->json(['success' => true, 'categories' => Category::all()]);
    }

    public function toggleAvailability(Request $request, $id)
    {
        $p = Product::find($id);
        if (!$p)
            return response()->json(['success' => false], 404);
        $p->is_available = !$p->is_available;
        $p->save();
        return response()->json(['success' => true]);
    }

    public function status(Request $request, $id)
    {
        $p = Product::find($id);
        if (!$p)
            return response()->json(['success' => false, 'isAvailable' => null]);
        return response()->json(['success' => true, 'id' => $p->id, 'isAvailable' => (bool) $p->is_available]);
    }

    public function updateStock(Request $request, $id)
    {
        $p = Product::find($id);
        if (!$p)
            return response()->json(['success' => false], 404);
        $stock = intval($request->input('stock', 0));
        $p->stock = $stock;
        $p->save();
        return response()->json(['success' => true]);
    }

    public function bulkStocks(Request $request)
    {
        $items = $request->input('items', []);
        foreach ($items as $it) {
            $p = Product::find($it['id']);
            if ($p) {
                $p->stock = intval($it['stock']);
                $p->save();
            }
        }
        return response()->json(['success' => true]);
    }

    public function orders(Request $request)
    {
        $user = $request->attributes->get('auth_user');
        $orders = Order::where('seller_id', $user->id)->get();
        return response()->json(['success' => true, 'orders' => $orders]);
    }

    public function orderDetail(Request $request, $id)
    {
        $order = Order::find($id);
        if (!$order)
            return response()->json(['success' => false], 404);
        return response()->json(['success' => true, 'order' => $order]);
    }

    public function changeOrderStatus(Request $request, $id)
    {
        $new = $request->input('newStatus');
        $order = Order::find($id);
        if (!$order)
            return response()->json(['success' => false], 404);
        $order->status = $new;
        $order->save();
        return response()->json(['success' => true]);
    }

    public function assignSeller(Request $request, $id)
    {
        $sellerId = $request->input('sellerId');
        $order = Order::find($id);
        if (!$order)
            return response()->json(['success' => false], 404);
        $order->seller_id = $sellerId;
        $order->save();
        return response()->json(['success' => true]);
    }
}
