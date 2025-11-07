<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;

class ClientController extends Controller
{
    // GET /api/client/products
    public function products(Request $request)
    {
        // Support /client/products and /client/products/full
        $isFull = str_ends_with($request->path(), 'client/products/full') || $request->boolean('full');

        $query = Product::query()->where('is_available', 1);
        if ($request->filled('category')) {
            $query->where('category_id', intval($request->query('category')));
        }
        if ($request->filled('search')) {
            $s = $request->query('search');
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")->orWhere('description', 'like', "%{$s}%");
            });
        }

        $page = max(1, intval($request->query('page', 1)));
        $perPage = 20;
        $paginator = $query->orderBy('created_at', 'desc')->paginate($perPage, ['*'], 'page', $page);

        // Map product entities to the requested shape
        $products = $paginator->getCollection()->map(function ($product) {
            return [
                'id' => $product->id,
                'category' => [
                    'id' => $product->category_id,
                    'name' => $product->category?->name ?? null
                ],
                'name' => $product->name,
                'description' => $product->description,
                'price' => $product->price,
                'stock' => $product->stock,
                'imageUrl' => $product->image_url ?? null,
                'isAvailable' => (bool) $product->is_available,
                'createdAt' => $product->created_at ?? null,
                'updatedAt' => $product->updated_at ?? null
            ];
        })->all();

        $response = ['success' => true, 'products' => $products];
        // include pagination meta when full list requested or when page param present
        $response['pagination'] = [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total()
        ];

        return response()->json($response);
    }

    // POST /api/client/orders
    public function placeOrder(Request $request)
    {
        // Require authentication
        $user = $request->attributes->get('auth_user');
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'unauthenticated'], 401);
        }

        $delivery = $request->input('delivery_address');
        $payment = $request->input('payment_method');
        $cart = $request->input('cart', []);

        // Basic validation
        if (!$delivery || !$payment || !is_array($cart) || empty($cart)) {
            return response()->json(['success' => false, 'message' => 'invalid_input'], 400);
        }

        // Allowed payment methods
        $allowed = ['Tarjeta', 'Efectivo'];
        if (!in_array($payment, $allowed)) {
            return response()->json(['success' => false, 'message' => 'invalid_payment_method'], 400);
        }

        $total = 0;
        $itemsToCreate = [];

        foreach ($cart as $it) {
            if (!isset($it['id']) || !isset($it['quantity'])) {
                return response()->json(['success' => false, 'message' => 'invalid_cart_item'], 400);
            }
            $productId = intval($it['id']);
            $qty = intval($it['quantity']);
            if ($qty <= 0) {
                return response()->json(['success' => false, 'message' => 'invalid_quantity'], 400);
            }
            $p = Product::find($productId);
            if (!$p) {
                return response()->json(['success' => false, 'message' => 'product_not_found'], 400);
            }
            $total += $p->price * $qty;
            $itemsToCreate[] = ['product' => $p, 'quantity' => $qty, 'unit_price' => $p->price];
        }

        $order = Order::create([
            'client_id' => $user->id,
            'delivery_address' => $delivery,
            'total_amount' => $total,
            'status' => 'Pendiente',
            'payment_method' => $payment,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        foreach ($itemsToCreate as $it) {
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $it['product']->id,
                'quantity' => $it['quantity'],
                'unit_price' => $it['unit_price']
            ]);
        }

        return response()->json(['success' => true, 'order_id' => $order->id]);
    }

    // GET /api/client/categories
    public function categories()
    {
        return response()->json(['success' => true, 'categories' => Category::all()]);
    }

    // GET /api/client/categories/{id}
    public function categoryDetail($id)
    {
        $cat = Category::find($id);
        if (!$cat)
            return response()->json(['success' => false, 'category' => null], 404);
        return response()->json(['success' => true, 'category' => $cat->load('products')]);
    }

    // POST /api/client/cart/details
    public function cartDetails(Request $request)
    {
        $ids = $request->input('ids', []);
        $products = Product::whereIn('id', $ids)->get();
        return response()->json(['success' => true, 'products' => $products]);
    }

    // GET /api/client/orders
    public function orders(Request $request)
    {
        $user = $request->attributes->get('auth_user');
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'unauthenticated'], 401);
        }

        $page = max(1, intval($request->query('page', 1)));
        $perPage = max(1, intval($request->query('per_page', 20)));

        $paginator = Order::where('client_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        $orders = $paginator->getCollection()->map(function ($o) {
            return [
                'id' => $o->id,
                'status' => $o->status,
                'totalAmount' => $o->total_amount,
                'createdAt' => $o->created_at
            ];
        })->all();

        $response = [
            'success' => true,
            'orders' => $orders,
            'pagination' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total_pages' => $paginator->lastPage(),
                'total' => $paginator->total()
            ]
        ];

        return response()->json($response);
    }

    // GET /api/client/orders/{id}
    public function orderDetail(Request $request, $id)
    {
        // Require authentication
        $user = $request->attributes->get('auth_user');
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'unauthenticated'], 401);
        }

        // Load order with items and related products
        $order = Order::with('items.product')->find($id);
        // If order not found, return success:true, order:null (matches Java API behavior)
        if (!$order) {
            return response()->json(['success' => true, 'order' => null]);
        }

        // Only the owner can view the order
        if ($order->client_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'forbidden'], 403);
        }

        // Build OrderDetailDto
        $items = [];
        foreach ($order->items as $it) {
            $prod = $it->product;
            $items[] = [
                'productId' => $it->product_id,
                'productName' => $prod?->name ?? null,
                'quantity' => $it->quantity,
                'unitPrice' => $it->unit_price
            ];
        }

        $orderDto = [
            'id' => $order->id,
            'status' => $order->status,
            'totalAmount' => $order->total_amount,
            'deliveryAddress' => $order->delivery_address,
            'paymentMethod' => $order->payment_method,
            'createdAt' => $order->created_at,
            'items' => $items
        ];

        return response()->json(['success' => true, 'order' => $orderDto]);
    }

    // POST /api/client/orders/{id}/cancel
    public function cancelOrder(Request $request, $id)
    {
        $user = $request->attributes->get('auth_user');
        $order = Order::find($id);
        if (!$order)
            return response()->json(['success' => false], 404);
        if ($order->client_id !== $user->id)
            return response()->json(['success' => false, 'message' => 'forbidden'], 403);
        $order->status = 'Cancelado';
        $order->save();
        return response()->json(['success' => true]);
    }

    // POST /api/client/orders/{id}/reorder
    public function reorder(Request $request, $id)
    {
        // Require authentication
        $user = $request->attributes->get('auth_user');
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'unauthenticated'], 401);
        }

        $old = Order::with('items')->find($id);
        if (!$old) {
            return response()->json(['success' => false, 'message' => 'not_found'], 404);
        }

        // Only owner may reorder
        if ($old->client_id !== $user->id) {
            return response()->json(['success' => false, 'message' => 'forbidden'], 403);
        }

        $order = Order::create([
            'client_id' => $old->client_id,
            'delivery_address' => $old->delivery_address,
            'total_amount' => $old->total_amount,
            'status' => 'Pendiente',
            'payment_method' => $old->payment_method,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        foreach ($old->items as $it) {
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $it->product_id,
                'quantity' => $it->quantity,
                'unit_price' => $it->unit_price
            ]);
        }

        return response()->json(['success' => true, 'order_id' => $order->id]);
    }

    // GET /api/client/profile/stats
    public function profileStats(Request $request)
    {
        $user = $request->attributes->get('auth_user');
        $total_orders = Order::where('client_id', $user->id)->count();
        $total_spent = Order::where('client_id', $user->id)->sum('total_amount');
        return response()->json(['success' => true, 'stats' => ['total_orders' => $total_orders, 'total_spent' => $total_spent, 'favorite_category' => null]]);
    }

    public function ordersRecent(Request $request)
    {
        $user = $request->attributes->get('auth_user');
        $orders = Order::where('client_id', $user->id)->orderBy('created_at', 'desc')->limit(5)->get();
        return response()->json(['success' => true, 'orders' => $orders]);
    }

    public function favorites(Request $request)
    {
        return response()->json(['success' => true, 'products' => []]);
    }

    // Public endpoint: GET /api/products/active-count
    public function activeCount()
    {
        $count = Product::where('is_available', 1)->count();
        return response()->json(['success' => true, 'active_count' => $count]);
    }

    // Public endpoint: GET /api/orders/{id}/details
    public function publicOrderDetails($id)
    {
        $order = Order::with('items.product', 'client')->find($id);
        if (!$order)
            return response()->json(['success' => true, 'order' => null]);
        $items = [];
        foreach ($order->items as $it) {
            $items[] = [
                'productId' => $it->product_id,
                'productName' => $it->product?->name ?? null,
                'quantity' => $it->quantity,
                'unitPrice' => $it->unit_price,
                'total' => $it->unit_price * $it->quantity
            ];
        }
        $orderDto = [
            'id' => $order->id,
            'status' => $order->status,
            'totalAmount' => $order->total_amount,
            'deliveryAddress' => $order->delivery_address,
            'paymentMethod' => $order->payment_method,
            'createdAt' => $order->created_at,
            'client' => [
                'id' => $order->client?->id ?? null,
                'name' => $order->client?->name ?? null,
                'email' => $order->client?->email ?? null,
                'address' => $order->client?->address ?? null
            ],
            'items' => $items
        ];
        return response()->json(['success' => true, 'order' => $orderDto]);
    }

    public function updateProfile(Request $request)
    {
        return app('App\\Http\\Controllers\\AuthController')->updateProfile($request);
    }

    public function changePassword(Request $request)
    {
        return app('App\\Http\\Controllers\\AuthController')->changePassword($request);
    }
}
