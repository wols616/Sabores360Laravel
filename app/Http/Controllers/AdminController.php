<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Category;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AdminController extends Controller
{
    // Helper: verify the request auth_user is an admin
    private function getAdminUser(\Illuminate\Http\Request $request)
    {
        $user = $request->attributes->get('auth_user');
        if (!$user)
            return null;
        $roleName = strtolower($user->role?->name ?? '');
        if ($roleName !== 'administrador' && $roleName !== 'admin')
            return null;
        return $user;
    }

    public function dashboard(\Illuminate\Http\Request $request)
    {
        if (!$this->getAdminUser($request))
            return response()->json(['success' => false, 'message' => 'forbidden'], 403);
        $orders_count = Order::count();
        $users_count = User::count();
        $products_count = Product::count();
        $low_stock_count = Product::where('stock', '<', 10)->count();
        $recent_orders = Order::orderBy('created_at', 'desc')->limit(10)->get();
        return response()->json(['success' => true, 'orders_count' => $orders_count, 'users_count' => $users_count, 'products_count' => $products_count, 'low_stock_count' => $low_stock_count, 'recent_orders' => $recent_orders]);
    }

    public function ordersStats(\Illuminate\Http\Request $request)
    {
        if (!$this->getAdminUser($request))
            return response()->json(['success' => false, 'message' => 'forbidden'], 403);
        $stats = Order::selectRaw('status, count(*) as count')->groupBy('status')->get()->pluck('count', 'status');
        return response()->json(['success' => true, 'stats' => $stats]);
    }

    public function vendors(\Illuminate\Http\Request $request)
    {
        if (!$this->getAdminUser($request))
            return response()->json(['success' => false, 'message' => 'forbidden'], 403);
        $vendors = User::whereHas('role', function ($q) {
            $q->where('name', 'Vendedor');
        })->get();
        return response()->json(['success' => true, 'vendors' => $vendors]);
    }

    public function orders(Request $request)
    {
        if (!$this->getAdminUser($request))
            return response()->json(['success' => false, 'message' => 'forbidden'], 403);
        $page = max(1, intval($request->query('page', 1)));
        $perPage = max(1, intval($request->query('per_page', 20)));
        $query = Order::query();
        if ($request->filled('status'))
            $query->where('status', $request->query('status'));
        if ($request->filled('vendor_id'))
            $query->where('vendor_id', intval($request->query('vendor_id')));
        if ($request->filled('date_from'))
            $query->whereDate('created_at', '>=', $request->query('date_from'));
        if ($request->filled('date_to'))
            $query->whereDate('created_at', '<=', $request->query('date_to'));
        if ($request->filled('search')) {
            $s = $request->query('search');
            $query->where(function ($q) use ($s) {
                $q->where('id', $s)->orWhere('delivery_address', 'like', "%{$s}%");
            });
        }
        $paginator = $query->orderBy('created_at', 'desc')->paginate($perPage, ['*'], 'page', $page);
        $orders = $paginator->getCollection();
        return response()->json(['success' => true, 'orders' => $orders->values(), 'pagination' => ['page' => $paginator->currentPage(), 'total_pages' => $paginator->lastPage()]]);
    }

    public function orderDetail(Request $request, $id)
    {
        if (!$this->getAdminUser($request))
            return response()->json(['success' => false, 'message' => 'forbidden'], 403);
        $order = Order::with('items.product', 'client')->find($id);
        return response()->json(['success' => true, 'order' => $order]);
    }

    public function updateOrderStatus(Request $request, $id)
    {
        if (!$this->getAdminUser($request))
            return response()->json(['success' => false, 'message' => 'forbidden'], 403);
        $order = Order::find($id);
        if (!$order)
            return response()->json(['success' => false, 'message' => 'not_found'], 404);
        $order->status = $request->input('status');
        $order->save();
        return response()->json(['success' => true]);
    }

    public function exportOrders(Request $request)
    {
        if (!$this->getAdminUser($request))
            return response()->json(['success' => false, 'message' => 'forbidden'], 403);
        // Minimal CSV export but with xlsx filename for compatibility with clients expecting Excel download
        $content = "id,status,total_amount\n";
        return response($content, 200, ['Content-Type' => 'application/octet-stream', 'Content-Disposition' => 'attachment; filename="pedidos.xlsx"']);
    }

    public function deleteOrder(Request $request, $id)
    {
        if (!$this->getAdminUser($request))
            return response()->json(['success' => false, 'message' => 'forbidden'], 403);
        Order::where('id', $id)->delete();
        return response()->json(['success' => true]);
    }

    public function productsStats(Request $request)
    {
        if (!$this->getAdminUser($request))
            return response()->json(['success' => false, 'message' => 'forbidden'], 403);
        $total = Product::count();
        $low_stock_count = Product::where('stock', '<', 10)->count();
        $inactive = Product::where('is_available', 0)->count();
        return response()->json(['success' => true, 'total' => $total, 'low_stock_count' => $low_stock_count, 'inactive' => $inactive]);
    }

    public function categories(Request $request)
    {
        if (!$this->getAdminUser($request))
            return response()->json(['success' => false, 'message' => 'forbidden'], 403);
        return response()->json(['success' => true, 'categories' => Category::all()]);
    }

    public function categoryDetail(Request $request, $id)
    {
        if (!$this->getAdminUser($request))
            return response()->json(['success' => false, 'message' => 'forbidden'], 403);
        $cat = Category::find($id);
        if (!$cat)
            return response()->json(['success' => false, 'category' => null], 404);
        return response()->json(['success' => true, 'category' => $cat]);
    }

    public function roles(Request $request)
    {
        if (!$this->getAdminUser($request))
            return response()->json(['success' => false, 'message' => 'forbidden'], 403);
        return response()->json(['success' => true, 'roles' => Role::all()]);
    }

    public function roleDetail(Request $request, $id)
    {
        if (!$this->getAdminUser($request))
            return response()->json(['success' => false, 'message' => 'forbidden'], 403);
        $r = Role::find($id);
        if (!$r)
            return response()->json(['success' => false, 'role' => null], 404);
        return response()->json(['success' => true, 'role' => $r]);
    }

    public function createCategory(Request $request)
    {
        if (!$this->getAdminUser($request))
            return response()->json(['success' => false, 'message' => 'forbidden'], 403);
        $c = Category::create($request->only(['name', 'description']));
        return response()->json(['success' => true, 'id' => $c->id, 'name' => $c->name]);
    }

    public function updateCategory(Request $request, $id)
    {
        if (!$this->getAdminUser($request))
            return response()->json(['success' => false, 'message' => 'forbidden'], 403);
        $c = Category::find($id);
        if (!$c)
            return response()->json(['success' => false, 'message' => 'not_found'], 404);
        $c->fill($request->only(['name', 'description']));
        $c->save();
        return response()->json(['success' => true, 'id' => $c->id, 'name' => $c->name]);
    }

    public function deleteCategory($id)
    {
        // note: keep guard
        // this method will be called via route with middleware; still verify
        // accept Request optional
        Category::where('id', $id)->delete();
        return response()->json(['success' => true]);
    }

    public function products(Request $request)
    {
        if (!$this->getAdminUser($request))
            return response()->json(['success' => false, 'message' => 'forbidden'], 403);
        $page = max(1, intval($request->query('page', 1)));
        $perPage = max(1, intval($request->query('per_page', 20)));
        $query = Product::query();
        if ($request->filled('category'))
            $query->where('category_id', intval($request->query('category')));
        if ($request->filled('search'))
            $query->where('name', 'like', "%{$request->query('search')}%");
        if ($request->boolean('low_stock'))
            $query->where('stock', '<', 10);
        $p = $query->paginate($perPage, ['*'], 'page', $page);
        return response()->json(['success' => true, 'products' => $p->getCollection()->values(), 'pagination' => ['page' => $p->currentPage(), 'total_pages' => $p->lastPage()]]);
    }

    public function productDetail(Request $request, $id)
    {
        if (!$this->getAdminUser($request))
            return response()->json(['success' => false, 'message' => 'forbidden'], 403);
        $p = Product::find($id);
        return response()->json(['success' => true, 'product' => $p]);
    }

    public function createProduct(Request $request)
    {
        if (!$this->getAdminUser($request))
            return response()->json(['success' => false, 'message' => 'forbidden'], 403);
        $p = Product::create($request->only(['category_id', 'name', 'description', 'price', 'stock', 'image_url', 'is_available']));
        return response()->json(['success' => true, 'id' => $p->id, 'name' => $p->name]);
    }

    public function updateProduct(Request $request, $id)
    {
        if (!$this->getAdminUser($request))
            return response()->json(['success' => false, 'message' => 'forbidden'], 403);
        $p = Product::find($id);
        if (!$p)
            return response()->json(['success' => false, 'message' => 'not_found'], 404);
        $p->fill($request->only(['category_id', 'name', 'description', 'price', 'stock', 'image_url', 'is_available']));
        $p->save();
        return response()->json(['success' => true, 'id' => $p->id, 'name' => $p->name]);
    }

    public function toggleProductStatus(Request $request, $id)
    {
        if (!$this->getAdminUser($request))
            return response()->json(['success' => false, 'message' => 'forbidden'], 403);
        $p = Product::find($id);
        if (!$p)
            return response()->json(['success' => false, 'message' => 'not_found'], 404);
        $p->is_available = !$p->is_available;
        $p->save();
        return response()->json(['success' => true]);
    }

    public function deleteProduct(Request $request, $id)
    {
        if (!$this->getAdminUser($request))
            return response()->json(['success' => false, 'message' => 'forbidden'], 403);
        Product::where('id', $id)->delete();
        return response()->json(['success' => true]);
    }

    public function exportProducts(Request $request)
    {
        if (!$this->getAdminUser($request))
            return response()->json(['success' => false, 'message' => 'forbidden'], 403);
        $content = "id,name,price\n";
        return response($content, 200, ['Content-Type' => 'application/octet-stream', 'Content-Disposition' => 'attachment; filename="productos.xlsx"']);
    }

    public function reports(Request $request)
    {
        if (!$this->getAdminUser($request))
            return response()->json(['success' => false, 'message' => 'forbidden'], 403);
        return response()->json(['success' => true, 'sales_by_day' => [], 'sales_by_seller' => [], 'top_products' => []]);
    }

    public function exportReports(Request $request)
    {
        if (!$this->getAdminUser($request))
            return response()->json(['success' => false, 'message' => 'forbidden'], 403);
        $content = "date,total\n";
        return response($content, 200, ['Content-Type' => 'application/octet-stream', 'Content-Disposition' => 'attachment; filename="reportes.xlsx"']);
    }

    // Stats endpoints (minimal placeholders)
    public function statsSalesByDay(Request $request)
    {
        if (!$this->getAdminUser($request))
            return response()->json(['success' => false, 'message' => 'forbidden'], 403);

        $dateTo = $request->query('date_to');
        $dateFrom = $request->query('date_from');
        $end = $dateTo ? Carbon::parse($dateTo)->endOfDay() : Carbon::now()->endOfDay();
        $start = $dateFrom ? Carbon::parse($dateFrom)->startOfDay() : $end->copy()->subDays(29)->startOfDay();

        $rows = Order::selectRaw("DATE(created_at) as date, COALESCE(SUM(total_amount),0) as total")
            ->whereBetween('created_at', [$start->toDateTimeString(), $end->toDateTimeString()])
            // Exclude cancelled/anulado orders
            ->whereRaw("LOWER(TRIM(status)) NOT IN ('cancelado','anulado')")
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $result = [];
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $d = $cursor->toDateString();
            $total = isset($rows[$d]) ? floatval($rows[$d]->total) : 0.0;
            $result[] = ['fecha' => $d, 'totalVentas' => $total];
            $cursor->addDay();
        }

        return response()->json(['success' => true, 'data' => ['sales_by_day' => $result]]);
    }

    public function statsSalesBySeller(Request $request)
    {
        if (!$this->getAdminUser($request))
            return response()->json(['success' => false, 'message' => 'forbidden'], 403);

        $dateTo = $request->query('date_to');
        $dateFrom = $request->query('date_from');
        $end = $dateTo ? Carbon::parse($dateTo)->endOfDay() : Carbon::now()->endOfDay();
        $start = $dateFrom ? Carbon::parse($dateFrom)->startOfDay() : $end->copy()->subDays(29)->startOfDay();

        $rows = Order::selectRaw('seller_id, COALESCE(SUM(total_amount),0) as total')
            ->whereBetween('created_at', [$start->toDateTimeString(), $end->toDateTimeString()])
            // Exclude cancelled/anulado orders
            ->whereRaw("LOWER(TRIM(status)) NOT IN ('cancelado','anulado')")
            ->whereNotNull('seller_id')
            ->groupBy('seller_id')
            ->orderByDesc('total')
            ->get();

        $result = [];
        foreach ($rows as $r) {
            $seller = User::find($r->seller_id);
            $result[] = ['vendedorId' => $r->seller_id, 'vendedorNombre' => $seller ? $seller->name : null, 'totalVentas' => floatval($r->total)];
        }

        return response()->json(['success' => true, 'data' => ['sales_by_seller' => $result]]);
    }

    public function statsTopProducts(Request $request)
    {
        if (!$this->getAdminUser($request))
            return response()->json(['success' => false, 'message' => 'forbidden'], 403);

        $limit = max(1, intval($request->query('limit', 10)));
        $dateTo = $request->query('date_to');
        $dateFrom = $request->query('date_from');
        $end = $dateTo ? Carbon::parse($dateTo)->endOfDay() : Carbon::now()->endOfDay();
        $start = $dateFrom ? Carbon::parse($dateFrom)->startOfDay() : $end->copy()->subDays(29)->startOfDay();

        $rows = OrderItem::selectRaw('product_id, COALESCE(SUM(quantity),0) as qty')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->whereBetween('orders.created_at', [$start->toDateTimeString(), $end->toDateTimeString()])
            // Exclude cancelled/anulado orders from the join
            ->whereRaw("LOWER(TRIM(orders.status)) NOT IN ('cancelado','anulado')")
            ->groupBy('product_id')
            ->orderByDesc('qty')
            ->limit($limit)
            ->get();

        $result = [];
        foreach ($rows as $r) {
            $p = Product::find($r->product_id);
            $result[] = ['productoId' => $r->product_id, 'productoNombre' => $p ? $p->name : null, 'cantidadVendida' => intval($r->qty)];
        }

        return response()->json(['success' => true, 'data' => ['top_products' => $result]]);
    }

    public function statsUsersGrowth(Request $request)
    {
        if (!$this->getAdminUser($request))
            return response()->json(['success' => false, 'message' => 'forbidden'], 403);

        $dateTo = $request->query('date_to');
        $dateFrom = $request->query('date_from');
        $end = $dateTo ? Carbon::parse($dateTo)->endOfDay() : Carbon::now()->endOfDay();
        $start = $dateFrom ? Carbon::parse($dateFrom)->startOfDay() : $end->copy()->subDays(29)->startOfDay();

        $rows = User::selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->whereBetween('created_at', [$start->toDateTimeString(), $end->toDateTimeString()])
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $result = [];
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $d = $cursor->toDateString();
            $count = isset($rows[$d]) ? intval($rows[$d]->count) : 0;
            $result[] = ['fecha' => $d, 'cantidadUsuarios' => $count];
            $cursor->addDay();
        }

        return response()->json(['success' => true, 'data' => ['users_growth' => $result]]);
    }

    public function statsOrdersByStatus(Request $request)
    {
        if (!$this->getAdminUser($request))
            return response()->json(['success' => false, 'message' => 'forbidden'], 403);

        $dateTo = $request->query('date_to');
        $dateFrom = $request->query('date_from');
        $end = $dateTo ? Carbon::parse($dateTo)->endOfDay() : Carbon::now()->endOfDay();
        $start = $dateFrom ? Carbon::parse($dateFrom)->startOfDay() : $end->copy()->subDays(29)->startOfDay();

        $rows = Order::selectRaw('status, COUNT(*) as count')
            ->whereBetween('created_at', [$start->toDateTimeString(), $end->toDateTimeString()])
            ->groupBy('status')
            ->get();

        $result = [];
        foreach ($rows as $r) {
            $result[] = ['status' => $r->status, 'count' => intval($r->count)];
        }

        return response()->json(['success' => true, 'data' => ['orders_by_status' => $result]]);
    }

    public function statsOrdersPeriod(Request $request)
    {
        if (!$this->getAdminUser($request))
            return response()->json(['success' => false, 'message' => 'forbidden'], 403);

        $dateTo = $request->query('date_to');
        $dateFrom = $request->query('date_from');
        $granularity = $request->query('granularity', 'daily'); // daily, weekly, monthly
        $end = $dateTo ? Carbon::parse($dateTo)->endOfDay() : Carbon::now()->endOfDay();
        $start = $dateFrom ? Carbon::parse($dateFrom)->startOfDay() : $end->copy()->subDays(29)->startOfDay();

        $periodDays = $start->diffInDays($end) + 1;
        $prevStart = $start->copy()->subDays($periodDays);
        $prevEnd = $start->copy()->subDay();

        // Build series
        $series = [];
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            if ($granularity === 'monthly') {
                $label = $cursor->format('Y-m');
                $next = $cursor->copy()->endOfMonth();
            } else {
                $label = $cursor->toDateString();
                $next = $cursor->copy()->endOfDay();
            }

            $count = Order::whereBetween('created_at', [$cursor->startOfDay()->toDateTimeString(), $next->toDateTimeString()])->count();
            $series[] = ['label' => $label, 'count' => $count];
            $cursor = $cursor->copy()->addDay();
        }

        $current_total = Order::whereBetween('created_at', [$start->toDateTimeString(), $end->toDateTimeString()])->count();
        $previous_total = Order::whereBetween('created_at', [$prevStart->toDateTimeString(), $prevEnd->toDateTimeString()])->count();
        $percent_change = 0;
        if ($previous_total == 0) {
            $percent_change = $current_total > 0 ? 100.0 : 0.0;
        } else {
            $percent_change = (($current_total - $previous_total) / max(1, $previous_total)) * 100.0;
        }

        return response()->json(['success' => true, 'data' => ['series' => $series, 'current_total' => $current_total, 'previous_total' => $previous_total, 'percent_change' => $percent_change, 'granularity' => $granularity]]);
    }

    public function statsRates(Request $request)
    {
        if (!$this->getAdminUser($request))
            return response()->json(['success' => false, 'message' => 'forbidden'], 403);

        $dateTo = $request->query('date_to');
        $dateFrom = $request->query('date_from');
        $end = $dateTo ? Carbon::parse($dateTo)->endOfDay() : Carbon::now()->endOfDay();
        $start = $dateFrom ? Carbon::parse($dateFrom)->startOfDay() : $end->copy()->subDays(29)->startOfDay();

        $total = Order::whereBetween('created_at', [$start->toDateTimeString(), $end->toDateTimeString()])->count();
        $confirmed = Order::whereBetween('created_at', [$start->toDateTimeString(), $end->toDateTimeString()])->where('status', 'Confirmado')->count();
        $closed = Order::whereBetween('created_at', [$start->toDateTimeString(), $end->toDateTimeString()])->where('status', 'Entregado')->count();
        $cancelled = Order::whereBetween('created_at', [$start->toDateTimeString(), $end->toDateTimeString()])->where('status', 'Cancelado')->count();

        $confirmation_rate = $total ? ($confirmed / $total) : null;
        $closure_rate = $total ? ($closed / $total) : null;
        $cancellation_rate = $total ? ($cancelled / $total) : null;

        return response()->json(['success' => true, 'data' => ['confirmation_rate' => $confirmation_rate, 'closure_rate' => $closure_rate, 'cancellation_rate' => $cancellation_rate]]);
    }

    public function statsRevenueSummary(Request $request)
    {
        if (!$this->getAdminUser($request))
            return response()->json(['success' => false, 'message' => 'forbidden'], 403);

        $dateTo = $request->query('date_to');
        $dateFrom = $request->query('date_from');
        $end = $dateTo ? Carbon::parse($dateTo)->endOfDay() : Carbon::now()->endOfDay();
        $start = $dateFrom ? Carbon::parse($dateFrom)->startOfDay() : $end->copy()->subDays(29)->startOfDay();

        $periodDays = $start->diffInDays($end) + 1;
        $prevStart = $start->copy()->subDays($periodDays);
        $prevEnd = $start->copy()->subDay();

        // Exclude cancelled/anulado orders from revenue calculations
        $current = floatval(Order::whereBetween('created_at', [$start->toDateTimeString(), $end->toDateTimeString()])
            ->whereRaw("LOWER(TRIM(status)) NOT IN ('cancelado','anulado')")
            ->sum('total_amount'));
        $previous = floatval(Order::whereBetween('created_at', [$prevStart->toDateTimeString(), $prevEnd->toDateTimeString()])
            ->whereRaw("LOWER(TRIM(status)) NOT IN ('cancelado','anulado')")
            ->sum('total_amount'));

        $percent_change = 0.0;
        if ($previous == 0.0) {
            $percent_change = $current > 0.0 ? 100.0 : 0.0;
        } else {
            $percent_change = (($current - $previous) / max(0.000001, $previous)) * 100.0;
        }

        // YoY: same period last year
        $yoyStart = $start->copy()->subYear();
        $yoyEnd = $end->copy()->subYear();
        $yoy = floatval(Order::whereBetween('created_at', [$yoyStart->toDateTimeString(), $yoyEnd->toDateTimeString()])
            ->whereRaw("LOWER(TRIM(status)) NOT IN ('cancelado','anulado')")
            ->sum('total_amount'));
        $yoy_percent = 0.0;
        if ($yoy == 0.0) {
            $yoy_percent = $yoy == $current ? 0.0 : ($current > 0.0 ? 100.0 : 0.0);
        } else {
            $yoy_percent = (($current - $yoy) / max(0.000001, $yoy)) * 100.0;
        }

        return response()->json(['success' => true, 'data' => ['current_revenue' => $current, 'previous_revenue' => $previous, 'percent_change' => $percent_change, 'yoy_revenue' => $yoy, 'yoy_percent_change' => $yoy_percent]]);
    }

    public function statsRevenueBySegment(Request $request)
    {
        if (!$this->getAdminUser($request))
            return response()->json(['success' => false, 'message' => 'forbidden'], 403);
        $dateTo = $request->query('date_to');
        $dateFrom = $request->query('date_from');
        $end = $dateTo ? Carbon::parse($dateTo)->endOfDay() : Carbon::now()->endOfDay();
        $start = $dateFrom ? Carbon::parse($dateFrom)->startOfDay() : $end->copy()->subDays(29)->startOfDay();

        // By seller
        $bySellerRows = Order::selectRaw('seller_id, COALESCE(SUM(total_amount),0) as total, COUNT(*) as count')
            ->whereBetween('created_at', [$start->toDateTimeString(), $end->toDateTimeString()])
            ->whereRaw("LOWER(TRIM(status)) NOT IN ('cancelado','anulado')")
            ->whereNotNull('seller_id')
            ->groupBy('seller_id')
            ->orderByDesc('total')
            ->get();
        $by_seller = [];
        foreach ($bySellerRows as $r) {
            $s = User::find($r->seller_id);
            $by_seller[] = ['vendedorId' => $r->seller_id, 'vendedorNombre' => $s ? $s->name : null, 'totalVentas' => floatval($r->total)];
        }

        // By channel (use payment_method as proxy)
        $byChannelRows = Order::selectRaw('payment_method as label, COUNT(*) as count, COALESCE(SUM(total_amount),0) as total')
            ->whereBetween('created_at', [$start->toDateTimeString(), $end->toDateTimeString()])
            ->whereRaw("LOWER(TRIM(status)) NOT IN ('cancelado','anulado')")
            ->groupBy('payment_method')
            ->get();
        $by_channel = [];
        foreach ($byChannelRows as $r) {
            $by_channel[] = ['label' => $r->label, 'count' => intval($r->count), 'total' => floatval($r->total)];
        }

        // By category (join order_items -> products -> categories)
        $byCategoryRows = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->whereBetween('orders.created_at', [$start->toDateTimeString(), $end->toDateTimeString()])
            ->whereRaw("LOWER(TRIM(orders.status)) NOT IN ('cancelado','anulado')")
            ->selectRaw('categories.id as category_id, categories.name as label, COUNT(*) as count, COALESCE(SUM(order_items.quantity * order_items.unit_price),0) as total')
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('total')
            ->get();
        $by_category = [];
        foreach ($byCategoryRows as $r) {
            $by_category[] = ['label' => $r->label, 'count' => intval($r->count), 'total' => floatval($r->total)];
        }

        return response()->json(['success' => true, 'data' => ['by_seller' => $by_seller, 'by_channel' => $by_channel, 'by_category' => $by_category]]);
    }

    public function statsTopClients(Request $request)
    {
        if (!$this->getAdminUser($request))
            return response()->json(['success' => false, 'message' => 'forbidden'], 403);
        $limit = max(1, intval($request->query('limit', 20)));
        $dateTo = $request->query('date_to');
        $dateFrom = $request->query('date_from');
        $end = $dateTo ? Carbon::parse($dateTo)->endOfDay() : Carbon::now()->endOfDay();
        $start = $dateFrom ? Carbon::parse($dateFrom)->startOfDay() : $end->copy()->subDays(29)->startOfDay();

        $rows = Order::selectRaw('client_id, COUNT(*) as orders_count, COALESCE(SUM(total_amount),0) as total')
            ->whereBetween('created_at', [$start->toDateTimeString(), $end->toDateTimeString()])
            ->whereRaw("LOWER(TRIM(status)) NOT IN ('cancelado','anulado')")
            ->groupBy('client_id')
            ->orderByDesc('total')
            ->limit($limit)
            ->get();

        $result = [];
        foreach ($rows as $r) {
            $u = User::find($r->client_id);
            $result[] = ['label' => $u ? $u->name : null, 'count' => intval($r->orders_count), 'total' => floatval($r->total)];
        }

        return response()->json(['success' => true, 'data' => ['top_clients' => $result]]);
    }

    /**
     * GET /api/admin/stats/seller-sales-total
     * Query params: seller_id (required), date (YYYY-MM-DD, optional)
     * Returns the total monetary sales (sum of total_amount) for the given seller on the given day.
     * Orders with status 'Cancelado' are excluded from the total (all other statuses are counted).
     */
    public function statsSellerTotal(Request $request)
    {
        if (!$this->getAdminUser($request))
            return response()->json(['success' => false, 'message' => 'forbidden'], 403);

        $sellerId = $request->query('seller_id') ?? $request->query('vendedorId');
        if (!$sellerId) {
            return response()->json(['success' => false, 'message' => 'seller_id is required'], 400);
        }

        $date = $request->query('date');
        $day = $date ? Carbon::parse($date) : Carbon::now();
        $start = $day->copy()->startOfDay();
        $end = $day->copy()->endOfDay();

        // Exclude canceled/anulado orders (case-insensitive, trim whitespace);
        // count all other statuses for the seller on that day.
        $total = floatval(Order::where('seller_id', intval($sellerId))
            ->whereBetween('created_at', [$start->toDateTimeString(), $end->toDateTimeString()])
            ->whereRaw("LOWER(TRIM(status)) NOT IN ('cancelado','anulado')")
            ->sum('total_amount'));

        return response()->json(['success' => true, 'total' => $total]);
    }

    /**
     * GET /api/admin/stats/seller-delivered-count
     * Query params: seller_id (required), date (YYYY-MM-DD, optional)
     * Returns the count of delivered orders (status 'Entregado') for the given seller on the given day.
     */
    public function statsSellerDeliveredCount(Request $request)
    {
        if (!$this->getAdminUser($request))
            return response()->json(['success' => false, 'message' => 'forbidden'], 403);

        $sellerId = $request->query('seller_id') ?? $request->query('vendedorId');
        if (!$sellerId) {
            return response()->json(['success' => false, 'message' => 'seller_id is required'], 400);
        }

        $date = $request->query('date');
        $day = $date ? Carbon::parse($date) : Carbon::now();
        $start = $day->copy()->startOfDay();
        $end = $day->copy()->endOfDay();

        $count = intval(Order::where('seller_id', intval($sellerId))
            ->where('status', 'Entregado')
            ->whereBetween('created_at', [$start->toDateTimeString(), $end->toDateTimeString()])
            ->count());

        return response()->json(['success' => true, 'count' => $count]);
    }

    // Users management
    public function users(Request $request)
    {
        if (!$this->getAdminUser($request))
            return response()->json(['success' => false, 'message' => 'forbidden'], 403);
        $page = max(1, intval($request->query('page', 1)));
        $perPage = max(1, intval($request->query('per_page', 20)));
        $p = User::orderBy('id', 'desc')->paginate($perPage, ['*'], 'page', $page);
        return response()->json(['success' => true, 'users' => $p->getCollection()->values(), 'pagination' => ['page' => $p->currentPage(), 'total_pages' => $p->lastPage()]]);
    }

    public function createUser(Request $request)
    {
        if (!$this->getAdminUser($request))
            return response()->json(['success' => false, 'message' => 'forbidden'], 403);
        $data = $request->only(['name', 'email', 'password', 'address', 'role_id', 'is_active']);
        if (empty($data['name']) || empty($data['email']) || empty($data['password']))
            return response()->json(['success' => false, 'message' => 'invalid_input'], 400);
        $hash = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 10]);
        $u = User::create(['name' => $data['name'], 'email' => $data['email'], 'password_hash' => $hash, 'address' => $data['address'] ?? null, 'role_id' => $data['role_id'] ?? null, 'is_active' => isset($data['is_active']) ? (bool) $data['is_active'] : true]);
        return response()->json(['success' => true, 'id' => $u->id, 'name' => $u->name, 'email' => $u->email]);
    }

    public function updateUser(Request $request, $id)
    {
        if (!$this->getAdminUser($request))
            return response()->json(['success' => false, 'message' => 'forbidden'], 403);
        $u = User::find($id);
        if (!$u)
            return response()->json(['success' => false, 'message' => 'not_found'], 404);
        $data = $request->only(['name', 'email', 'password', 'address', 'role_id', 'is_active']);
        if (isset($data['password']))
            $u->password_hash = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 10]);
        foreach (['name', 'email', 'address', 'role_id', 'is_active'] as $k)
            if (array_key_exists($k, $data))
                $u->$k = $data[$k];
        $u->save();
        return response()->json(['success' => true, 'id' => $u->id, 'name' => $u->name, 'email' => $u->email]);
    }

    public function userStatus(Request $request, $id)
    {
        if (!$this->getAdminUser($request))
            return response()->json(['success' => false, 'message' => 'forbidden'], 403);
        $u = User::find($id);
        if (!$u)
            return response()->json(['success' => false, 'message' => 'not_found'], 404);
        $status = $request->input('status');
        $u->is_active = ($status === 'active');
        $u->save();
        return response()->json(['success' => true]);
    }

    public function deleteUser(Request $request, $id)
    {
        if (!$this->getAdminUser($request))
            return response()->json(['success' => false, 'message' => 'forbidden'], 403);
        User::where('id', $id)->delete();
        return response()->json(['success' => true]);
    }
}
