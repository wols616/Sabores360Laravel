-- Sistema de Gestión de Pedidos de Comida - Script de Base de Datos
-- Autor: Perplexity AI
-- Motor: InnoDB (para soporte de transacciones y claves foráneas)

-- Borrado de tablas en orden inverso para evitar conflictos de claves foráneas
DROP TABLE IF EXISTS `password_resets`;
DROP TABLE IF EXISTS `order_status_history`;
DROP TABLE IF EXISTS `order_items`;
DROP TABLE IF EXISTS `orders`;
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `roles`;

-- Creación de la tabla de Roles
-- Almacena los roles disponibles en el sistema (Administrador, Vendedor, Cliente).
CREATE TABLE `roles` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(50) NOT NULL UNIQUE COMMENT 'Nombre del rol (admin, vendedor, cliente)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Creación de la tabla de Usuarios
-- Almacena la información de todos los usuarios, independientemente de su rol.
CREATE TABLE `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `role_id` INT NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL COMMENT 'Contraseña hasheada',
    `address` TEXT NULL COMMENT 'Dirección principal del cliente',
    `is_active` BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Indica si la cuenta está activa o desactivada',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Creación de la tabla de Categorías de productos
-- Organiza los productos en categorías como "Bebidas", "Pizzas", "Postres", etc.
CREATE TABLE `categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE,
    `description` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Creación de la tabla de Productos
-- Contiene toda la información sobre los productos que se venden.
CREATE TABLE `products` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `category_id` INT NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `price` DECIMAL(10, 2) NOT NULL CHECK (`price` >= 0),
    `stock` INT NOT NULL DEFAULT 0 CHECK (`stock` >= 0),
    `image_url` VARCHAR(255) NULL COMMENT 'URL de la imagen del producto',
    `is_available` BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Disponibilidad del producto',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Creación de la tabla de Pedidos
-- Almacena la información de cada pedido realizado.
CREATE TABLE `orders` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `client_id` INT NOT NULL,
    `seller_id` INT NULL COMMENT 'Vendedor asignado al pedido, puede ser nulo inicialmente',
    `delivery_address` TEXT NOT NULL COMMENT 'Dirección de entrega específica para este pedido',
    `total_amount` DECIMAL(10, 2) NOT NULL,
    `status` ENUM('Pendiente', 'Confirmado', 'En preparación', 'En camino', 'Entregado', 'Cancelado') NOT NULL DEFAULT 'Pendiente',
    `payment_method` ENUM('Tarjeta', 'Efectivo') NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`client_id`) REFERENCES `users`(`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`seller_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Creación de la tabla de Detalle de Pedidos (tabla pivote)
-- Relaciona los productos y las cantidades con un pedido específico.
CREATE TABLE `order_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT NOT NULL,
    `product_id` INT NOT NULL,
    `quantity` INT NOT NULL,
    `unit_price` DECIMAL(10, 2) NOT NULL COMMENT 'Precio del producto al momento de la compra',
    FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Creación de la tabla de Historial de Estados del Pedido
-- Registra cada cambio de estado de un pedido para seguimiento.
CREATE TABLE `order_status_history` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT NOT NULL,
    `status` ENUM('Pendiente', 'Confirmado', 'En preparación', 'En camino', 'Entregado', 'Cancelado') NOT NULL,
    `changed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `notes` TEXT NULL COMMENT 'Notas adicionales sobre el cambio de estado',
    FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Creación de la tabla de Reseteo de Contraseñas
-- Almacena tokens temporales para la funcionalidad de "recuperar contraseña".
CREATE TABLE `password_resets` (
    `email` VARCHAR(255) PRIMARY KEY,
    `token` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Inserción de datos iniciales
-- Poblar la tabla de roles para que el sistema sea funcional desde el inicio.
INSERT INTO `roles` (`id`, `name`) VALUES
(1, 'Administrador'),
(2, 'Vendedor'),
(3, 'Cliente');

-- Crear un usuario administrador por defecto para poder acceder al sistema.
-- ¡RECUERDA CAMBIAR LA CONTRASEÑA EN UN ENTORNO DE PRODUCCIÓN!
-- La contraseña de ejemplo es 'admin123'. Deberías usar una herramienta de hashing (como bcrypt) en tu aplicación.
INSERT INTO `users` (`role_id`, `name`, `email`, `password_hash`) VALUES
(1, 'Admin Principal', 'admin@ejemplo.com', '$2y$10$xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx');

-- Poblar categorías de ejemplo
INSERT INTO `categories` (`name`, `description`) VALUES
('Pizzas', 'Pizzas artesanales con ingredientes frescos.'),
('Hamburguesas', 'Hamburguesas de carne de res y opciones vegetarianas.'),
('Bebidas', 'Refrescos, jugos naturales y agua.'),
('Postres', 'Dulces para terminar tu comida.');

-- Poblar productos de ejemplo
INSERT INTO `products` (`category_id`, `name`, `description`, `price`, `stock`, `is_available`) VALUES
(1, 'Pizza Margarita', 'Salsa de tomate, mozzarella y albahaca fresca.', 12.50, 50, TRUE),
(1, 'Pizza Pepperoni', 'La clásica pizza de pepperoni con extra queso.', 14.00, 40, TRUE),
(2, 'Hamburguesa Clásica', 'Carne de res, lechuga, tomate, cebolla y pepinillos.', 9.75, 30, TRUE),
(3, 'Refresco de Cola', 'Lata de 330ml.', 2.50, 100, TRUE);

COMMIT;

