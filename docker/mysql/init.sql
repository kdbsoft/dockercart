-- MySQL initialization script for DockerCart
-- This script runs automatically when the container starts for the first time

-- Set character set and collation
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- Create database if not exists (already created by docker-compose env vars)
-- CREATE DATABASE IF NOT EXISTS dockercart CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Grant privileges (already done by docker-compose env vars)
-- GRANT ALL PRIVILEGES ON dockercart.* TO 'dockercart'@'%';
-- FLUSH PRIVILEGES;

-- Optional: Set timezone
SET time_zone = '+00:00';
