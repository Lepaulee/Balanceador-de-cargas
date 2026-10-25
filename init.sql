-- 1. Usuario para la replicación (Maestro-Esclavo)
CREATE USER IF NOT EXISTS 'replicador'@'%' IDENTIFIED BY 'repl123';
GRANT REPLICATION SLAVE ON *.* TO 'replicador'@'%';

-- 2. Base de datos de la tienda
CREATE DATABASE IF NOT EXISTS ecommerce;
USE ecommerce;

-- 3. Tablas oficiales
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) DEFAULT NULL,
    rol VARCHAR(50) DEFAULT NULL,
    email VARCHAR(100) DEFAULT NULL UNIQUE,
    password VARCHAR(255) DEFAULT NULL,
    secret VARCHAR(255) DEFAULT NULL,
    confirmado TINYINT(1) DEFAULT '0',
    token_confirmacion VARCHAR(64) DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS catalogo_muebles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    articulo VARCHAR(100) DEFAULT NULL,
    precio DECIMAL(10,2) DEFAULT NULL,
    stock INT DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS catalogo_zapateria (
    id INT AUTO_INCREMENT PRIMARY KEY,
    modelo VARCHAR(100) DEFAULT NULL,
    tALLA VARCHAR(10) DEFAULT NULL,
    precio DECIMAL(10,2) DEFAULT NULL,
    stock INT DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS ventas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    total DECIMAL(10,2) NOT NULL,
    fecha DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS detalle_ventas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    venta_id INT NOT NULL,
    producto VARCHAR(255) NOT NULL,
    cantidad INT NOT NULL,
    precio DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL
);

-- 4. Datos de prueba iniciales
INSERT INTO usuarios (id, nombre, rol, email, password, secret, confirmado) VALUES 
(1, 'Prueba Final CETI', 'admin', 'paulo291204@gmail.com', 'secreta123', 'CZYFSBPHX7VO2AAJ6Y3T5UDYYEI73XYJ', 1),
(2, 'juan sanchez', 'usuario', 'juan@ceti.mx', '$2y$10$nhjWATiWD5aqh8fKcDx5nOavpVOh5G72g8UUNIuATsU34dy/7SZ9C', 'SIYMWFDUKA52VAN5R7RQ2L257GF453KE', 1),
(3, 'Paulo Gonzalez', 'usuario', 'a23310011@ceti.mx', '$2y$10$hxeO462I4MD45.qv8kSt6ugG9ThAWvqwjTvJNsngvwmQ9EkBszqNC', 'N/A', 1)
ON DUPLICATE KEY UPDATE rol='admin', email='paulo291204@gmail.com';

INSERT INTO catalogo_muebles (id, articulo, precio, stock) VALUES 
(1, 'Silla Gamer Ejecutiva', 2850.50, 11)
ON DUPLICATE KEY UPDATE stock=11;

INSERT INTO catalogo_zapateria (id, modelo, tALLA, precio, stock) VALUES 
(1, 'Tenis Deportivos Running', '27.5', 1200.00, 20)
ON DUPLICATE KEY UPDATE stock=20;

-- 5. Usuario de la aplicación con acceso desde cualquier contenedor (web1, web2)
CREATE USER IF NOT EXISTS 'app_user'@'%' IDENTIFIED BY 'PasswordSeguro123!';
GRANT ALL PRIVILEGES ON ecommerce.* TO 'app_user'@'%';
FLUSH PRIVILEGES;
