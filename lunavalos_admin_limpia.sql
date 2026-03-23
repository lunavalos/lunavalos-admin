-- MySQL dump 10.13  Distrib 9.6.0, for macos26.2 (arm64)
--
-- Host: 127.0.0.1    Database: lunavalos_admin
-- ------------------------------------------------------
-- Server version	9.6.0

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
-- SET @MYSQLDUMP_TEMP_LOG_BIN = @@SESSION.SQL_LOG_BIN;
-- SET @@SESSION.SQL_LOG_BIN= 0;

--
-- GTID state at the beginning of the backup 
--

-- SET @@GLOBAL.GTID_PURGED=/*!80000 '+'*/ '2e92753a-0961-11f1-9f35-f585b70802f2:1-4014';

--
-- Table structure for table `agencies`
--

DROP TABLE IF EXISTS `agencies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `agencies` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `agencies_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `agencies`
--

LOCK TABLES `agencies` WRITE;
/*!40000 ALTER TABLE `agencies` DISABLE KEYS */;
/*!40000 ALTER TABLE `agencies` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache`
--

LOCK TABLES `cache` WRITE;
/*!40000 ALTER TABLE `cache` DISABLE KEYS */;
INSERT INTO `cache` VALUES ('lunavalos-digital-house-cache-spatie.permission.cache','a:3:{s:5:\"alias\";a:4:{s:1:\"a\";s:2:\"id\";s:1:\"b\";s:4:\"name\";s:1:\"c\";s:10:\"guard_name\";s:1:\"r\";s:5:\"roles\";}s:11:\"permissions\";a:32:{i:0;a:4:{s:1:\"a\";i:1;s:1:\"b\";s:13:\"Ver Dashboard\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:5:{i:0;i:1;i:1;i:2;i:2;i:4;i:3;i:5;i:4;i:6;}}i:1;a:4:{s:1:\"a\";i:2;s:1:\"b\";s:15:\"Crear Dashboard\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:1:{i:0;i:1;}}i:2;a:4:{s:1:\"a\";i:3;s:1:\"b\";s:16:\"Editar Dashboard\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:1:{i:0;i:1;}}i:3;a:4:{s:1:\"a\";i:4;s:1:\"b\";s:18:\"Eliminar Dashboard\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:1:{i:0;i:1;}}i:4;a:4:{s:1:\"a\";i:5;s:1:\"b\";s:9:\"Ver Roles\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:1;i:1;i:2;i:2;i:5;}}i:5;a:4:{s:1:\"a\";i:6;s:1:\"b\";s:11:\"Crear Roles\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:1;i:1;i:2;i:2;i:5;}}i:6;a:4:{s:1:\"a\";i:7;s:1:\"b\";s:12:\"Editar Roles\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:1;i:1;i:2;i:2;i:5;}}i:7;a:4:{s:1:\"a\";i:8;s:1:\"b\";s:14:\"Eliminar Roles\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:1;i:1;i:2;i:2;i:5;}}i:8;a:4:{s:1:\"a\";i:9;s:1:\"b\";s:12:\"Ver Usuarios\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:1;i:1;i:2;i:2;i:5;}}i:9;a:4:{s:1:\"a\";i:10;s:1:\"b\";s:14:\"Crear Usuarios\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:1;i:1;i:2;i:2;i:5;}}i:10;a:4:{s:1:\"a\";i:11;s:1:\"b\";s:15:\"Editar Usuarios\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:1;i:1;i:2;i:2;i:5;}}i:11;a:4:{s:1:\"a\";i:12;s:1:\"b\";s:17:\"Eliminar Usuarios\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:1;i:1;i:2;i:2;i:5;}}i:12;a:4:{s:1:\"a\";i:13;s:1:\"b\";s:13:\"Ver Servicios\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:4:{i:0;i:1;i:1;i:2;i:2;i:4;i:3;i:6;}}i:13;a:4:{s:1:\"a\";i:14;s:1:\"b\";s:15:\"Crear Servicios\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:1;i:1;i:2;}}i:14;a:4:{s:1:\"a\";i:15;s:1:\"b\";s:16:\"Editar Servicios\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:1;i:1;i:2;}}i:15;a:4:{s:1:\"a\";i:16;s:1:\"b\";s:18:\"Eliminar Servicios\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:1;i:1;i:2;}}i:16;a:4:{s:1:\"a\";i:17;s:1:\"b\";s:16:\"Ver Cotizaciones\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:1;i:1;i:2;}}i:17;a:4:{s:1:\"a\";i:18;s:1:\"b\";s:18:\"Crear Cotizaciones\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:1;i:1;i:2;}}i:18;a:4:{s:1:\"a\";i:19;s:1:\"b\";s:19:\"Editar Cotizaciones\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:1;i:1;i:2;}}i:19;a:4:{s:1:\"a\";i:20;s:1:\"b\";s:21:\"Eliminar Cotizaciones\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:1;i:1;i:2;}}i:20;a:4:{s:1:\"a\";i:21;s:1:\"b\";s:12:\"Ver Clientes\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:4:{i:0;i:1;i:1;i:2;i:2;i:4;i:3;i:6;}}i:21;a:4:{s:1:\"a\";i:22;s:1:\"b\";s:14:\"Crear Clientes\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:1;i:1;i:2;}}i:22;a:4:{s:1:\"a\";i:23;s:1:\"b\";s:15:\"Editar Clientes\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:1;i:1;i:2;}}i:23;a:4:{s:1:\"a\";i:24;s:1:\"b\";s:17:\"Eliminar Clientes\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:2:{i:0;i:1;i:1;i:2;}}i:24;a:4:{s:1:\"a\";i:29;s:1:\"b\";s:11:\"Ver Tickets\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:5:{i:0;i:1;i:1;i:2;i:2;i:3;i:3;i:4;i:4;i:6;}}i:25;a:4:{s:1:\"a\";i:30;s:1:\"b\";s:13:\"Crear Tickets\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:4:{i:0;i:1;i:1;i:2;i:2;i:3;i:3;i:6;}}i:26;a:4:{s:1:\"a\";i:31;s:1:\"b\";s:14:\"Editar Tickets\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:5:{i:0;i:1;i:1;i:2;i:2;i:3;i:3;i:4;i:4;i:6;}}i:27;a:4:{s:1:\"a\";i:32;s:1:\"b\";s:16:\"Eliminar Tickets\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:1;i:1;i:2;i:2;i:3;}}i:28;a:4:{s:1:\"a\";i:33;s:1:\"b\";s:8:\"Ver RRHH\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:1;i:1;i:2;i:2;i:5;}}i:29;a:4:{s:1:\"a\";i:34;s:1:\"b\";s:19:\"Gestionar Empleados\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:1;i:1;i:2;i:2;i:5;}}i:30;a:4:{s:1:\"a\";i:35;s:1:\"b\";s:18:\"Gestionar Salarios\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:1;i:1;i:2;i:2;i:5;}}i:31;a:4:{s:1:\"a\";i:36;s:1:\"b\";s:25:\"Gestionar Documentos RRHH\";s:1:\"c\";s:3:\"web\";s:1:\"r\";a:3:{i:0;i:1;i:1;i:2;i:2;i:5;}}}s:5:\"roles\";a:6:{i:0;a:3:{s:1:\"a\";i:1;s:1:\"b\";s:20:\"Administrador Master\";s:1:\"c\";s:3:\"web\";}i:1;a:3:{s:1:\"a\";i:2;s:1:\"b\";s:13:\"Administrador\";s:1:\"c\";s:3:\"web\";}i:2;a:3:{s:1:\"a\";i:4;s:1:\"b\";s:13:\"Web Developer\";s:1:\"c\";s:3:\"web\";}i:3;a:3:{s:1:\"a\";i:5;s:1:\"b\";s:4:\"RRHH\";s:1:\"c\";s:3:\"web\";}i:4;a:3:{s:1:\"a\";i:6;s:1:\"b\";s:8:\"Designer\";s:1:\"c\";s:3:\"web\";}i:5;a:3:{s:1:\"a\";i:3;s:1:\"b\";s:7:\"Cliente\";s:1:\"c\";s:3:\"web\";}}}',1774032009);
/*!40000 ALTER TABLE `cache` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cache_locks`
--

LOCK TABLES `cache_locks` WRITE;
/*!40000 ALTER TABLE `cache_locks` DISABLE KEYS */;
/*!40000 ALTER TABLE `cache_locks` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `clients`
--

DROP TABLE IF EXISTS `clients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `clients` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `business_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `agency_source` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contract_date` date DEFAULT NULL,
  `initial_price` decimal(12,2) DEFAULT NULL,
  `next_renewal_date` date DEFAULT NULL,
  `renewal_amount` decimal(12,2) DEFAULT NULL,
  `package_services` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `auto_renew_notice` tinyint(1) NOT NULL DEFAULT '1',
  `domain_names` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `domain_provider` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hosting_provider` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_provider` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `has_custom_email_config` tinyint(1) NOT NULL DEFAULT '0',
  `imap_host` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `imap_port` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `imap_tls` tinyint(1) NOT NULL DEFAULT '1',
  `pop_host` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pop_port` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pop_tls` tinyint(1) NOT NULL DEFAULT '1',
  `smtp_host` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `smtp_port` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `smtp_tls` tinyint(1) NOT NULL DEFAULT '1',
  `login_credentials` text COLLATE utf8mb4_unicode_ci,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `quote_id` bigint unsigned DEFAULT NULL,
  `quote_file_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contract_file_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `branding_file_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `receipt_file_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `clients_user_id_foreign` (`user_id`),
  KEY `clients_quote_id_foreign` (`quote_id`),
  CONSTRAINT `clients_quote_id_foreign` FOREIGN KEY (`quote_id`) REFERENCES `quotes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `clients_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `clients`
--

LOCK TABLES `clients` WRITE;
/*!40000 ALTER TABLE `clients` DISABLE KEYS */;
/*!40000 ALTER TABLE `clients` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contracts`
--

DROP TABLE IF EXISTS `contracts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contracts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `quote_id` bigint unsigned NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `legal_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fiscal_address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `postal_code` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `legal_representative` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `csf_file_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `signed_at` timestamp NULL DEFAULT NULL,
  `signature_ip` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pdf_file_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `contracts_token_unique` (`token`),
  KEY `contracts_quote_id_foreign` (`quote_id`),
  CONSTRAINT `contracts_quote_id_foreign` FOREIGN KEY (`quote_id`) REFERENCES `quotes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contracts`
--

LOCK TABLES `contracts` WRITE;
/*!40000 ALTER TABLE `contracts` DISABLE KEYS */;
/*!40000 ALTER TABLE `contracts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `employee_documents`
--

DROP TABLE IF EXISTS `employee_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `employee_documents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` bigint unsigned NOT NULL,
  `file_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `document_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `employee_documents_employee_id_foreign` (`employee_id`),
  CONSTRAINT `employee_documents_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `employee_documents`
--

LOCK TABLES `employee_documents` WRITE;
/*!40000 ALTER TABLE `employee_documents` DISABLE KEYS */;
/*!40000 ALTER TABLE `employee_documents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `employees`
--

DROP TABLE IF EXISTS `employees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `employees` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned DEFAULT NULL,
  `employee_number` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `curp` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nss` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rfc` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci,
  `position` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `department` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `join_date` date DEFAULT NULL,
  `initial_salary` decimal(15,2) NOT NULL DEFAULT '0.00',
  `current_salary` decimal(15,2) NOT NULL DEFAULT '0.00',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Activo',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `employees_employee_number_unique` (`employee_number`),
  KEY `employees_user_id_foreign` (`user_id`),
  CONSTRAINT `employees_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `employees`
--

LOCK TABLES `employees` WRITE;
/*!40000 ALTER TABLE `employees` DISABLE KEYS */;
INSERT INTO `employees` VALUES (1,1,'LUN-001','844-293-2253','LUCH880629HCLNZG02','00744438','LUCH880629Q24','Gallo #118, Col. Las Maravillas, Saltillo Coahuila. CP. 25019','CEO','Dev Ops','2026-03-19',15000.00,15000.00,'Activo',NULL,'2026-03-20 01:48:09','2026-03-20 01:48:09');
/*!40000 ALTER TABLE `employees` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `failed_jobs`
--

LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `job_batches`
--

LOCK TABLES `job_batches` WRITE;
/*!40000 ALTER TABLE `job_batches` DISABLE KEYS */;
/*!40000 ALTER TABLE `job_batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jobs`
--

LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=51 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES (1,'0001_01_01_000000_create_users_table',1),(2,'0001_01_01_000001_create_cache_table',1),(3,'0001_01_01_000002_create_jobs_table',1),(4,'2026_03_03_030948_create_permission_tables',1),(5,'2026_03_03_050650_create_services_table',2),(6,'2026_03_03_050701_create_quotes_table',2),(7,'2026_03_03_050710_create_quote_items_table',2),(8,'2026_03_03_060035_add_duration_to_quotes_table',3),(9,'2026_03_03_063243_add_payment_terms_to_quotes_table',4),(10,'2026_03_03_070009_create_clients_table',5),(11,'2026_03_03_154148_create_kanban_columns_table',6),(12,'2026_03_03_154147_create_tasks_table',7),(13,'2026_03_03_154148_create_task_attachments_table',8),(14,'2026_03_03_161945_add_priority_to_tasks_table',9),(15,'2026_03_03_164332_add_user_id_to_clients_table',10),(16,'2026_03_03_164715_create_tickets_table',11),(17,'2026_03_03_191400_create_ticket_attachments_table',12),(18,'2026_03_03_194543_add_is_approved_by_client_to_tickets_table',13),(19,'2026_03_04_031121_add_contact_and_status_to_quotes_table',14),(20,'2026_03_04_031122_add_files_and_quote_id_to_clients_table',14),(21,'2026_03_04_040858_add_is_package_to_services_table',15),(22,'2026_03_04_040859_create_package_service_table',15),(23,'2026_03_04_154130_add_renewal_price_to_services_table',16),(24,'2026_03_04_165809_add_initial_price_and_receipt_file_to_clients_table',17),(25,'2026_03_04_183138_create_settings_table',18),(26,'2026_03_04_183144_create_contracts_table',18),(27,'2026_03_05_233045_create_agencies_table',19),(28,'2026_03_06_044910_create_ticket_messages_table',20),(29,'2026_03_06_050050_create_ticket_message_attachments_table',21),(30,'2026_03_06_052100_add_is_archived_to_tasks_table',22),(31,'2026_03_06_052809_add_completed_at_to_tasks_table',23),(32,'2026_03_06_054537_create_employees_table',24),(33,'2026_03_06_054538_create_employee_documents_table',25),(34,'2026_03_06_054539_create_salary_histories_table',25),(35,'2026_03_06_054540_create_payroll_receipts_table',25),(36,'2026_03_06_060447_add_created_by_to_payroll_receipts_table',26),(37,'2026_03_06_183505_add_rejection_and_force_close_to_tickets_table',27),(38,'2026_03_07_025646_create_tickets_table',28),(39,'2026_03_07_025647_create_ticket_messages_table',28),(40,'2026_03_07_030621_create_ticket_attachments_table',29),(41,'2026_03_07_052749_create_notifications_table',30),(42,'2026_03_10_051440_add_email_settings_to_clients_table',31),(43,'2026_03_10_053123_add_has_custom_email_config_to_clients_table',32),(44,'2026_03_10_055741_create_signature_templates_table',33),(45,'2026_03_12_155447_add_is_multiple_choice_to_quotes_table',34),(46,'2026_03_12_185548_create_service_costs_table',35),(47,'2026_03_12_200040_add_service_id_to_quote_items_table',36),(48,'2026_03_12_200411_create_quote_item_costs_table',37),(49,'2026_03_19_183155_add_status_updated_at_to_tickets_table',38),(50,'2026_03_19_183837_make_user_id_nullable_in_ticket_messages',39);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `model_has_permissions`
--

DROP TABLE IF EXISTS `model_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `model_has_permissions` (
  `permission_id` bigint unsigned NOT NULL,
  `model_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`model_id`,`model_type`),
  KEY `model_has_permissions_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `model_has_permissions`
--

LOCK TABLES `model_has_permissions` WRITE;
/*!40000 ALTER TABLE `model_has_permissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `model_has_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `model_has_roles`
--

DROP TABLE IF EXISTS `model_has_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `model_has_roles` (
  `role_id` bigint unsigned NOT NULL,
  `model_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`model_id`,`model_type`),
  KEY `model_has_roles_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `model_has_roles`
--

LOCK TABLES `model_has_roles` WRITE;
/*!40000 ALTER TABLE `model_has_roles` DISABLE KEYS */;
INSERT INTO `model_has_roles` VALUES (2,'App\\Models\\User',1),(2,'App\\Models\\User',2),(4,'App\\Models\\User',3),(3,'App\\Models\\User',5),(3,'App\\Models\\User',6),(3,'App\\Models\\User',7),(5,'App\\Models\\User',8);
/*!40000 ALTER TABLE `model_has_roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notifiable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notifiable_id` bigint unsigned NOT NULL,
  `data` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notifications_notifiable_type_notifiable_id_index` (`notifiable_type`,`notifiable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `package_service`
--

DROP TABLE IF EXISTS `package_service`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `package_service` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `package_id` bigint unsigned NOT NULL,
  `service_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `package_service_package_id_foreign` (`package_id`),
  KEY `package_service_service_id_foreign` (`service_id`),
  CONSTRAINT `package_service_package_id_foreign` FOREIGN KEY (`package_id`) REFERENCES `services` (`id`) ON DELETE CASCADE,
  CONSTRAINT `package_service_service_id_foreign` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `package_service`
--

LOCK TABLES `package_service` WRITE;
/*!40000 ALTER TABLE `package_service` DISABLE KEYS */;
/*!40000 ALTER TABLE `package_service` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_reset_tokens`
--

LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payroll_receipts`
--

DROP TABLE IF EXISTS `payroll_receipts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payroll_receipts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` bigint unsigned NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `payment_date` date NOT NULL,
  `base_salary` decimal(15,2) NOT NULL,
  `bonus` decimal(15,2) NOT NULL DEFAULT '0.00',
  `deductions` decimal(15,2) NOT NULL DEFAULT '0.00',
  `net_total` decimal(15,2) NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Pendiente',
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payroll_receipts_employee_id_foreign` (`employee_id`),
  KEY `payroll_receipts_created_by_foreign` (`created_by`),
  CONSTRAINT `payroll_receipts_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payroll_receipts_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payroll_receipts`
--

LOCK TABLES `payroll_receipts` WRITE;
/*!40000 ALTER TABLE `payroll_receipts` DISABLE KEYS */;
/*!40000 ALTER TABLE `payroll_receipts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `permissions`
--

DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `permissions`
--

LOCK TABLES `permissions` WRITE;
/*!40000 ALTER TABLE `permissions` DISABLE KEYS */;
INSERT INTO `permissions` VALUES (1,'Ver Dashboard','web','2026-03-03 22:59:52','2026-03-03 22:59:52'),(2,'Crear Dashboard','web','2026-03-03 22:59:52','2026-03-03 22:59:52'),(3,'Editar Dashboard','web','2026-03-03 22:59:52','2026-03-03 22:59:52'),(4,'Eliminar Dashboard','web','2026-03-03 22:59:52','2026-03-03 22:59:52'),(5,'Ver Roles','web','2026-03-03 22:59:52','2026-03-03 22:59:52'),(6,'Crear Roles','web','2026-03-03 22:59:52','2026-03-03 22:59:52'),(7,'Editar Roles','web','2026-03-03 22:59:52','2026-03-03 22:59:52'),(8,'Eliminar Roles','web','2026-03-03 22:59:52','2026-03-03 22:59:52'),(9,'Ver Usuarios','web','2026-03-03 22:59:52','2026-03-03 22:59:52'),(10,'Crear Usuarios','web','2026-03-03 22:59:52','2026-03-03 22:59:52'),(11,'Editar Usuarios','web','2026-03-03 22:59:52','2026-03-03 22:59:52'),(12,'Eliminar Usuarios','web','2026-03-03 22:59:52','2026-03-03 22:59:52'),(13,'Ver Servicios','web','2026-03-03 22:59:52','2026-03-03 22:59:52'),(14,'Crear Servicios','web','2026-03-03 22:59:52','2026-03-03 22:59:52'),(15,'Editar Servicios','web','2026-03-03 22:59:52','2026-03-03 22:59:52'),(16,'Eliminar Servicios','web','2026-03-03 22:59:52','2026-03-03 22:59:52'),(17,'Ver Cotizaciones','web','2026-03-03 22:59:52','2026-03-03 22:59:52'),(18,'Crear Cotizaciones','web','2026-03-03 22:59:52','2026-03-03 22:59:52'),(19,'Editar Cotizaciones','web','2026-03-03 22:59:52','2026-03-03 22:59:52'),(20,'Eliminar Cotizaciones','web','2026-03-03 22:59:52','2026-03-03 22:59:52'),(21,'Ver Clientes','web','2026-03-03 22:59:52','2026-03-03 22:59:52'),(22,'Crear Clientes','web','2026-03-03 22:59:52','2026-03-03 22:59:52'),(23,'Editar Clientes','web','2026-03-03 22:59:52','2026-03-03 22:59:52'),(24,'Eliminar Clientes','web','2026-03-03 22:59:52','2026-03-03 22:59:52'),(29,'Ver Tickets','web','2026-03-03 22:59:52','2026-03-03 22:59:52'),(30,'Crear Tickets','web','2026-03-03 22:59:52','2026-03-03 22:59:52'),(31,'Editar Tickets','web','2026-03-03 22:59:52','2026-03-03 22:59:52'),(32,'Eliminar Tickets','web','2026-03-03 22:59:52','2026-03-03 22:59:52'),(33,'Ver RRHH','web','2026-03-06 11:50:52','2026-03-06 11:50:52'),(34,'Gestionar Empleados','web','2026-03-06 11:50:52','2026-03-06 11:50:52'),(35,'Gestionar Salarios','web','2026-03-06 11:50:52','2026-03-06 11:50:52'),(36,'Gestionar Documentos RRHH','web','2026-03-06 11:50:52','2026-03-06 11:50:52');
/*!40000 ALTER TABLE `permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quote_item_costs`
--

DROP TABLE IF EXISTS `quote_item_costs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quote_item_costs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `quote_item_id` bigint unsigned NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` int NOT NULL DEFAULT '1',
  `price` decimal(15,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `quote_item_costs_quote_item_id_foreign` (`quote_item_id`),
  CONSTRAINT `quote_item_costs_quote_item_id_foreign` FOREIGN KEY (`quote_item_id`) REFERENCES `quote_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quote_item_costs`
--

LOCK TABLES `quote_item_costs` WRITE;
/*!40000 ALTER TABLE `quote_item_costs` DISABLE KEYS */;
/*!40000 ALTER TABLE `quote_item_costs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quote_items`
--

DROP TABLE IF EXISTS `quote_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quote_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `quote_id` bigint unsigned NOT NULL,
  `concept` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `quantity` int NOT NULL DEFAULT '1',
  `unit_price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `billing_type` enum('unique','monthly') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unique',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `service_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `quote_items_quote_id_foreign` (`quote_id`),
  KEY `quote_items_service_id_foreign` (`service_id`),
  CONSTRAINT `quote_items_quote_id_foreign` FOREIGN KEY (`quote_id`) REFERENCES `quotes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `quote_items_service_id_foreign` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quote_items`
--

LOCK TABLES `quote_items` WRITE;
/*!40000 ALTER TABLE `quote_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `quote_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `quotes`
--

DROP TABLE IF EXISTS `quotes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quotes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `client_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `issue_date` date NOT NULL,
  `valid_until` date DEFAULT NULL,
  `duration` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `include_payment_terms` tinyint(1) NOT NULL DEFAULT '0',
  `is_multiple_choice` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `contact_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Pendiente',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `quotes`
--

LOCK TABLES `quotes` WRITE;
/*!40000 ALTER TABLE `quotes` DISABLE KEYS */;
/*!40000 ALTER TABLE `quotes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `role_has_permissions`
--

DROP TABLE IF EXISTS `role_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_has_permissions` (
  `permission_id` bigint unsigned NOT NULL,
  `role_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`role_id`),
  KEY `role_has_permissions_role_id_foreign` (`role_id`),
  CONSTRAINT `role_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_has_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `role_has_permissions`
--

LOCK TABLES `role_has_permissions` WRITE;
/*!40000 ALTER TABLE `role_has_permissions` DISABLE KEYS */;
INSERT INTO `role_has_permissions` VALUES (1,1),(2,1),(3,1),(4,1),(5,1),(6,1),(7,1),(8,1),(9,1),(10,1),(11,1),(12,1),(13,1),(14,1),(15,1),(16,1),(17,1),(18,1),(19,1),(20,1),(21,1),(22,1),(23,1),(24,1),(29,1),(30,1),(31,1),(32,1),(33,1),(34,1),(35,1),(36,1),(1,2),(5,2),(6,2),(7,2),(8,2),(9,2),(10,2),(11,2),(12,2),(13,2),(14,2),(15,2),(16,2),(17,2),(18,2),(19,2),(20,2),(21,2),(22,2),(23,2),(24,2),(29,2),(30,2),(31,2),(32,2),(33,2),(34,2),(35,2),(36,2),(29,3),(30,3),(31,3),(32,3),(1,4),(13,4),(21,4),(29,4),(31,4),(1,5),(5,5),(6,5),(7,5),(8,5),(9,5),(10,5),(11,5),(12,5),(33,5),(34,5),(35,5),(36,5),(1,6),(13,6),(21,6),(29,6),(30,6),(31,6);
/*!40000 ALTER TABLE `role_has_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'Administrador Master','web','2026-03-03 09:20:32','2026-03-03 09:20:32'),(2,'Administrador','web','2026-03-03 09:21:02','2026-03-03 09:21:02'),(3,'Cliente','web','2026-03-03 22:58:07','2026-03-03 22:58:07'),(4,'Web Developer','web','2026-03-03 23:08:54','2026-03-09 23:14:39'),(5,'RRHH','web','2026-03-06 12:25:09','2026-03-06 12:25:09'),(6,'Designer','web','2026-03-09 23:15:21','2026-03-09 23:15:21');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `salary_histories`
--

DROP TABLE IF EXISTS `salary_histories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `salary_histories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` bigint unsigned NOT NULL,
  `salary` decimal(15,2) NOT NULL,
  `change_date` date NOT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `salary_histories_employee_id_foreign` (`employee_id`),
  CONSTRAINT `salary_histories_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `salary_histories`
--

LOCK TABLES `salary_histories` WRITE;
/*!40000 ALTER TABLE `salary_histories` DISABLE KEYS */;
INSERT INTO `salary_histories` VALUES (1,1,15000.00,'2026-03-19','Salario inicial','2026-03-20 01:48:09','2026-03-20 01:48:09');
/*!40000 ALTER TABLE `salary_histories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `service_costs`
--

DROP TABLE IF EXISTS `service_costs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `service_costs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `service_id` bigint unsigned NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` int NOT NULL DEFAULT '1',
  `price` decimal(15,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `service_costs_service_id_foreign` (`service_id`),
  CONSTRAINT `service_costs_service_id_foreign` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `service_costs`
--

LOCK TABLES `service_costs` WRITE;
/*!40000 ALTER TABLE `service_costs` DISABLE KEYS */;
INSERT INTO `service_costs` VALUES (1,14,'Dominio',1,800.00,'2026-03-13 01:03:01','2026-03-13 01:03:01'),(2,14,'Hosting',1,1200.00,'2026-03-13 01:03:01','2026-03-13 01:03:01'),(3,15,'Dominio',1,800.00,'2026-03-13 01:04:55','2026-03-13 01:04:55'),(4,15,'Hosting',1,1200.00,'2026-03-13 01:04:55','2026-03-13 01:04:55'),(7,17,'Dominio',1,800.00,'2026-03-13 01:06:31','2026-03-13 01:06:31'),(8,17,'Hosting',1,2500.00,'2026-03-13 01:06:31','2026-03-13 01:06:31'),(9,16,'Dominio',1,800.00,'2026-03-13 01:06:46','2026-03-13 01:06:46'),(10,16,'Hosting',1,2300.00,'2026-03-13 01:06:46','2026-03-13 01:06:46'),(11,18,'Precio real Plugin',1,3100.00,'2026-03-13 01:07:18','2026-03-13 01:07:18'),(12,19,'Honorarios Traductor',1,2000.00,'2026-03-13 01:07:50','2026-03-13 01:07:50'),(13,21,'Costo Plugin',1,7000.00,'2026-03-13 01:10:57','2026-03-13 01:10:57'),(14,22,'Precio Plugin',1,1800.00,'2026-03-13 01:12:33','2026-03-13 01:12:33'),(15,23,'Costo Google',1,1600.00,'2026-03-13 01:13:01','2026-03-13 01:13:01'),(16,24,'Costo Google',1,3600.00,'2026-03-13 01:13:14','2026-03-13 01:13:14'),(17,25,'Costo Plan Google',1,5500.00,'2026-03-13 01:13:36','2026-03-13 01:13:36'),(19,27,'Costo Mailer',1,200.00,'2026-03-13 01:22:04','2026-03-13 01:22:04');
/*!40000 ALTER TABLE `service_costs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `services`
--

DROP TABLE IF EXISTS `services`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `services` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `renewal_price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `billing_type` enum('unique','monthly') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unique',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `is_package` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `services`
--

LOCK TABLES `services` WRITE;
/*!40000 ALTER TABLE `services` DISABLE KEYS */;
INSERT INTO `services` VALUES (14,'Web Design Bronze','Un sitio web sencillo con contenido breve pero informativo, su diseño es de una sola pagina donde\ntoda tu informacion se centrará. Este tipo de pagina web es especial para negocios que desean\nmostrarse de manera breve, ideal para comenzar tu presencia online.\n\n- Diseño web responsivo, totalmente funcional en móviles, tablets y computadoras.\n- Método para que el visitante se ponga en contacto, botones de llamada, mensaje por\nw whatsApp, Facebook Chat integrado formulario de contacto. (llamado a la acción)\n- Alta de oficinas en Google my business\n- Optimización de acuerdo a estándares de Google\n- Certificado de seguridad SSL y Re-Captcha de google para la seguridad en formularios\n- Registro de Dominio (la cotización puede variar dependiendo del nombre)\n- Cuenta de Hospedaje flexible con: 12GB Espacio en web - Bandwidth 3GB - 5 Correos - SSL\n- Soporte sitio web y mantenimiento hosting',12000.00,3700.00,'unique','2026-03-04 22:08:13','2026-03-04 22:12:45',0),(15,'Web Design Silver','Muestra todo sobre tu negocio, informacion, galerias, catálogos, servicios etc. envuelve a tu audiencia\ncon un contenido más informativo y creativo, este tipo de sitios son excelentes para que las personas\nconozcan más sobre tu marca, creando una experiencia de usuario más satisfactoria.\n\n- Diseño web responsivo, totalmente funcional en móviles, tablets y computadoras.\n- Método para que el visitante se ponga en contacto, botones de llamada, mensaje por\nw whatsApp, Facebook Chat integrado formulario de contacto. (llamado a la acción)\n- Alta de oficinas en Google my business\n- Optimización de acuerdo a estándares de Google\n- Certificado de seguridad SSL y Re-Captcha de google para la seguridad en formularios\n- Registro de Dominio (la cotización puede variar dependiendo del nombre)\n- Cuenta de Hospedaje flexible con: 24GB Espacio en web - Bandwidth 10GB - Correos 10 - SSL\n- Soporte sitio web y mantenimiento hosting (solo si se adquiere con hosting y dominio)',18500.00,4300.00,'unique','2026-03-04 22:09:23','2026-03-04 22:13:01',0),(16,'Web Design Gold','Muy similar al silver pero con la facilidad de gestionar tu mismo el contenido contando siempre con un\nequipo de asesoria y soporte. Nosotros hacemos que todo funcione para ti, este paquete es\nespecial para creadores de contenido como bloggers, noticias, informes, funcionalidades avanzadas etc.\n\n- Diseño web responsivo, totalmente funcional en móviles, tablets y computadoras.\n- Método para que el visitante se ponga en contacto, botones de llamada, mensaje por\nw whatsApp, Facebook Chat integrado formulario de contacto. (llamado a la acción)\n- Alta de oficinas en Google my business\n- Optimización de acuerdo a estándares de Google\n- Certificado de seguridad SSL y Re-Captcha de google para la seguridad en formularios\n- Registro de Dominio (la cotización puede variar dependiendo del nombre)\n- Cuenta de Hospedaje flexible con: 48GB Espacio en web - Bandwidth 20GB - Correos 20 - SSL\n- Soporte sitio web y mantenimiento hosting',28700.00,5800.00,'unique','2026-03-04 22:10:34','2026-03-04 22:13:18',0),(17,'Web Design Platinum','El sitio de ventas online, cuenta con todas las caracteristicas del paquete gold pero especial para\nventas donde podras administrar tu inventario, gestionar ordenes y envios, configurar metodos de\npagos seguros, crear cupones para promociones con descuento, aplicar descuentos directos a\nproductos por temporada. Es el sitio web ideal para introducir tu negocio al comercio electronico.\n\n- Diseño web responsivo, totalmente funcional en móviles, tablets y computadoras.\n- Método para que el visitante se ponga en contacto, botones de llamada, mensaje por\nw whatsApp, Facebook Chat integrado formulario de contacto. (llamado a la acción)\n- Alta de oficinas en Google my business\n- Optimización de acuerdo a estándares de Google\n- Certificado de seguridad SSL y Re-Captcha de google para la seguridad en formularios\n- Registro de Dominio (la cotización puede variar dependiendo del nombre)\n- Cuenta de Hospedaje flexible con: 48GB Espacio en web - Bandwidth 20GB - Correos 20 - SSL\n- Soporte sitio web, monitoreo de funcionamiento y mantenimiento hosting',75000.00,8200.00,'unique','2026-03-04 22:12:20','2026-03-04 22:12:20',0),(18,'Live Chat','Chat en vivo que ofrece mecanografía en vivo, lista de visitantes en vivo, páginas vistas y más',4000.00,4000.00,'unique','2026-03-04 22:15:35','2026-03-04 22:15:35',0),(19,'Conversión a Inglés','Convertimos tu sitio web al idioma inglés',3500.00,0.00,'unique','2026-03-04 22:16:29','2026-03-04 22:16:29',0),(20,'Formulario personalizado','Creación de formulario personalizado con multiples elementos como carga de archivos, cuestionario y más',1200.00,0.00,'unique','2026-03-04 22:17:56','2026-03-04 22:17:56',0),(21,'Advanced Live Chat','Chat en vivo avanzado con inteligencia artificial',9000.00,9000.00,'unique','2026-03-04 22:21:03','2026-03-13 01:10:57',0),(22,'SEO Avanzado','Rastrea e informa el tráfico del sitio web, facilita datos e informes sobre todo lo que pasa en le sitio web.\nSEO mejora tu posición en los resultados obtenidos por los motores de búsqueda, es decir, intentar\naparecer primero en las búsquedas de Google.',9000.00,0.00,'unique','2026-03-04 22:22:44','2026-03-04 22:22:44',0),(23,'Google Workspace Starter (Anual)','Es una poderosa de herramienta para mensajería y colaboración que incrementa la productividad\n\nStarter incluye:\n30 GB de almacenamiento compartido por persona*\nCorreo de empresa personalizado y seguro (tu-nombre@tu-empresa.com)\nAsistente de IA Gemini en Gmail\nChatea con la IA en la aplicación Gemini\nVideollamadas de hasta 100 participantes\nGoogle Vids: creador y editor de vídeos basado en IA\nControles de seguridad y gestión',1600.00,1600.00,'unique','2026-03-04 22:26:18','2026-03-04 22:26:18',0),(24,'Google Workspace Standard (Anual)','Es una poderosa de herramienta para mensajería y colaboración que incrementa la productividad\n\nStandard incluye:\n2 TB de almacenamiento compartido por persona*\nCorreo de empresa personalizado y seguro (tu-nombre@tu-empresa.com)\nAsistente de IA Gemini en Gmail\nChatea con la IA en la aplicación Gemini\nVideollamadas de hasta 100 participantes\nGoogle Vids: creador y editor de vídeos basado en IA\nControles de seguridad y gestión\nNotebookLM con acceso ampliado a funciones\nChatea con la IA en la aplicación Gemini con acceso ampliado a modelos y funciones\nVideollamadas con registros, cancelación de ruido y hasta 150 participantes\nPáginas de reserva de citas\nFirma electrónica con Documentos y PDFs\nHerramienta Google Workspace Migrate para migrar datos',3600.00,3600.00,'unique','2026-03-04 22:28:11','2026-03-04 22:28:11',0),(25,'Google Workspace Plus (Anual)','Es una poderosa de herramienta para mensajería y colaboración que incrementa la productividad\n\nPlus incluye:\n\n5 TB de almacenamiento compartido por persona*\nCorreo de empresa personalizado y seguro tu-nombre@tu-empresa.com\nAsistente de IA Gemini en Gmail\nChatea con la IA en la aplicación Gemini\nVideollamadas de hasta 500 participantes\nGoogle Vids: creador y editor de vídeos basado en IA\nNotebookLM con acceso ampliado a funciones\nChatea con la IA en la aplicación Gemini con acceso ampliado a modelos y funciones\nVideollamadas con registros, cancelación de ruido y hasta 150 participantes\nPáginas de reserva de citas\nFirma electrónica con Documentos y PDFs\nVault para conservar, archivar y buscar datos\nLDAP seguro\nGestión avanzada de endpoints\nControles de seguridad y de gestión mejorados',5500.00,5500.00,'unique','2026-03-04 22:32:05','2026-03-04 22:32:22',0),(26,'Redes Sociales','Administración de 3 plataformas de redes sociales, las cuáles pueden ser:\n- Facebook\n- Instagram\n- X\n- TikTok\n- LinkedIn',5800.00,0.00,'monthly','2026-03-04 23:21:18','2026-03-04 23:22:38',0),(27,'Email Marketing','Creación de Campañas de Email marketing, administración de Suscripciones',2500.00,0.00,'monthly','2026-03-04 23:21:58','2026-03-04 23:21:58',0),(29,'Video Institucional','Grabación y edición profesional de video institucional',5500.00,0.00,'unique','2026-03-09 22:38:05','2026-03-09 22:38:05',0);
/*!40000 ALTER TABLE `services` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sessions`
--

LOCK TABLES `sessions` WRITE;
/*!40000 ALTER TABLE `sessions` DISABLE KEYS */;
INSERT INTO `sessions` VALUES ('1l0W6LMb7c0m27dhiHm1GtUYx0sXHSSD3SVqCI7R',1,'127.0.0.1','Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36','YTo1OntzOjY6Il90b2tlbiI7czo0MDoiMEQzbjhFU1Nkd3dYY3lBdklJUENJWnp6b2xkeHUzWXpoeEU0NXYwRSI7czozOiJ1cmwiO2E6MDp7fXM6OToiX3ByZXZpb3VzIjthOjI6e3M6MzoidXJsIjtzOjMzOiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvZW1wbG95ZWVzLzEiO3M6NToicm91dGUiO3M6MTQ6ImVtcGxveWVlcy5zaG93Ijt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319czo1MDoibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiO2k6MTt9',1773950147);
/*!40000 ALTER TABLE `sessions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `settings_key_unique` (`key`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings`
--

LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
INSERT INTO `settings` VALUES (1,'contract_template','# Contrato de Prestación de Servicios Digitales\n\nEn la ciudad correspondiente, el día **[fecha]**, celebran el presente **Contrato de Prestación de Servicios Digitales**, por una parte [mi_razon_social] con RFC [mi_rfc] a quien en lo sucesivo se le denominará **\"EL PRESTADOR\"**, y por la otra **[razon_social]**, representada por **[representante]**, a quien en lo sucesivo se le denominará **\"EL CLIENTE\"**, al tenor de las siguientes declaraciones y cláusulas:\n\n\"EL CLIENTE\" declara tener su domicilio fiscal ubicado en: **[direccion]**, con Registro Federal de Contribuyentes **[rfc]**.\n\nAmbas partes manifiestan contar con la capacidad legal suficiente para obligarse en los términos del presente contrato.\n\n---\n\n# DECLARACIONES\n\n### I. Declara **EL PRESTADOR** que: \n\n1. Es una empresa dedicada a la prestación de servicios digitales, desarrollo web, marketing digital, desarrollo de software, automatización de procesos y consultoría tecnológica.\n2. Cuenta con la experiencia técnica, personal especializado y recursos necesarios para brindar los servicios contratados.\n\n### II. Declara **EL CLIENTE** que:\n\n1. Tiene interés en contratar los servicios digitales ofrecidos por **EL PRESTADOR**.\n2. La información proporcionada para la prestación del servicio es verídica y suficiente para la ejecución del proyecto.\n\n---\n\n# CLÁUSULAS\n\n## Cláusula 1. Objeto\n\nPor medio del presente contrato, **EL PRESTADOR** se obliga a brindar a **EL CLIENTE** los servicios aprobados en la cotización correspondiente, consistentes en:\n\n[lista_servicios]\n\nLos servicios se prestarán conforme a las especificaciones, alcances y condiciones establecidas en la propuesta o cotización aceptada por **EL CLIENTE**.\n\n---\n\n## Cláusula 2. Honorarios y Pagos\n\nComo contraprestación por los servicios prestados, **EL CLIENTE** se obliga a pagar a **EL PRESTADOR** los montos acordados en la cotización aceptada.\n\n[inversion_inicial]\n\n[inversion_mensual]\n\nLos pagos deberán realizarse mediante transferencia bancaria, tarjeta o cualquier medio acordado entre las partes.\n\nEn caso de retraso en los pagos, **EL PRESTADOR** podrá suspender temporalmente los servicios hasta que el pago correspondiente sea regularizado.\n\n---\n\n## Cláusula 3. Duración\n\nEl presente contrato tendrá una duración de **[duracion]**, contados a partir de la fecha de firma del presente documento.\n\nEn caso de tratarse de servicios recurrentes o igualas mensuales, el contrato podrá renovarse automáticamente previo acuerdo entre ambas partes.\n\n---\n\n## Cláusula 4. Obligaciones de EL PRESTADOR\n\nEL PRESTADOR se compromete a:\n\n* Realizar los servicios con profesionalismo y conforme a las mejores prácticas del sector.\n* Mantener comunicación razonable con EL CLIENTE sobre el avance de los servicios.\n* Entregar los resultados conforme a los tiempos estimados cuando dependan exclusivamente de EL PRESTADOR.\n\n---\n\n## Cláusula 5. Obligaciones de EL CLIENTE\n\nEL CLIENTE se compromete a:\n\n* Proporcionar la información, accesos, materiales y contenidos necesarios para la ejecución de los servicios.\n* Revisar y aprobar avances dentro de tiempos razonables.\n* Cumplir con los pagos en los tiempos acordados.\n\nLos retrasos ocasionados por falta de información o aprobaciones por parte de EL CLIENTE podrán extender los tiempos de entrega sin responsabilidad para EL PRESTADOR.\n\n---\n\n## Cláusula 6. Propiedad Intelectual\n\nSalvo acuerdo distinto por escrito:\n\n* El código, diseño, contenidos, estrategias o materiales desarrollados serán propiedad de **EL CLIENTE** una vez cubiertos en su totalidad los pagos correspondientes.\n* EL PRESTADOR podrá conservar el derecho de mostrar el proyecto en su portafolio profesional.\n\nLas herramientas internas, metodologías, frameworks, plugins o desarrollos reutilizables creados por **EL PRESTADOR** seguirán siendo propiedad de este último.\n\n---\n\n## Cláusula 7. Confidencialidad\n\nAmbas partes se comprometen a mantener estricta confidencialidad respecto a la información técnica, comercial o estratégica compartida durante la vigencia del contrato.\n\nEsta obligación permanecerá vigente incluso después de terminado el contrato.\n\n---\n\n## Cláusula 8. Limitación de Responsabilidad\n\nEL PRESTADOR no será responsable por:\n\n* Fallas ocasionadas por servicios de terceros (hosting, plataformas, proveedores externos).\n* Cambios en políticas de plataformas externas (Google, Meta, Apple, etc.).\n* Pérdida de datos o interrupciones derivadas de causas fuera de su control.\n\n---\n\n## Cláusula 9. Terminación Anticipada\n\nEl presente contrato podrá darse por terminado anticipadamente en los siguientes casos:\n\n* Incumplimiento de las obligaciones de pago por parte de EL CLIENTE.\n* Incumplimiento grave de cualquiera de las obligaciones establecidas en este contrato.\n* Mutuo acuerdo entre las partes.\n\nEn caso de terminación anticipada, **EL CLIENTE** deberá cubrir los servicios ya realizados hasta la fecha de cancelación.\n\n---\n\n## Cláusula 10. Jurisdicción\n\nPara la interpretación y cumplimiento del presente contrato, ambas partes se someten a las leyes y tribunales competentes de los Estados Unidos Mexicanos, renunciando a cualquier otro fuero que pudiera corresponderles por razón de su domicilio presente o futuro.\n\n---\n\n# Aceptación del Contrato\n\nLeído que fue el presente contrato y enteradas las partes de su contenido y alcance legal, lo firman digitalmente en la fecha **[fecha]**.\n\n**EL PRESTADOR**\n**LUN AVALOS DIGITAL HOUSE**\n\n---\n\n**EL CLIENTE**\n**[razon_social]**\nRepresentante: **[representante]**','2026-03-05 01:23:31','2026-03-13 08:07:33'),(2,'company_legal_name','Joanna Isamar Avalos Duarte','2026-03-06 20:47:54','2026-03-06 20:47:54'),(3,'company_rfc','AADJ900522M54','2026-03-06 20:47:54','2026-03-06 20:47:54'),(4,'company_address','Gallo #118, Las Maravillas, Saltillo Coahuila CP 25019','2026-03-06 20:47:54','2026-03-06 20:47:54'),(5,'company_email','contacto@lunavalos.com','2026-03-06 20:47:54','2026-03-06 20:47:54'),(6,'company_phone','8442751165','2026-03-06 20:47:54','2026-03-06 20:47:54'),(7,'company_commercial_name','LunAvalos Digital House','2026-03-13 08:03:01','2026-03-13 08:14:12'),(8,'company_commercial_address','Av. La salle #437 Col. La Salle','2026-03-13 08:03:01','2026-03-13 08:03:01'),(9,'company_work_days','Lunes a Viernes','2026-03-13 08:03:01','2026-03-13 08:11:42'),(10,'company_work_start','08:45','2026-03-13 08:03:01','2026-03-13 08:03:01'),(11,'company_work_end','14:00','2026-03-13 08:03:01','2026-03-13 08:03:01'),(12,'company_logo','','2026-03-13 08:03:01','2026-03-13 08:03:01'),(13,'company_fb','lunavalos','2026-03-13 08:03:01','2026-03-13 08:03:01'),(14,'company_x','lunavalosDH','2026-03-13 08:03:01','2026-03-13 08:03:01'),(15,'company_linkedin','','2026-03-13 08:03:01','2026-03-13 08:03:01'),(16,'company_ig','lunavalos','2026-03-13 08:03:01','2026-03-13 08:03:01'),(17,'company_tiktok','@lunavalos','2026-03-13 08:03:01','2026-03-13 08:03:01'),(18,'company_yt','LunAvalosDigitalHouse','2026-03-13 08:03:01','2026-03-13 08:03:01'),(19,'employee_contract_template','<p><strong>CONTRATO INDIVIDUAL DE TRABAJO</strong> que celebran por una parte <strong>[razon_social]</strong>, a quien en lo sucesivo se le denominará <strong>“EL PATRÓN”</strong>, y por la otra <strong>[nombre_empleado]</strong>, a quien en lo sucesivo se le denominará <strong>“EL EMPLEADO”</strong>, al tenor de las siguientes declaraciones y cláusulas:</p><h3><br></h3><h3>DECLARACIONES</h3><p><strong>I. Declara EL PATRÓN que:</strong></p><p>a) Es una empresa legalmente constituida bajo la razón social <strong>[razon_social]</strong>.</p><p> b) Se encuentra inscrita en el Registro Federal de Contribuyentes con clave <strong>[rfc_empresa]</strong>.</p><p> c) Tiene interés en contratar los servicios personales subordinados de <strong>EL EMPLEADO</strong> para desempeñar el puesto de <strong>[puesto]</strong> dentro del área de <strong>[departamento]</strong>.</p><p><strong>II. Declara EL EMPLEADO que:</strong></p><p>a) Su nombre es <strong>[nombre_empleado]</strong>.</p><p> b) Su número de empleado es <strong>[numero_empleado]</strong>.</p><p> c) Su domicilio es <strong>[direccion]</strong>.</p><p> d) Su teléfono de contacto es <strong>[telefono]</strong>.</p><p> e) Su CURP es <strong>[curp]</strong>.</p><p> f) Su Número de Seguridad Social es <strong>[nss]</strong>.</p><p> g) Su Registro Federal de Contribuyentes es <strong>[rfc]</strong>.</p><p> h) Cuenta con la capacidad, conocimientos y aptitudes necesarias para prestar sus servicios en el puesto señalado.</p><p>Expuesto lo anterior, ambas partes acuerdan sujetarse a las siguientes:</p><h2><br></h2><h2>CLÁUSULAS</h2><h3><br></h3><h3>PRIMERA. OBJETO DEL CONTRATO</h3><p>EL PATRÓN contrata a EL EMPLEADO para prestar sus servicios personales subordinados en el puesto de <strong>[puesto]</strong>, adscrito al departamento de <strong>[departamento]</strong>, desempeñando las funciones inherentes a dicho cargo y aquellas que le sean razonablemente encomendadas de acuerdo con la naturaleza de sus actividades.</p><h3><br></h3><h3>SEGUNDA. FECHA DE INICIO</h3><p>La relación de trabajo dará inicio el día <strong>[fecha_ingreso]</strong>.</p><h3><br></h3><h3>TERCERA. LUGAR DE PRESTACIÓN DE LOS SERVICIOS</h3><p>EL EMPLEADO prestará sus servicios en el domicilio, instalaciones o centros de trabajo que determine EL PATRÓN, conforme a las necesidades operativas de la empresa.</p><h3><br></h3><h3>CUARTA. JORNADA DE TRABAJO</h3><p>La jornada de trabajo será la que determine EL PATRÓN de conformidad con la legislación aplicable, respetando los límites máximos legales. EL EMPLEADO se obliga a cumplir con los horarios, controles de asistencia, políticas internas y lineamientos establecidos por la empresa.</p><h3><br></h3><h3>QUINTA. SALARIO</h3><p>Como contraprestación por los servicios prestados, EL PATRÓN pagará a EL EMPLEADO un salario de <strong>[salario_actual]</strong>, en la forma y periodicidad que determine la empresa, realizando las deducciones legales correspondientes.</p><h3><br></h3><h3>SEXTA. PRESTACIONES</h3><p>EL EMPLEADO tendrá derecho a las prestaciones que establezcan la Ley Federal del Trabajo, la seguridad social aplicable, así como aquellas prestaciones adicionales que, en su caso, otorgue EL PATRÓN conforme a sus políticas internas.</p><h3><br></h3><h3>SÉPTIMA. OBLIGACIONES DEL EMPLEADO</h3><p>EL EMPLEADO se obliga a:</p><p>a) Desempeñar sus labores con diligencia, eficiencia y buena fe.</p><p> b) Acatar las órdenes e instrucciones de sus superiores relacionadas con su trabajo.</p><p> c) Cumplir con las políticas, reglamentos, manuales y procedimientos internos de la empresa.</p><p> d) Guardar confidencialidad respecto de la información, procesos, datos, clientes, estrategias y cualquier información reservada a la que tenga acceso con motivo de su trabajo.</p><p> e) Cuidar los bienes, herramientas, equipos, documentos y materiales que le sean proporcionados.</p><h3><br></h3><h3>OCTAVA. CONFIDENCIALIDAD</h3><p>EL EMPLEADO reconoce que toda la información a la que tenga acceso con motivo de la relación laboral es confidencial, por lo que se obliga a no divulgarla, reproducirla ni utilizarla para fines distintos al desempeño de sus funciones, salvo autorización expresa y por escrito de EL PATRÓN.</p><h3><br></h3><h3>NOVENA. HERRAMIENTAS Y EQUIPO DE TRABAJO</h3><p>En caso de que EL PATRÓN proporcione equipo, herramientas, uniformes, claves de acceso, documentos o materiales de trabajo, EL EMPLEADO se obliga a utilizarlos exclusivamente para el desempeño de sus funciones y devolverlos al término de la relación laboral o cuando le sean requeridos.</p><h3><br></h3><h3>DÉCIMA. CAUSAS DE TERMINACIÓN</h3><p>La relación de trabajo podrá suspenderse, rescindirse o darse por terminada en los casos previstos por la Ley Federal del Trabajo y demás disposiciones aplicables.</p><h3><br></h3><h3>DÉCIMA PRIMERA. ACEPTACIÓN</h3><p>Leído que fue el presente contrato y enteradas las partes de su contenido y alcance legal, lo firman de conformidad en la fecha <strong>[fecha_actual]</strong>.</p><p><br></p><p><strong>EL PATRÓN</strong></p><p> <strong>[razon_social]</strong></p><p>Firma: __________________________</p><p>Nombre: _________________________</p><p>Cargo: __________________________</p><p><br></p><p><strong>EL EMPLEADO</strong></p><p> <strong>[nombre_empleado]</strong></p><p>Firma: __________________________</p><p>Número de empleado: <strong>[numero_empleado]</strong></p><h2><br></h2>','2026-03-20 01:46:42','2026-03-20 01:46:42');
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `signature_templates`
--

DROP TABLE IF EXISTS `signature_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `signature_templates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `html_content` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `css_content` text COLLATE utf8mb4_unicode_ci,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `signature_templates_slug_unique` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `signature_templates`
--

LOCK TABLES `signature_templates` WRITE;
/*!40000 ALTER TABLE `signature_templates` DISABLE KEYS */;
INSERT INTO `signature_templates` VALUES (2,'Modern 1','modern-1','<table cellpadding=\"0\" cellspacing=\"0\" border=\"0\" style=\"font-family: \'Helvetica Neue\', Helvetica, Arial, sans-serif; color: #333; line-height: 1.2; background-color: #ffffff; width: 100%; max-width: 600px;\">\n  <!-- Ribbon Superior Dinámico -->\n  <tr>\n    <td colspan=\"2\" style=\"background-color: {{primary_color}}; height: 35px; border-bottom: 5px solid {{secondary_color}};\"></td>\n  </tr>\n  \n  <tr>\n    <!-- Columna Izquierda: Logo Circular -->\n    <td style=\"padding: 30px 20px; vertical-align: middle; width: 140px;\">\n      <div style=\"width: 110px; height: 110px; border-radius: 0; overflow: hidden; display: block;\">\n        <img src=\"{{logo}}\" width=\"110\" height=\"110\" style=\"display: block; object-fit: contain;\" alt=\"Logo\">\n      </div>\n    </td>\n    \n    <!-- Columna Derecha: Información -->\n    <td style=\"padding: 30px 20px; vertical-align: middle;\">\n      <!-- Nombre: Muy Grande y Ultra Bold -->\n      <div style=\"font-size: 28px; font-weight: 900; color: #000000; margin-bottom: 4px; font-family: Arial, sans-serif; letter-spacing: -1px; text-transform: uppercase;\">\n        {{name}}\n      </div>\n      \n      <!-- Puesto: Bold y en Color Principal -->\n      <div style=\"font-size: 13px; font-weight: bold; color: {{primary_color}}; margin-bottom: 15px; text-transform: uppercase; letter-spacing: 1px;\">\n        {{position}}\n      </div>\n      \n      <!-- Línea Divisoria Sutil -->\n      <div style=\"height: 1px; background-color: #eeeeee; width: 100%; margin-bottom: 15px;\"></div>\n      \n      <!-- Datos de Contacto con Itálica y Separadores -->\n      <div style=\"font-size: 12px; font-style: italic; color: #555555;\">\n        <span style=\"font-weight: bold; color: {{primary_color}};\"></span> <span style=\"color: #000;\">{{phone}}</span> \n        <span style=\"margin: 0 10px; color: {{secondary_color}};\">|</span> \n        <span style=\"font-weight: bold; color: {{primary_color}};\"></span> <span style=\"color: #000;\">{{email}}</span> \n        <span style=\"margin: 0 10px; color: {{secondary_color}};\">|</span> \n        <span style=\"font-weight: bold; color: {{primary_color}};\"></span> <span style=\"color: #000;\">{{website}}</span>\n      </div>\n      \n      <!-- Redes Sociales -->\n      <div style=\"margin-top: 18px;\">\n        {{social_links}}\n      </div>\n    </td>\n  </tr>\n  <!-- Ribbon Superior Dinámico -->\n  <tr>\n    <td colspan=\"2\" style=\"background-color: {{secondary_color}}; height: 15px;\"></td>\n  </tr>\n</table>',NULL,1,'2026-03-10 12:40:29','2026-03-10 21:51:00'),(4,'Classic 1','classic-1','<table cellpadding=\"0\" cellspacing=\"0\" border=\"0\" style=\"font-family: \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; color: #333; width: 100%; max-width: 600px; background-color: #ffffff;\">\n  <tr>\n    <td style=\"padding: 30px 0;\">\n      <table cellpadding=\"0\" cellspacing=\"0\" border=\"0\">\n        <tr>\n          <!-- Columna del Logo -->\n          <td style=\"vertical-align: middle; padding-right: 25px;\">\n            <div style=\"width: 110px; height: 110px; border-radius: 0; overflow: hidden;\">\n                <img src=\"{{logo}}\" width=\"110\" height=\"110\" style=\"display: block; object-fit: contain;\" alt=\"Brand\">\n            </div>\n          </td>\n\n          <!-- Barra Separadora Vertical -->\n          <td style=\"width: 4px; background-color: {{secondary_color}}; vertical-align: stretch; border-radius: 2px;\"></td>\n\n          <!-- Columna de Información -->\n          <td style=\"vertical-align: middle; padding-left: 25px;\">\n            <!-- Nombre -->\n            <div style=\"font-size: 28px; font-weight: 800; color: {{primary_color}}; letter-spacing: -0.5px; line-height: 1; font-family: Arial, sans-serif;\">\n              {{name}}\n            </div>\n            \n            <!-- Puesto -->\n            <div style=\"font-size: 13px; background-color: #e0e0e0; padding: 4px 10px; text-transform: uppercase; font-weight: 400; color: #666666; margin-top: 8px; margin-bottom: 20px;\">\n              {{position}}\n            </div>\n            \n            <!-- Información de Contacto -->\n            <div style=\"font-size: 12px; color: #111111; font-weight: 500;\">\n              <span>{{phone}}</span>\n              <span style=\"margin: 0 5px; color: {{secondary_color}}; font-weight: bold;\">|</span>\n              <span>{{email}}</span>\n              <span style=\"margin: 0 5px; color: {{secondary_color}}; font-weight: bold;\">|</span>\n              <span>{{website}}</span>\n            </div>\n            \n            <!-- Redes Sociales -->\n            <div style=\"margin-top: 15px;\">\n              {{social_links}}\n            </div>\n          </td>\n        </tr>\n      </table>\n    </td>\n    \n    <!-- Decoración Lateral (Ondas simuladas) -->\n    <td style=\"width: 100px; vertical-align: top; text-align: right;\">\n        <div style=\"background-color: {{primary_color}}; width: 40px; height: 100%; display: inline-block; opacity: 0.1; border-top-left-radius: 100px; border-bottom-left-radius: 100px;\"></div>\n        <div style=\"background-color: {{secondary_color}}; width: 30px; height: 80%; display: inline-block; opacity: 0.1; border-top-left-radius: 100px; border-bottom-left-radius: 100px; margin-left: -5px;\"></div>\n    </td>\n  </tr>\n</table>',NULL,1,'2026-03-10 13:18:24','2026-03-10 22:57:34');
/*!40000 ALTER TABLE `signature_templates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ticket_attachments`
--

DROP TABLE IF EXISTS `ticket_attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ticket_attachments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ticket_id` bigint unsigned NOT NULL,
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ticket_attachments_ticket_id_foreign` (`ticket_id`),
  CONSTRAINT `ticket_attachments_ticket_id_foreign` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ticket_attachments`
--

LOCK TABLES `ticket_attachments` WRITE;
/*!40000 ALTER TABLE `ticket_attachments` DISABLE KEYS */;
/*!40000 ALTER TABLE `ticket_attachments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ticket_messages`
--

DROP TABLE IF EXISTS `ticket_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ticket_messages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `ticket_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `message` text COLLATE utf8mb4_unicode_ci,
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ticket_messages_ticket_id_foreign` (`ticket_id`),
  KEY `ticket_messages_user_id_foreign` (`user_id`),
  CONSTRAINT `ticket_messages_ticket_id_foreign` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ticket_messages_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ticket_messages`
--

LOCK TABLES `ticket_messages` WRITE;
/*!40000 ALTER TABLE `ticket_messages` DISABLE KEYS */;
/*!40000 ALTER TABLE `ticket_messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tickets`
--

DROP TABLE IF EXISTS `tickets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tickets` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` longtext COLLATE utf8mb4_unicode_ci,
  `priority` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Media',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Nuevos',
  `status_updated_at` timestamp NULL DEFAULT NULL,
  `creator_id` bigint unsigned NOT NULL,
  `assigned_id` bigint unsigned DEFAULT NULL,
  `due_date` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tickets_creator_id_foreign` (`creator_id`),
  KEY `tickets_assigned_id_foreign` (`assigned_id`),
  CONSTRAINT `tickets_assigned_id_foreign` FOREIGN KEY (`assigned_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tickets_creator_id_foreign` FOREIGN KEY (`creator_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tickets`
--

LOCK TABLES `tickets` WRITE;
/*!40000 ALTER TABLE `tickets` DISABLE KEYS */;
/*!40000 ALTER TABLE `tickets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Hugo Luna','hugo@lunavalos.com',NULL,'$2y$12$vdKlNxHydgIw2ou0FZslceqn1tdRAr3fnLADKe5afJRewm7MDDzx6',NULL,'2026-03-03 09:20:33','2026-03-04 07:19:36');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
-- SET @@SESSION.SQL_LOG_BIN = @MYSQLDUMP_TEMP_LOG_BIN;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-03-19 14:19:52
