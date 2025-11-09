<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\SellerController;
use App\Http\Controllers\AdminController;

// CORS ahora se aplica globalmente desde bootstrap/app.php
// AUTH
Route::post('/auth/login', [AuthController::class, 'login']);
Route::get('/debug/auth', [AuthController::class, 'debugAuth']);
// Nota: como en la API Java, si no hay autenticación, devuelve success:true,user:null en lugar de 401
Route::get('/auth/me', [AuthController::class, 'me']);
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);
Route::post('/auth/change-password', [AuthController::class, 'changePassword'])->middleware(\App\Http\Middleware\JwtAuthMiddleware::class);
Route::put('/auth/profile', [AuthController::class, 'updateProfile'])->middleware(\App\Http\Middleware\JwtAuthMiddleware::class);
Route::match(['get', 'post'], '/auth/logout', [AuthController::class, 'logout'])->middleware(\App\Http\Middleware\JwtAuthMiddleware::class);

// CLIENT (requires role client)
// Public endpoints
Route::get('/products/active-count', [\App\Http\Controllers\ClientController::class, 'activeCount']);
Route::get('/orders/{id}/details', [\App\Http\Controllers\ClientController::class, 'publicOrderDetails']);
// Public full products catalog (no auth required)
Route::get('/public/products/full', [\App\Http\Controllers\ClientController::class, 'products']);

Route::prefix('client')->group(function () {
    Route::get('/products', [ClientController::class, 'products']);
    Route::get('/products/full', [ClientController::class, 'products']);
    Route::post('/orders', [ClientController::class, 'placeOrder'])->middleware(\App\Http\Middleware\JwtAuthMiddleware::class);
    Route::get('/categories', [ClientController::class, 'categories']);
    Route::get('/categories/{id}', [ClientController::class, 'categoryDetail']);
    Route::post('/cart/details', [ClientController::class, 'cartDetails']);
    Route::get('/orders', [ClientController::class, 'orders'])->middleware(\App\Http\Middleware\JwtAuthMiddleware::class);
    Route::get('/orders/{id}', [ClientController::class, 'orderDetail'])->middleware(\App\Http\Middleware\JwtAuthMiddleware::class);
    Route::post('/orders/{id}/cancel', [ClientController::class, 'cancelOrder'])->middleware(\App\Http\Middleware\JwtAuthMiddleware::class);
    Route::post('/orders/{id}/reorder', [ClientController::class, 'reorder'])->middleware(\App\Http\Middleware\JwtAuthMiddleware::class);
    Route::get('/profile/stats', [ClientController::class, 'profileStats'])->middleware(\App\Http\Middleware\JwtAuthMiddleware::class);
    Route::get('/orders/recent', [ClientController::class, 'ordersRecent'])->middleware(\App\Http\Middleware\JwtAuthMiddleware::class);
    Route::get('/favorites', [ClientController::class, 'favorites'])->middleware(\App\Http\Middleware\JwtAuthMiddleware::class);
    Route::put('/profile', [ClientController::class, 'updateProfile'])->middleware(\App\Http\Middleware\JwtAuthMiddleware::class);
    Route::post('/change-password', [ClientController::class, 'changePassword'])->middleware(\App\Http\Middleware\JwtAuthMiddleware::class);
});

// SELLER
Route::prefix('seller')->group(function () {
    Route::get('/dashboard', [SellerController::class, 'dashboard'])->middleware(\App\Http\Middleware\JwtAuthMiddleware::class);
    Route::get('/products/stock', [SellerController::class, 'productsStock'])->middleware(\App\Http\Middleware\JwtAuthMiddleware::class);
    Route::get('/products', [SellerController::class, 'products'])->middleware(\App\Http\Middleware\JwtAuthMiddleware::class);
    Route::get('/categories', [SellerController::class, 'categories'])->middleware(\App\Http\Middleware\JwtAuthMiddleware::class);
    Route::post('/products/{id}/availability', [SellerController::class, 'toggleAvailability'])->middleware(\App\Http\Middleware\JwtAuthMiddleware::class);
    Route::get('/products/{id}/status', [SellerController::class, 'status'])->middleware(\App\Http\Middleware\JwtAuthMiddleware::class);
    Route::post('/products/{id}/stock', [SellerController::class, 'updateStock'])->middleware(\App\Http\Middleware\JwtAuthMiddleware::class);
    Route::post('/products/stocks', [SellerController::class, 'bulkStocks'])->middleware(\App\Http\Middleware\JwtAuthMiddleware::class);
    Route::get('/orders', [SellerController::class, 'orders'])->middleware(\App\Http\Middleware\JwtAuthMiddleware::class);
    Route::get('/orders/{id}', [SellerController::class, 'orderDetail'])->middleware(\App\Http\Middleware\JwtAuthMiddleware::class);
    Route::post('/orders/{id}/status', [SellerController::class, 'changeOrderStatus'])->middleware(\App\Http\Middleware\JwtAuthMiddleware::class);
    Route::post('/orders/{id}/assign', [SellerController::class, 'assignSeller'])->middleware(\App\Http\Middleware\JwtAuthMiddleware::class);
});

// ADMIN
Route::prefix('admin')->group(function () {
    Route::get('/dashboard', [AdminController::class, 'dashboard'])->middleware(\App\Http\Middleware\JwtAuthMiddleware::class);
    Route::get('/orders/stats', [AdminController::class, 'ordersStats'])->middleware(\App\Http\Middleware\JwtAuthMiddleware::class);
    Route::get('/vendors', [AdminController::class, 'vendors'])->middleware(\App\Http\Middleware\JwtAuthMiddleware::class);
    Route::get('/orders', [AdminController::class, 'orders'])->middleware(\App\Http\Middleware\JwtAuthMiddleware::class);
    Route::get('/orders/{id}', [AdminController::class, 'orderDetail'])->middleware(\App\Http\Middleware\JwtAuthMiddleware::class);
    Route::put('/orders/{id}/status', [AdminController::class, 'updateOrderStatus'])->middleware(\App\Http\Middleware\JwtAuthMiddleware::class);
    Route::get('/orders/export', [AdminController::class, 'exportOrders'])->middleware(\App\Http\Middleware\JwtAuthMiddleware::class);
    Route::get('/reports/export', [AdminController::class, 'exportReports'])->middleware(\App\Http\Middleware\JwtAuthMiddleware::class);
    Route::delete('/orders/{id}', [AdminController::class, 'deleteOrder'])->middleware(\App\Http\Middleware\JwtAuthMiddleware::class);
    Route::get('/products/stats', [AdminController::class, 'productsStats'])->middleware(\App\Http\Middleware\JwtAuthMiddleware::class);
    Route::get('/categories', [AdminController::class, 'categories'])->middleware(\App\Http\Middleware\JwtAuthMiddleware::class);
    Route::get('/roles', [AdminController::class, 'roles'])->middleware(\App\Http\Middleware\JwtAuthMiddleware::class);
    Route::get('/roles/{id}', [AdminController::class, 'roleDetail'])->middleware(\App\Http\Middleware\JwtAuthMiddleware::class);
    Route::post('/categories', [AdminController::class, 'createCategory'])->middleware(\App\Http\Middleware\JwtAuthMiddleware::class);
    Route::put('/categories/{id}', [AdminController::class, 'updateCategory'])->middleware(\App\Http\Middleware\JwtAuthMiddleware::class);
    Route::delete('/categories/{id}', [AdminController::class, 'deleteCategory'])->middleware(\App\Http\Middleware\JwtAuthMiddleware::class);
    Route::get('/categories/{id}', [AdminController::class, 'categoryDetail'])->middleware(\App\Http\Middleware\JwtAuthMiddleware::class);
    Route::get('/products', [AdminController::class, 'products'])->middleware(\App\Http\Middleware\JwtAuthMiddleware::class);
    Route::get('/products/{id}', [AdminController::class, 'productDetail'])->middleware(\App\Http\Middleware\JwtAuthMiddleware::class);
    Route::post('/products', [AdminController::class, 'createProduct'])->middleware(\App\Http\Middleware\JwtAuthMiddleware::class);
    Route::put('/products/{id}', [AdminController::class, 'updateProduct'])->middleware(\App\Http\Middleware\JwtAuthMiddleware::class);
    Route::post('/products/{id}/toggle-status', [AdminController::class, 'toggleProductStatus'])->middleware(\App\Http\Middleware\JwtAuthMiddleware::class);
    Route::delete('/products/{id}', [AdminController::class, 'deleteProduct'])->middleware(\App\Http\Middleware\JwtAuthMiddleware::class);
    Route::get('/products/export', [AdminController::class, 'exportProducts'])->middleware(\App\Http\Middleware\JwtAuthMiddleware::class);
    Route::get('/reports', [AdminController::class, 'reports'])->middleware(\App\Http\Middleware\JwtAuthMiddleware::class);

    // Extra admin stats endpoints
    Route::get('/stats/revenue-by-segment', [AdminController::class, 'statsRevenueBySegment'])->middleware(\App\Http\Middleware\JwtAuthMiddleware::class);
    Route::get('/stats/top-clients', [AdminController::class, 'statsTopClients'])->middleware(\App\Http\Middleware\JwtAuthMiddleware::class);

    // Users management
    Route::get('/users', [AdminController::class, 'users'])->middleware(\App\Http\Middleware\JwtAuthMiddleware::class);
    Route::post('/users', [AdminController::class, 'createUser'])->middleware(\App\Http\Middleware\JwtAuthMiddleware::class);
    Route::put('/users/{id}', [AdminController::class, 'updateUser'])->middleware(\App\Http\Middleware\JwtAuthMiddleware::class);
    Route::post('/users/{id}/status', [AdminController::class, 'userStatus'])->middleware(\App\Http\Middleware\JwtAuthMiddleware::class);
    Route::delete('/users/{id}', [AdminController::class, 'deleteUser'])->middleware(\App\Http\Middleware\JwtAuthMiddleware::class);

    // stats endpoints
    Route::get('/stats/sales-by-day', [AdminController::class, 'statsSalesByDay'])->middleware(\App\Http\Middleware\JwtAuthMiddleware::class);
    Route::get('/stats/sales-by-seller', [AdminController::class, 'statsSalesBySeller'])->middleware(\App\Http\Middleware\JwtAuthMiddleware::class);
    Route::get('/stats/top-products', [AdminController::class, 'statsTopProducts'])->middleware(\App\Http\Middleware\JwtAuthMiddleware::class);
    Route::get('/stats/users-growth', [AdminController::class, 'statsUsersGrowth'])->middleware(\App\Http\Middleware\JwtAuthMiddleware::class);
    Route::get('/stats/orders-by-status', [AdminController::class, 'statsOrdersByStatus'])->middleware(\App\Http\Middleware\JwtAuthMiddleware::class);
    Route::get('/stats/orders-period', [AdminController::class, 'statsOrdersPeriod'])->middleware(\App\Http\Middleware\JwtAuthMiddleware::class);
    Route::get('/stats/rates', [AdminController::class, 'statsRates'])->middleware(\App\Http\Middleware\JwtAuthMiddleware::class);
    Route::get('/stats/revenue-summary', [AdminController::class, 'statsRevenueSummary'])->middleware(\App\Http\Middleware\JwtAuthMiddleware::class);
});
