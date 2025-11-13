# API_ROUTES

This document lists the API endpoints implemented in this project and their expected request/response shapes.

Notes:

-   Most authenticated endpoints require a JWT. The server accepts the token in the `Authorization: Bearer <token>` header, or via `token`/`auth_token` cookies or `?token=` query param.
-   Successful responses normally use JSON objects with a `success` boolean, and then either `user`, `data`, `orders`, `products` or other keys depending on the endpoint.

---

## AUTH

### POST /api/auth/login

-   Qué hace: Autentica con email + password, devuelve JWT y datos básicos del usuario. También intenta setear cookies `token` (HttpOnly) y `auth_token` (legible para dev).
-   Método: POST
-   Body: { email: string, password: string }
-   Response: { success: true, token: string, user: { id, name, email, role, address } }

### GET /api/debug/auth

-   Qué hace: (No auth) Devuelve los headers, cookies y el token detectado por el servidor y el payload si el token valida. Útil para depuración.
-   Método: GET
-   Response: { success: true, headers: {...}, cookies: {...}, detected_auth_raw: string|null, token: string|null, payload: object|null }

### GET /api/auth/me

-   Qué hace: Devuelve el usuario autenticado. Si no hay sesión válida devuelve `user: null` (comportamiento intencionalmente permisivo, igual que la API Java).
-   Método: GET
-   Auth: opcional (si no hay token devuelve `user: null`)
-   Response (autenticado): { success: true, user: { id, name, email, role: "admin|seller|client", address } }
-   Response (no auth): { success: true, user: null }

### POST /api/auth/register

-   Qué hace: Registra un nuevo usuario (cliente por defecto).
-   Método: POST
-   Body: { name, email, password, address? }
-   Response: { success: true, userId }

### POST /api/auth/forgot-password

-   Qué hace: Genera & guarda token de reset; devuelve token (no email enviado en esta implementación de prueba).
-   Método: POST
-   Body: { email }
-   Response: { success: true, token }

### POST /api/auth/reset-password

-   Qué hace: Reset de contraseña con token.
-   Método: POST
-   Body: { token, password }
-   Response: { success: true }

### POST /api/auth/change-password

-   Qué hace: Cambia contraseña (autenticado).
-   Método: POST
-   Auth: JWT required (middleware)
-   Body: { currentPassword, newPassword }
-   Response: { success: true }

### PUT /api/auth/profile

-   Qué hace: Actualiza profile del usuario autenticado.
-   Método: PUT
-   Auth: JWT required
-   Body: { name?, email?, address? }
-   Response: { success: true }

### GET/POST /api/auth/logout

-   Qué hace: Logout (stateless JWT - no acción en servidor por defecto).
-   Método: GET or POST
-   Auth: JWT required
-   Response: { success: true }

---

## CLIENT (prefixed with /api/client)

Public endpoints (no auth):

### GET /api/products/active-count

-   Qué hace: Devuelve número de productos disponibles.
-   Método: GET
-   Response: { success: true, active_count: int }

### GET /api/orders/{id}/details

-   Qué hace: Devuelve detalle público de un pedido (items y cliente limitado).
-   Método: GET
-   Params: id (order id)
-   Response: { success: true, order: { id, status, totalAmount, deliveryAddress, paymentMethod, createdAt, client:{id,name,email,address}, items:[{productId,productName,quantity,unitPrice,total}] } | null }

Client routes (some require JWT):

### GET /api/client/products and GET /api/client/products/full

-   Qué hace: Lista productos visibles para clientes. `/full` is alias for full list. Returns pagination.
-   Método: GET
-   Query params: category (int, optional), search (string, optional), page (int, default=1), full (boolean)
-   Response: { success: true, products: [Product], pagination: { current_page, last_page, per_page, total } }
-   Product entity: { id, category: {id,name}, name, description, price, stock, imageUrl, isAvailable, createdAt, updatedAt }

### GET /api/public/products/full

-   Qué hace: Misma respuesta que `GET /api/client/products/full` pero pública (no requiere token). Lista productos visibles (`isAvailable=true`) con entidad completa (incluye `imageUrl`) y paginación.
-   Método: GET
-   Access: Public (no Authorization header required)
-   Query params: category (int, optional), search (string, optional), page (int, default=1)
-   Response: { success: true, products: [Product], pagination: { current_page, last_page, per_page, total } }
-   Product entity: { id, category: {id,name}, name, description, price, stock, imageUrl, isAvailable, createdAt, updatedAt }

### GET /api/public/categories

-   Qué hace: Devuelve todas las categorías como entidades completas. Útil para poblar menús, filtros y vistas públicas donde no se requiere autenticación.
-   Método: GET
-   Acceso: Público — NO requiere Authorization header ni token JWT.
-   Query params: ninguno
-   Response: { "success": true, "categories": [ Category ] }
    -   Category entity incluye: { id, name, description, createdAt }

### POST /api/client/orders

-   Qué hace: Crea un pedido (autenticado). Valida cart items, calcula total y crea Order + OrderItems.
-   Método: POST
-   Auth: JWT required
-   Body: { delivery_address: string, payment_method: 'Tarjeta'|'Efectivo', cart: [{ id: productId, quantity }] }
-   Response: { success: true, order_id }

### GET /api/client/categories

-   Qué hace: Lista categorías.
-   Método: GET
-   Response: { success: true, categories: [...] }

### GET /api/client/categories/{id}

-   Qué hace: Devuelve categoría + productos.
-   Método: GET
-   Response: { success: true, category: { ... , products: [...] } }

### POST /api/client/cart/details

-   Qué hace: Devuelve detalles de productos para un listado de ids (útil para revisar el carrito antes de pagar).
-   Método: POST
-   Body: { ids: [int] }
-   Response: { success: true, products: [Product] }

### GET /api/client/orders

-   Qué hace: Lista pedidos del cliente autenticado (paginated).
-   Método: GET
-   Auth: JWT required
-   Query params: page (int), per_page (int)
-   Response: { success: true, orders: [{id,status,totalAmount,createdAt}], pagination: {...} }

### GET /api/client/orders/{id}

-   Qué hace: Devuelve detalle del pedido (owner-only).
-   Método: GET
-   Auth: JWT required (owner)
-   Response: { success: true, order: { id,status,totalAmount,deliveryAddress,paymentMethod,createdAt,items:[...] } }

### POST /api/client/orders/{id}/cancel

-   Qué hace: Cancela un pedido (el cliente propietario lo cancela).
-   Método: POST
-   Auth: JWT required
-   Response: { success: true }

### POST /api/client/orders/{id}/reorder

-   Qué hace: Repite un pedido anterior (clona el pedido y sus items).
-   Método: POST
-   Auth: JWT required
-   Response: { success: true, order_id }

### GET /api/client/profile/stats

-   Qué hace: Devuelve estadísticas del perfil (total_orders, total_spent, favorite_category placeholder).
-   Método: GET
-   Auth: JWT required
-   Response: { success: true, stats: { total_orders, total_spent, favorite_category } }

### GET /api/client/orders/recent

-   Qué hace: Órdenes recientes (limit 5) del cliente.
-   Método: GET
-   Auth: JWT required
-   Response: { success: true, orders: [...] }

### GET /api/client/favorites

-   Qué hace: Devuelve productos favoritos (placeholder, retorna []).
-   Método: GET
-   Auth: JWT required
-   Response: { success: true, products: [] }

### PUT /api/client/profile

-   Qué hace: Reusa `AuthController::updateProfile`.
-   Método: PUT
-   Auth: JWT required
-   Response: { success: true }

### POST /api/client/change-password

-   Qué hace: Reusa `AuthController::changePassword`.
-   Método: POST
-   Auth: JWT required
-   Response: { success: true }

---

## SELLER (prefixed with /api/seller)

All seller endpoints require JWT (JwtAuthMiddleware).

### GET /api/seller/dashboard

-   Qué hace: Dashboard del vendedor. Devuelve `data` con pending, recent_orders (full), today_orders (simplified count per request), today_sales_total (sum of all orders), products_count.
-   Método: GET
-   Auth: JWT required (seller)
-   Response: { success: true, data: { pending:int, recent_orders:[Order], today_orders:int, today_sales_total:float, products_count:int } }
-   Note: `recent_orders` items are mapped to include client and items details.

### GET /api/seller/products/stock

-   Qué hace: Devuelve todos los productos (stock) — seller-scoped behaviour in code is global in current impl.
-   Método: GET
-   Auth: JWT required
-   Response: { success: true, products: [...] }

### GET /api/seller/products

-   Qué hace: Alias to productsStock
-   Método: GET
-   Auth: JWT required

### GET /api/seller/categories

-   Qué hace: Lista categorías (seller view)
-   Método: GET
-   Auth: JWT required

### POST /api/seller/products/{id}/availability

-   Qué hace: Toggle availability flag for a product.
-   Método: POST
-   Auth: JWT required
-   Response: { success: true }

### GET /api/seller/products/{id}/status

-   Qué hace: Devuelve availability status del producto.
-   Método: GET
-   Auth: JWT required
-   Response: { success: true, id, isAvailable }

### POST /api/seller/products/{id}/stock

-   Qué hace: Actualiza stock de un producto.
-   Método: POST
-   Auth: JWT required
-   Body: { stock: int }
-   Response: { success: true }

### POST /api/seller/products/stocks

-   Qué hace: Bulk update de stocks. Body: { items: [{id,stock}] }
-   Método: POST
-   Auth: JWT required
-   Response: { success: true }

### GET /api/seller/orders

-   Qué hace: Lista órdenes del vendedor.
-   Método: GET
-   Auth: JWT required
-   Response: { success: true, orders: [...] }

### GET /api/seller/orders/{id}

-   Qué hace: Devuelve orden por id.
-   Método: GET
-   Auth: JWT required
-   Response: { success: true, order }

### POST /api/seller/orders/{id}/status

-   Qué hace: Cambia el estado de una orden.
-   Método: POST
-   Auth: JWT required
-   Body: { newStatus }
-   Response: { success: true }

### POST /api/seller/orders/{id}/assign

-   Qué hace: Asigna vendedor a una orden.
-   Método: POST
-   Auth: JWT required
-   Body: { sellerId }
-   Response: { success: true }

---

## ADMIN (prefixed with /api/admin)

All admin endpoints require JWT and the user to have admin role (checked in controller).

### GET /api/admin/dashboard

-   Qué hace: Devuelve métricas generales (orders_count, users_count, products_count, low_stock_count, recent_orders).
-   Método: GET
-   Auth: JWT required (admin)
-   Response: { success: true, orders_count, users_count, products_count, low_stock_count, recent_orders }

### GET /api/admin/orders/stats

-   Qué hace: Agrupa órdenes por estado y devuelve counts.
-   Método: GET
-   Auth: JWT required (admin)
-   Response: { success: true, stats: { status => count } }

### GET /api/admin/vendors

-   Qué hace: Lista usuarios con rol vendedor.
-   Método: GET
-   Auth: JWT required (admin)
-   Response: { success: true, vendors: [...] }

### GET /api/admin/orders

-   Qué hace: Lista órdenes con filtros (status, vendor_id, date_from, date_to, search) y paginación.
-   Método: GET
-   Auth: JWT required (admin)
-   Query params: status, vendor_id, date_from (YYYY-MM-DD), date_to, search, page, per_page
-   Response: { success: true, orders: [...], pagination: { page, total_pages } }

### GET /api/admin/orders/{id}

-   Qué hace: Detalle de orden (admin)
-   Método: GET
-   Auth: JWT required (admin)
-   Response: { success: true, order }

### PUT /api/admin/orders/{id}/status

-   Qué hace: Actualiza estado de orden
-   Método: PUT
-   Auth: JWT required (admin)
-   Body: { status }
-   Response: { success: true }

### GET /api/admin/orders/export

-   Qué hace: Export CSV (returned as octet-stream with xlsx filename)
-   Método: GET
-   Auth: JWT required (admin)
-   Response: file download

### GET /api/admin/reports/export

-   Qué hace: Export reports CSV (placeholder)
-   Método: GET
-   Auth: JWT required (admin)
-   Response: file download

### DELETE /api/admin/orders/{id}

-   Qué hace: Borra orden por id
-   Método: DELETE
-   Auth: JWT required (admin)
-   Response: { success: true }

### GET /api/admin/products/stats

-   Qué hace: Estadísticas de productos (total, low_stock_count, inactive)
-   Método: GET
-   Auth: JWT required (admin)
-   Response: { success: true, total, low_stock_count, inactive }

### Category endpoints

-   GET /api/admin/categories -> { success:true, categories }
-   GET /api/admin/categories/{id} -> { success:true, category }
-   POST /api/admin/categories -> create (body: name, description) -> { success:true, id, name }
-   PUT /api/admin/categories/{id} -> update -> { success:true, id, name }
-   DELETE /api/admin/categories/{id} -> { success:true }

### Roles

-   GET /api/admin/roles -> { success:true, roles }
-   GET /api/admin/roles/{id} -> { success:true, role }

### Products (admin)

-   GET /api/admin/products -> filters: category, search, low_stock, page, per_page -> { success:true, products, pagination }
-   GET /api/admin/products/{id} -> { success:true, product }
-   POST /api/admin/products -> create (body: category_id,name,description,price,stock,image_url,is_available) -> { success:true, id, name }
-   PUT /api/admin/products/{id} -> update -> { success:true, id, name }
-   POST /api/admin/products/{id}/toggle-status -> toggles is_available -> { success:true }
-   DELETE /api/admin/products/{id} -> { success:true }
-   GET /api/admin/products/export -> returns file download (CSV bytes)

### Reports

-   GET /api/admin/reports -> returns placeholders for sales_by_day, sales_by_seller, top_products
-   GET /api/admin/reports/export -> file download

### Stats endpoints (admin)

Las siguientes rutas están pensadas para panel administrativo. Todas requieren autenticación (JWT) y usuario con rol admin.

GET /api/admin/stats/revenue-summary

-   Query params: date_from (YYYY-MM-DD, optional), date_to (YYYY-MM-DD, optional)
-   Propósito: Resumen numérico de ingresos (periodo actual vs anterior, crecimiento y YoY)
-   Response (mínimo recomendado):

```json
{
    "success": true,
    "data": {
        "current_revenue": 12345.67,
        "previous_revenue": 9876.54,
        "percent_change": 25.0,
        "yoy_revenue": 100000.0,
        "yoy_percent_change": 10.5
    }
}
```

GET /api/admin/stats/sales-by-day

-   Query params: date_from, date_to
-   Propósito: Serie diaria de ventas para gráficas lineales. Devuelve una entrada por día del rango (llenando con 0 si no hubo ventas).
-   Response:

```json
{
    "success": true,
    "data": {
        "sales_by_day": [
            { "fecha": "2025-10-01", "totalVentas": 123.45 },
            { "fecha": "2025-10-02", "totalVentas": 234.56 }
        ]
    }
}
```

GET /api/admin/stats/users-growth

-   Query params: date_from, date_to
-   Propósito: Nuevos usuarios por día. Devuelve array `users_growth` con fecha y cantidad.
-   Response:

```json
{
    "success": true,
    "data": {
        "users_growth": [
            { "fecha": "2025-10-01", "cantidadUsuarios": 5 },
            { "fecha": "2025-10-02", "cantidadUsuarios": 8 }
        ]
    }
}
```

GET /api/admin/stats/sales-by-seller

-   Query params: date_from, date_to
-   Propósito: Ventas agregadas por vendedor para gráfico de barras.
-   Cada elemento contiene: `vendedorId`, `vendedorNombre`, `totalVentas`.
-   Response:

```json
{
    "success": true,
    "data": {
        "sales_by_seller": [
            {
                "vendedorId": 12,
                "vendedorNombre": "Ana",
                "totalVentas": 1234.5
            },
            { "vendedorId": 15, "vendedorNombre": "Luis", "totalVentas": 987.0 }
        ]
    }
}
```

GET /api/admin/stats/top-products

-   Query params: date_from, date_to, limit (optional)
-   Propósito: Top N productos por cantidad vendida. Cada elemento: `productoId`, `productoNombre`, `cantidadVendida`.
-   Response:

```json
{
    "success": true,
    "data": {
        "top_products": [
            {
                "productoId": 101,
                "productoNombre": "Pizza Margarita",
                "cantidadVendida": 120
            },
            {
                "productoId": 202,
                "productoNombre": "Ensalada César",
                "cantidadVendida": 88
            }
        ]
    }
}
```

GET /api/admin/stats/orders-by-status

-   Query params: date_from, date_to
-   Propósito: Distribución de pedidos por estado (pie chart). Cada elemento: `status`, `count`.
-   Response:

```json
{
    "success": true,
    "data": {
        "orders_by_status": [
            { "status": "Pendiente", "count": 10 },
            { "status": "Entregado", "count": 50 }
        ]
    }
}
```

GET /api/admin/stats/orders-period

-   Query params: date_from, date_to, granularity (optional: daily|weekly|monthly)
-   Propósito: Serie de pedidos por periodo + métricas comparativas (current/previous/percent change)
-   Response (forma ideal):

```json
{
	"success": true,
	"data": {
		"series": [ { "label": "2025-10-01", "count": 5 }, ... ],
		"current_total": 100,
		"previous_total": 75,
		"percent_change": 33.333,
		"granularity": "daily"
	}
}
```

GET /api/admin/stats/rates

-   Query params: date_from, date_to
-   Propósito: Tasas (confirmación, cierre, cancelación). El frontend acepta valores numéricos o objetos con `value`/`rate`.
-   Response:

```json
{
    "success": true,
    "data": {
        "confirmation_rate": 0.75,
        "closure_rate": 0.5,
        "cancellation_rate": 0.1
    }
}
```

GET /api/admin/stats/revenue-by-segment

-   Query params: date_from, date_to
-   Propósito: Desglose de ingresos por vendedor / canal / categoría.
-   Response ejemplo:

```json
{
    "success": true,
    "data": {
        "by_seller": [
            { "vendedorId": 12, "vendedorNombre": "Ana", "totalVentas": 1234.5 }
        ],
        "by_channel": [{ "label": "Tarjeta", "count": 200, "total": 12345.6 }],
        "by_category": [{ "label": "Bebidas", "count": 300, "total": 4321.2 }]
    }
}
```

GET /api/admin/stats/top-clients

-   Query params: date_from, date_to, limit (optional)
-   Propósito: Clientes con más pedidos / importe. Elementos con `label` (cliente), `count` (número de pedidos) y `total` (importe).
-   Response ejemplo:

```json
{
    "success": true,
    "data": {
        "top_clients": [
            { "label": "Juan Pérez", "count": 12, "total": 1200.5 },
            { "label": "María López", "count": 8, "total": 900.0 }
        ]
    }
}
```

Formato de fallo recomendado:

```json
{ "success": false, "message": "Descripción del error" }
```

Si quieres que convierta esto a una sección OpenAPI parcial / JSON para compartir con frontend devs, puedo generarlo a continuación.

### GET /api/admin/stats/seller-sales-total

-   Query params: seller_id (required), date (YYYY-MM-DD, optional)
-   Propósito: Devuelve el total monetario (suma de total_amount) de un vendedor específico en la fecha indicada. Se excluyen pedidos con estado `Cancelado`; el resto de estados se cuentan en el total.
-   Response ejemplo:

```json
{
    "success": true,
    "total": 1234.5
}
```

Notas:

-   Si no se pasa `date`, se usa el día actual.
-   `seller_id` puede pasarse también como `vendedorId`.

### GET /api/admin/stats/seller-delivered-count

-   Query params: seller_id (required), date (YYYY-MM-DD, optional)
-   Propósito: Devuelve la cantidad numérica de pedidos entregados (`Entregado`) para un vendedor en la fecha indicada.
-   Response ejemplo:

```json
{
    "success": true,
    "count": 5
}
```

Notas:

-   Si no se pasa `date`, se usa el día actual.
-   Si `seller_id` no se suministra, el endpoint responde con 400 y un mensaje de error.

### Users management

-   GET /api/admin/users -> paginated list of users (page, per_page)
-   POST /api/admin/users -> create user (body: name,email,password,address,role_id,is_active) -> returns created user id
-   PUT /api/admin/users/{id} -> update user fields
-   POST /api/admin/users/{id}/status -> toggle user active status (body: status = 'active' or other) -> { success:true }
-   DELETE /api/admin/users/{id} -> { success:true }

---

## Additional notes / conventions

-   Dates in responses are normally the raw `created_at`/`updated_at` from DB. In some mapped endpoints the controller converts to ISO UTC strings (example: seller recent orders mapping uses `Y-m-dTH:i:sZ`).
-   Currency fields are numeric floats (e.g. `totalAmount`). For financial criticalness use integer cents or decimal/BigDecimal in a production system.
-   Authentication middleware: routes that require authentication use `\App\Http\Middleware\JwtAuthMiddleware::class` in routes file.

---

If you want, I can:

-   Add example request/response bodies for a few key endpoints.
-   Generate a machine-readable OpenAPI (partial) from these routes.
-   Save a shorter summary (CSV or JSON) for consumption by frontend devs.
