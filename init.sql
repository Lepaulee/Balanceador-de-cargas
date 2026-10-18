-- 1. Usuario para la replicación (Maestro-Esclavo)
CREATE USER IF NOT EXISTS 'replicador'@'%' IDENTIFIED BY 'repl123';
GRANT REPLICATION SLAVE ON *.* TO 'replicador'@'%';

-- 2. Base de datos de la tienda
CREATE DATABASE IF NOT EXISTS ecommerce;
USE ecommerce;

-- 3. Tablas oficiales
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100),
    rol VARCHAR(50),
    email VARCHAR(100) UNIQUE,
    password VARCHAR(255),
    secret VARCHAR(255) DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS catalogo_muebles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    articulo VARCHAR(100),
    precio DECIMAL(10,2),
    stock INT
);

CREATE TABLE IF NOT EXISTS catalogo_zapateria (
    id INT AUTO_INCREMENT PRIMARY KEY,
    modelo VARCHAR(100),
    tALLA VARCHAR(10),
    precio DECIMAL(10,2),
    stock INT
);

-- 4. Datos de prueba iniciales
INSERT IGNORE INTO usuarios (nombre, rol, email, password)
VALUES ('Prueba Final CETI', 'Usu', 'ceti@final.com', 'secreta123');

INSERT INTO catalogo_muebles (articulo, precio, stock) VALUES ('Silla Gamer Ejecutiva', 2850.50, 15);
INSERT INTO catalogo_zapateria (modelo, tALLA, precio, stock) VALUES ('Tenis Deportivos Running', '27.5', 1200.00, 20);

-- 5. Usuario de la aplicación con acceso desde cualquier contenedor (web1, web2)
CREATE USER IF NOT EXISTS 'app_user'@'%' IDENTIFIED BY 'PasswordSeguro123!';
GRANT ALL PRIVILEGES ON ecommerce.* TO 'app_user'@'%';

FLUSH PRIVILEGES;
