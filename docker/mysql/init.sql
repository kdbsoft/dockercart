/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19-11.8.8-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: dockercart
-- ------------------------------------------------------
-- Server version	11.8.8-MariaDB-ubu2404

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*M!100616 SET @OLD_NOTE_VERBOSITY=@@NOTE_VERBOSITY, NOTE_VERBOSITY=0 */;

--
-- Table structure for table `oc_address`
--

DROP TABLE IF EXISTS `oc_address`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `oc_address` (
  `address_id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `firstname` varchar(32) NOT NULL,
  `lastname` varchar(32) NOT NULL,
  `company` varchar(40) NOT NULL,
  `address_1` varchar(128) NOT NULL,
  `address_2` varchar(128) NOT NULL,
  `city` varchar(128) NOT NULL,
  `postcode` varchar(10) NOT NULL,
  `country_id` int(11) NOT NULL DEFAULT 0,
  `zone_id` int(11) NOT NULL DEFAULT 0,
  `custom_field` mediumtext NOT NULL,
  PRIMARY KEY (`address_id`),
  KEY `customer_id` (`customer_id`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `oc_address`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `oc_address` WRITE;
/*!40000 ALTER TABLE `oc_address` DISABLE KEYS */;
INSERT INTO `oc_address` VALUES
(19,9,'Test','Test','','','','','',220,3490,'');
/*!40000 ALTER TABLE `oc_address` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `oc_api`
--

DROP TABLE IF EXISTS `oc_api`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `oc_api` (
  `api_id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(64) NOT NULL,
  `key` mediumtext NOT NULL,
  `status` tinyint(1) NOT NULL,
  `date_added` datetime NOT NULL,
  `date_modified` datetime NOT NULL,
  PRIMARY KEY (`api_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `oc_api`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `oc_api` WRITE;
/*!40000 ALTER TABLE `oc_api` DISABLE KEYS */;
INSERT INTO `oc_api` VALUES
(3,'Default','558a270743dc11f19007c2d192a9df58',1,'2026-04-29 15:01:43','2026-04-29 15:01:43');
/*!40000 ALTER TABLE `oc_api` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `oc_api_ip`
--

DROP TABLE IF EXISTS `oc_api_ip`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `oc_api_ip` (
  `api_ip_id` int(11) NOT NULL AUTO_INCREMENT,
  `api_id` int(11) NOT NULL,
  `ip` varchar(40) NOT NULL,
  PRIMARY KEY (`api_ip_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `oc_api_ip`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `oc_api_ip` WRITE;
/*!40000 ALTER TABLE `oc_api_ip` DISABLE KEYS */;
INSERT INTO `oc_api_ip` VALUES
(1,1,'172.19.0.2'),
(2,1,'172.19.0.3'),
(3,1,'172.19.0.6'),
(4,1,'172.19.0.4'),
(5,1,'172.22.0.7'),
(6,2,'172.20.0.6'),
(7,3,'10.89.1.6'),
(8,3,'10.89.1.7'),
(9,3,'10.89.2.7'),
(10,3,'10.89.2.8');
/*!40000 ALTER TABLE `oc_api_ip` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `oc_api_session`
--

DROP TABLE IF EXISTS `oc_api_session`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `oc_api_session` (
  `api_session_id` int(11) NOT NULL AUTO_INCREMENT,
  `api_id` int(11) NOT NULL,
  `session_id` varchar(32) NOT NULL,
  `ip` varchar(40) NOT NULL,
  `date_added` datetime NOT NULL,
  `date_modified` datetime NOT NULL,
  PRIMARY KEY (`api_session_id`)
) ENGINE=InnoDB AUTO_INCREMENT=970 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `oc_api_session`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `oc_api_session` WRITE;
/*!40000 ALTER TABLE `oc_api_session` DISABLE KEYS */;
INSERT INTO `oc_api_session` VALUES
(959,3,'b864cfd69b9f7a54fb48b3b68c','10.89.2.7','2026-06-20 11:50:45','2026-06-20 11:50:50'),
(960,3,'01809d95db77f7b5f747202d19','10.89.2.7','2026-06-22 12:09:26','2026-06-22 12:09:26'),
(961,3,'62e3e62ff37425209d2cf716ec','10.89.2.7','2026-06-22 12:22:22','2026-06-22 12:22:22'),
(962,3,'b5e6f0d416e9a8f2a23bf27945','10.89.2.7','2026-06-22 12:34:19','2026-06-22 12:34:19'),
(963,3,'8f16484b855628e986626b80f1','10.89.2.7','2026-06-22 12:46:39','2026-06-22 12:46:39'),
(964,3,'309a2ef096f2b0a175bb408e6f','10.89.2.7','2026-06-22 12:46:50','2026-06-22 12:46:50'),
(965,3,'a11f93d38e9a79b603e08bbdd6','10.89.2.8','2026-06-30 08:24:27','2026-06-30 08:24:27'),
(966,3,'a8f09caf90d8932fe5941a22c6','10.89.2.8','2026-06-30 08:24:42','2026-06-30 08:24:42'),
(967,3,'a91d9c45dcb6632ab8470df624','10.89.2.8','2026-06-30 08:28:17','2026-06-30 08:28:17'),
(968,3,'8f31a805b4e7115279ec5e08db','10.89.2.8','2026-06-30 08:35:36','2026-06-30 08:35:36'),
(969,3,'d1a3c70060234702a2359d77f7','10.89.2.8','2026-06-30 08:35:38','2026-06-30 08:35:38');
/*!40000 ALTER TABLE `oc_api_session` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `oc_attribute`
--

DROP TABLE IF EXISTS `oc_attribute`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `oc_attribute` (
  `attribute_id` int(11) NOT NULL AUTO_INCREMENT,
  `attribute_group_id` int(11) NOT NULL,
  `sort_order` int(11) NOT NULL,
  PRIMARY KEY (`attribute_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5005 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `oc_attribute`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `oc_attribute` WRITE;
/*!40000 ALTER TABLE `oc_attribute` DISABLE KEYS */;
INSERT INTO `oc_attribute` VALUES
(2,1,2),
(3,2,3),
(5001,1,1),
(5002,501,1),
(5003,501,1);
/*!40000 ALTER TABLE `oc_attribute` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `oc_attribute_description`
--

DROP TABLE IF EXISTS `oc_attribute_description`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `oc_attribute_description` (
  `attribute_id` int(11) NOT NULL,
  `language_id` int(11) NOT NULL,
  `name` varchar(64) NOT NULL,
  PRIMARY KEY (`attribute_id`,`language_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `oc_attribute_description`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `oc_attribute_description` WRITE;
/*!40000 ALTER TABLE `oc_attribute_description` DISABLE KEYS */;
INSERT INTO `oc_attribute_description` VALUES
(2,1,'Type'),
(2,2,'Тип'),
(2,3,'Тип'),
(3,1,'Is New'),
(3,2,'Новий'),
(3,3,'Новый'),
(5001,1,'Brand'),
(5001,2,'Бренд'),
(5001,3,'Бренд'),
(5002,1,'Material'),
(5002,2,'Матеріал'),
(5002,3,'Материал'),
(5003,1,'Warranty'),
(5003,2,'Гарантія'),
(5003,3,'Гарантия');
/*!40000 ALTER TABLE `oc_attribute_description` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `oc_attribute_group`
--

DROP TABLE IF EXISTS `oc_attribute_group`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `oc_attribute_group` (
  `attribute_group_id` int(11) NOT NULL AUTO_INCREMENT,
  `sort_order` int(11) NOT NULL,
  PRIMARY KEY (`attribute_group_id`)
) ENGINE=InnoDB AUTO_INCREMENT=503 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `oc_attribute_group`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `oc_attribute_group` WRITE;
/*!40000 ALTER TABLE `oc_attribute_group` DISABLE KEYS */;
INSERT INTO `oc_attribute_group` VALUES
(1,1),
(2,2),
(7,0),
(8,0),
(501,1);
/*!40000 ALTER TABLE `oc_attribute_group` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `oc_attribute_group_description`
--

DROP TABLE IF EXISTS `oc_attribute_group_description`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `oc_attribute_group_description` (
  `attribute_group_id` int(11) NOT NULL,
  `language_id` int(11) NOT NULL,
  `name` varchar(64) NOT NULL,
  PRIMARY KEY (`attribute_group_id`,`language_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `oc_attribute_group_description`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `oc_attribute_group_description` WRITE;
/*!40000 ALTER TABLE `oc_attribute_group_description` DISABLE KEYS */;
INSERT INTO `oc_attribute_group_description` VALUES
(1,1,'General'),
(1,2,'Загальне'),
(1,3,'Общее'),
(2,1,'Flags'),
(2,2,'Характеристики'),
(2,3,'Флаги'),
(501,1,'Specifications'),
(501,2,'Технічні характеристики'),
(501,3,'Технические характеристики');
/*!40000 ALTER TABLE `oc_attribute_group_description` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `oc_banner`
--

DROP TABLE IF EXISTS `oc_banner`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `oc_banner` (
  `banner_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(64) NOT NULL,
  `status` tinyint(1) NOT NULL,
  PRIMARY KEY (`banner_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `oc_banner`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `oc_banner` WRITE;
/*!40000 ALTER TABLE `oc_banner` DISABLE KEYS */;
INSERT INTO `oc_banner` VALUES
(6,'Banner in Categories',1),
(7,'Slideshow on the Homepage',1),
(8,'Brand Carousel',1),
(9,'Advantages on the Homepage',1);
/*!40000 ALTER TABLE `oc_banner` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `oc_banner_description`
--

DROP TABLE IF EXISTS `oc_banner_description`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `oc_banner_description` (
  `banner_id` int(11) NOT NULL,
  `language_id` int(11) NOT NULL,
  `name` varchar(64) NOT NULL,
  PRIMARY KEY (`banner_id`,`language_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `oc_banner_description`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `oc_banner_description` WRITE;
/*!40000 ALTER TABLE `oc_banner_description` DISABLE KEYS */;
INSERT INTO `oc_banner_description` VALUES
(6,1,'Banner in Categories'),
(6,2,'Банер в категоріях'),
(6,3,'Баннер в категориях'),
(7,1,'Slideshow on the Homepage'),
(7,2,'Слайд-шоу на головній сторінці'),
(7,3,'Слайд-шоу на главной странице'),
(8,1,'Brand Carousel'),
(8,2,'Карусель брендів'),
(8,3,'Карусель брендов'),
(9,1,'Advantages on the Homepage'),
(9,2,'Переваги на головній сторінці'),
(9,3,'Преимущества на главной странице');
/*!40000 ALTER TABLE `oc_banner_description` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `oc_banner_image`
--

DROP TABLE IF EXISTS `oc_banner_image`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `oc_banner_image` (
  `banner_image_id` int(11) NOT NULL AUTO_INCREMENT,
  `banner_id` int(11) NOT NULL,
  `language_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `subtitle` varchar(255) NOT NULL DEFAULT '',
  `accent_text` varchar(128) NOT NULL DEFAULT '',
  `accent_color` varchar(16) NOT NULL DEFAULT '',
  `primary_btn_text` varchar(64) NOT NULL DEFAULT '',
  `image` varchar(255) NOT NULL,
  `image_portrait` varchar(255) NOT NULL DEFAULT '',
  `link` varchar(255) NOT NULL DEFAULT '',
  `video_type` varchar(16) NOT NULL DEFAULT '',
  `video` varchar(255) NOT NULL DEFAULT '',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `content_position` varchar(16) NOT NULL DEFAULT 'left',
  PRIMARY KEY (`banner_image_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1310 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `oc_banner_image`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `oc_banner_image` WRITE;
/*!40000 ALTER TABLE `oc_banner_image` DISABLE KEYS */;
INSERT INTO `oc_banner_image` VALUES
(1233,8,1,'Harley Davidson','','','#6366f1','','catalog/demo/manufacturer/harley.png','','','','',0,'left'),
(1234,8,1,'Canon','','','#6366f1','','catalog/demo/manufacturer/canon.png','','','','',1,'left'),
(1235,8,1,'Burger King','','','#6366f1','','catalog/demo/manufacturer/burgerking.png','','','','',2,'left'),
(1236,8,1,'Coca Cola','','','#6366f1','','catalog/demo/manufacturer/cocacola.png','','','','',3,'left'),
(1237,8,1,'Dell','','','#6366f1','','catalog/demo/manufacturer/dell.png','','','','',4,'left'),
(1238,8,1,'Disney','','','#6366f1','','catalog/demo/manufacturer/disney.png','','','','',5,'left'),
(1239,8,1,'NFL','','','#6366f1','','catalog/demo/manufacturer/nfl.png','','','','',6,'left'),
(1240,8,1,'Nintendo','','','#6366f1','','catalog/demo/manufacturer/nintendo.png','','','','',7,'left'),
(1241,8,1,'Red Bull','','','#6366f1','','catalog/demo/manufacturer/redbull.png','','','','',8,'left'),
(1242,8,1,'Shell','','','#6366f1','','catalog/demo/manufacturer/shell.png','','','','',9,'left'),
(1243,8,1,'SONY','','','#6366f1','','catalog/demo/manufacturer/sony.png','','','','',10,'left'),
(1244,8,1,'Starbucks','','','#6366f1','','catalog/demo/manufacturer/starbucks.png','','','','',11,'left'),
(1245,8,2,'Харлі Девідсон','','','#6366f1','','catalog/demo/manufacturer/harley.png','','','','',0,'left'),
(1246,8,2,'Канон','','','#6366f1','','catalog/demo/manufacturer/canon.png','','','','',1,'left'),
(1247,8,2,'Бургер Кінг','','','#6366f1','','catalog/demo/manufacturer/burgerking.png','','','','',2,'left'),
(1248,8,2,'Кока-кола','','','#6366f1','','catalog/demo/manufacturer/cocacola.png','','','','',3,'left'),
(1249,8,2,'Делл','','','#6366f1','','catalog/demo/manufacturer/dell.png','','','','',4,'left'),
(1250,8,2,'Дісней','','','#6366f1','','catalog/demo/manufacturer/disney.png','','','','',5,'left'),
(1251,8,2,'Нінтендо','','','#6366f1','','catalog/demo/manufacturer/nintendo.png','','','','',6,'left'),
(1252,8,2,'Ред Булл','','','#6366f1','','catalog/demo/manufacturer/redbull.png','','','','',7,'left'),
(1253,8,2,'Шелл','','','#6366f1','','catalog/demo/manufacturer/shell.png','','','','',8,'left'),
(1254,8,2,'Старбакс','','','#6366f1','','catalog/demo/manufacturer/starbucks.png','','','','',9,'left'),
(1255,8,3,'Старбакс','','','#6366f1','','catalog/demo/manufacturer/harley.png','','','','',0,'left'),
(1256,8,3,'Канон','','','#6366f1','','catalog/demo/manufacturer/canon.png','','','','',1,'left'),
(1257,8,3,'Бургер Кинг','','','#6366f1','','catalog/demo/manufacturer/burgerking.png','','','','',2,'left'),
(1258,8,3,'Кока-Кола','','','#6366f1','','catalog/demo/manufacturer/cocacola.png','','','','',3,'left'),
(1259,8,3,'Делл','','','#6366f1','','catalog/demo/manufacturer/dell.png','','','','',4,'left'),
(1260,8,3,'Дисней','','','#6366f1','','catalog/demo/manufacturer/disney.png','','','','',5,'left'),
(1261,8,3,'Nintendo','','','#6366f1','','catalog/demo/manufacturer/nintendo.png','','','','',6,'left'),
(1262,8,3,'Ред Булл','','','#6366f1','','catalog/demo/manufacturer/redbull.png','','','','',7,'left'),
(1263,8,3,'Оболочка','','','#6366f1','','catalog/demo/manufacturer/shell.png','','','','',8,'left'),
(1264,8,3,'Старбакс','','','#6366f1','','catalog/demo/manufacturer/starbucks.png','','','','',9,'left'),
(1295,9,1,'Up to 50% Off Electronics','Today only — grab the best deals before they\'re gone.','','#7fa8be','Shop Now','catalog/demo/demo-seed/banners/home-slideshow-1.jpg','','route=product/category&amp;path=101','','',0,'left'),
(1296,9,2,'Знижки до 50% на електроніку','Тільки сьогодні — скористайтеся найкращими пропозиціями, поки вони не зникли.','','#7fa8be','Купуйте зараз','catalog/demo/demo-seed/banners/home-slideshow-1.jpg','','route=product/category&amp;path=101','','',0,'left'),
(1297,9,3,'Скидки до 50% на электронику','Только сегодня — воспользуйтесь лучшими предложениями, пока они не закончились.','','#7fa8be','Купить сейчас','catalog/demo/demo-seed/banners/home-slideshow-1.jpg','','route=product/category&amp;path=101','','',0,'left'),
(1298,6,1,'Active Sports Collection','More than 100 products','','#e8f264','View All','catalog/demo/demo-seed/banners/hp-products-3.jpg','','route=product/category&amp;path=104','','',0,'left'),
(1299,6,2,'Колекція для активного спорту','Більше 100 товарів для вибору','','#e8f264','До каталогу','catalog/demo/demo-seed/banners/hp-products-3.jpg','','route=product/category&amp;path=104','','',0,'left'),
(1300,6,3,'Коллекция спортивных товаров для активного отдыха','Более 100 товаров для выбора','','#e8f264','В каталог','catalog/demo/demo-seed/banners/hp-products-3.jpg','','route=product/category&amp;path=104','','',0,'left'),
(1301,7,1,'Smart Tech for Daily Life','Innovative smart devices that simplify everyday tasks and boost convenience at home and on the go.','Make everyday life smarter','#ffe252','View details','catalog/demo/demo-seed/banners/home-slideshow-1.jpg','','route=product/category&amp;path=101','','',0,'left'),
(1302,7,1,'Style, Comfort, and Essentials','Fashion and home essentials designed for comfort, quality, and effortless style.','Style &amp; Comfort Essentials','#6ef264','View details','catalog/demo/demo-seed/banners/home-slideshow-2.jpg','','route=product/category&amp;path=1971','','',1,'left'),
(1303,7,1,'Everything for Pets &amp; Cars','Accessories and supplies to keep pets happy and cars well-equipped and maintained.','Pets &amp; Auto Supplies','#6366f1','View details','catalog/demo/demo-seed/banners/home-slideshow-3.jpg','','route=product/category&amp;path=109','','',2,'left'),
(1304,7,2,'Розумні технології для повсякденного життя','Інноваційні розумні пристрої, які спрощують повсякденні завдання та підвищують зручність вдома та в дорозі.','Зробіть повсякденне життя розумнішим','#ffe252','Детальніше','catalog/demo/demo-seed/banners/home-slideshow-1.jpg','','route=product/category&amp;path=101','','',0,'left'),
(1305,7,2,'Стиль, комфорт та необхідні речі','Модні та предмети першої необхідності для дому, розроблені для комфорту, якості та бездоганного стилю.','Основні речі стилю та комфорту','#6ef264','Детальніше','catalog/demo/demo-seed/banners/home-slideshow-2.jpg','','route=product/category&amp;path=1971','','',1,'left'),
(1306,7,2,'Все для домашніх тварин та автомобілів','Аксесуари та приладдя для задоволення домашніх тварин та належного обслуговування автомобілів.','Домашні тварини та автотовари','#6366f1','Детальніше','catalog/demo/demo-seed/banners/home-slideshow-3.jpg','','route=product/category&amp;path=109','','',2,'left'),
(1307,7,3,'Техника, которая меняет всё','Новейшие смартфоны, мощные ноутбуки, умные гаджеты и аудио премиум-класса.','Будущее уже здесь','#ffe252','Подробнее','catalog/demo/demo-seed/banners/home-slideshow-1.jpg','','route=product/category&amp;path=101','','',0,'left'),
(1308,7,3,'Стиль, комфорт и необходимые вещи','Модные вещи и товары для дома, созданные для комфорта, качества и непринужденного стиля.','Основные элементы стиля и комфорта','#6ef264','Подробнее','catalog/demo/demo-seed/banners/home-slideshow-2.jpg','','route=product/category&amp;path=1971','','',1,'left'),
(1309,7,3,'Всё для домашних животных и автомобилей','Аксессуары и принадлежности для того, чтобы домашние животные были счастливы, а автомобили были хорошо оборудованы и обслуживались.','Товары для животных и автозапчасти','#6366f1','Подробнее','catalog/demo/demo-seed/banners/home-slideshow-3.jpg','','route=product/category&amp;path=109','','',2,'left');
/*!40000 ALTER TABLE `oc_banner_image` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `oc_blog_author`
--

DROP TABLE IF EXISTS `oc_blog_author`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `oc_blog_author` (
  `author_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `date_added` datetime NOT NULL,
  `date_modified` datetime NOT NULL,
  PRIMARY KEY (`author_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `oc_blog_author`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `oc_blog_author` WRITE;
/*!40000 ALTER TABLE `oc_blog_author` DISABLE KEYS */;
INSERT INTO `oc_blog_author` VALUES
(5,'Admin','admin@example.com','','Blog administrator',1,0,'2026-01-01 11:58:13','2026-01-01 12:28:16'),
(6,'Writter','writter@dockercart.net','','',1,0,'2026-01-22 17:47:20','2026-01-22 17:47:20');
/*!40000 ALTER TABLE `oc_blog_author` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `oc_blog_category`
--

DROP TABLE IF EXISTS `oc_blog_category`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `oc_blog_category` (
  `category_id` int(11) NOT NULL AUTO_INCREMENT,
  `parent_id` int(11) NOT NULL DEFAULT 0,
  `image` varchar(255) DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `date_added` datetime NOT NULL,
  `date_modified` datetime NOT NULL,
  PRIMARY KEY (`category_id`),
  KEY `idx_parent_id` (`parent_id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `oc_blog_category`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `oc_blog_category` WRITE;
/*!40000 ALTER TABLE `oc_blog_category` DISABLE KEYS */;
INSERT INTO `oc_blog_category` VALUES
(1,0,'blog/category-1.jpg',1,1,'2026-03-01 12:58:01','2026-03-01 12:58:01'),
(2,0,'blog/category-2.jpg',1,2,'2026-03-01 12:58:01','2026-03-01 12:58:01');
/*!40000 ALTER TABLE `oc_blog_category` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `oc_blog_category_description`
--

DROP TABLE IF EXISTS `oc_blog_category_description`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `oc_blog_category_description` (
  `category_id` int(11) NOT NULL,
  `language_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `meta_title` varchar(255) NOT NULL,
  `meta_description` varchar(255) NOT NULL,
  `meta_keyword` varchar(255) NOT NULL,
  PRIMARY KEY (`category_id`,`language_id`),
  KEY `idx_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `oc_blog_category_description`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `oc_blog_category_description` WRITE;
/*!40000 ALTER TABLE `oc_blog_category_description` DISABLE KEYS */;
INSERT INTO `oc_blog_category_description` VALUES
(1,1,'Tech Selection Guides','Useful tips and recommendations for choosing electronics and accessories','Tech Selection Guides','How to choose smartphone, laptop, headphones and other electronics','tips, tech, selection, electronics, gadgets'),
(1,2,'Посібники з вибору технологій','Корисні поради та рекомендації щодо вибору електроніки та аксесуарів','Посібники з вибору технологій','Як вибрати смартфон, ноутбук, навушники та іншу електроніку','поради, технології, вибір, електроніка, гаджети'),
(1,3,'Руководства по выбору техники','Полезные советы и рекомендации по выбору электроники и аксессуаров.','Руководства по выбору техники','Как выбрать смартфон, ноутбук, наушники и другую электронику','советы, технологии, выбор, электроника, гаджеты'),
(2,1,'Tech Reviews','Detailed reviews of new and popular gadgets with ratings and feature comparisons','Tech Reviews and Tests','Reviews of smartphones, laptops, tablets and gadgets for 2025-2026','review, test, gadget, design, specifications'),
(2,2,'Огляди технологій','Детальні огляди нових та популярних гаджетів з рейтингами та порівнянням функцій','Технічні огляди та тести','Огляди смартфонів, ноутбуків, планшетів та гаджетів за 2025-2026 роки','огляд, тест, гаджет, дизайн, характеристики'),
(2,3,'Технические обзоры','Подробные обзоры новых и популярных гаджетов с рейтингами и сравнением характеристик.','Технические обзоры и тесты','Обзоры смартфонов, ноутбуков, планшетов и гаджетов на 2025-2026 годы.','обзор, тест, гаджет, дизайн, технические характеристики');
/*!40000 ALTER TABLE `oc_blog_category_description` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `oc_blog_category_to_store`
--

DROP TABLE IF EXISTS `oc_blog_category_to_store`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `oc_blog_category_to_store` (
  `category_id` int(11) NOT NULL,
  `store_id` int(11) NOT NULL,
  PRIMARY KEY (`category_id`,`store_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `oc_blog_category_to_store`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `oc_blog_category_to_store` WRITE;
/*!40000 ALTER TABLE `oc_blog_category_to_store` DISABLE KEYS */;
INSERT INTO `oc_blog_category_to_store` VALUES
(1,0),
(2,0);
/*!40000 ALTER TABLE `oc_blog_category_to_store` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `oc_blog_comment`
--

DROP TABLE IF EXISTS `oc_blog_comment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `oc_blog_comment` (
  `comment_id` int(11) NOT NULL AUTO_INCREMENT,
  `post_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL DEFAULT 0,
  `author` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `text` text NOT NULL,
  `rating` int(11) DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 0,
  `ip` varchar(45) NOT NULL,
  `date_added` datetime NOT NULL,
  `date_modified` datetime NOT NULL,
  PRIMARY KEY (`comment_id`),
  KEY `idx_post_id` (`post_id`),
  KEY `idx_customer_id` (`customer_id`),
  KEY `idx_status` (`status`),
  KEY `idx_date_added` (`date_added`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `oc_blog_comment`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `oc_blog_comment` WRITE;
/*!40000 ALTER TABLE `oc_blog_comment` DISABLE KEYS */;
/*!40000 ALTER TABLE `oc_blog_comment` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `oc_blog_event`
--

DROP TABLE IF EXISTS `oc_blog_event`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `oc_blog_event` (
  `event_id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(64) NOT NULL,
  `trigger` varchar(255) NOT NULL,
  `action` varchar(255) NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`event_id`),
  UNIQUE KEY `idx_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `oc_blog_event`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `oc_blog_event` WRITE;
/*!40000 ALTER TABLE `oc_blog_event` DISABLE KEYS */;
/*!40000 ALTER TABLE `oc_blog_event` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `oc_blog_post`
--

DROP TABLE IF EXISTS `oc_blog_post`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `oc_blog_post` (
  `post_id` int(11) NOT NULL AUTO_INCREMENT,
  `author_id` int(11) NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `featured` tinyint(1) NOT NULL DEFAULT 0,
  `allow_comments` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `views` int(11) NOT NULL DEFAULT 0,
  `date_published` datetime NOT NULL,
  `date_added` datetime NOT NULL,
  `date_modified` datetime NOT NULL,
  PRIMARY KEY (`post_id`),
  KEY `idx_author_id` (`author_id`),
  KEY `idx_status` (`status`),
  KEY `idx_featured` (`featured`),
  KEY `idx_date_published` (`date_published`),
  KEY `idx_views` (`views`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `oc_blog_post`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `oc_blog_post` WRITE;
/*!40000 ALTER TABLE `oc_blog_post` DISABLE KEYS */;
INSERT INTO `oc_blog_post` VALUES
(1,5,'blog/post-1-smartphones.jpg',1,1,1,1,184,'2026-05-05 21:30:56','2026-03-01 12:58:01','2026-06-07 09:17:11'),
(2,1,'blog/post-2-laptops.jpg',1,1,1,2,66,'2026-02-26 12:58:01','2026-02-26 12:58:01','2026-02-26 12:58:01'),
(3,1,'blog/post-3-headphones.jpg',1,0,1,3,19,'2026-02-22 12:58:01','2026-02-22 12:58:01','2026-02-22 12:58:01'),
(4,1,'blog/post-4-cameras.jpg',1,1,1,4,0,'2026-02-19 12:58:01','2026-02-19 12:58:01','2026-02-19 12:58:01'),
(5,1,'blog/post-5-monitors.jpg',1,0,1,5,9,'2026-02-14 12:58:01','2026-02-14 12:58:01','2026-02-14 12:58:01'),
(6,1,'blog/post-6-tablets.jpg',1,1,1,6,0,'2026-02-09 12:58:01','2026-02-09 12:58:01','2026-02-09 12:58:01'),
(7,1,'blog/post-7-keyboards.jpg',1,0,1,7,1,'2026-02-04 12:58:01','2026-02-04 12:58:01','2026-02-04 12:58:01');
/*!40000 ALTER TABLE `oc_blog_post` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `oc_blog_post_description`
--

DROP TABLE IF EXISTS `oc_blog_post_description`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `oc_blog_post_description` (
  `post_id` int(11) NOT NULL,
  `language_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `content` mediumtext NOT NULL,
  `meta_title` varchar(255) NOT NULL,
  `meta_description` varchar(255) NOT NULL,
  `meta_keyword` varchar(255) NOT NULL,
  PRIMARY KEY (`post_id`,`language_id`),
  KEY `idx_name` (`name`),
  FULLTEXT KEY `idx_fulltext` (`name`,`description`,`content`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `oc_blog_post_description`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `oc_blog_post_description` WRITE;
/*!40000 ALTER TABLE `oc_blog_post_description` DISABLE KEYS */;
INSERT INTO `oc_blog_post_description` VALUES
(1,1,'How to Choose a Smartphone in 2025','A comprehensive guide on selecting the right smartphone considering all important specs and features','&lt;h2&gt;Determine Your Budget&lt;/h2&gt;\r\n&lt;p&gt;The first step when choosing a smartphone is to determine your budget. The market offers devices from affordable entry-level models to premium flagships.&lt;/p&gt;\r\n&lt;h2&gt;Screen Size and Resolution&lt;/h2&gt;\r\n&lt;p&gt;Choose a comfortable screen size for you. Large screens (6&quot;-6.8&quot;) are perfect for watching videos and gaming, while compact models (5.5&quot;-6&quot;) are easier to hold.&lt;/p&gt;\r\n&lt;h2&gt;Processor and RAM&lt;/h2&gt;\r\n&lt;p&gt;A powerful processor ensures fast operation of applications, games, and multimedia. At least 8GB of RAM is recommended for comfortable work.&lt;/p&gt;\r\n&lt;h2&gt;Camera Quality&lt;/h2&gt;\r\n&lt;p&gt;Camera is one of the main features of a modern smartphone. Pay attention to megapixels, sensor quality, and night photography capabilities.&lt;/p&gt;','How to Choose the Right Smartphone','Comprehensive guide to selecting a smartphone with processor, camera, and battery considerations','smartphone, guide, purchase, technology, gadget'),
(1,2,'Як вибрати смартфон у 2025 році','Вичерпний посібник з вибору правильного смартфона, враховуючи всі важливі характеристики та функції','&lt;h2&gt;Визначте свій бюджет&lt;/h2&gt;\r\n&lt;p&gt;Перший крок при виборі смартфона &amp;ndash; це визначення вашого бюджету. Ринок пропонує пристрої від доступних моделей початкового рівня до преміальних флагманів.&lt;/p&gt;\r\n&lt;h2&gt;Розмір і роздільна здатність екрана&lt;/h2&gt;\r\n&lt;p&gt;Оберіть зручний для вас розмір екрана. Великі екрани (6-6,8 дюйма) ідеально підходять для перегляду відео та ігор, тоді як компактні моделі (5,5-6 дюймів) зручніше тримати в руці.&lt;/p&gt;\r\n&lt;h2&gt;Процесор і оперативна пам\'ять&lt;/h2&gt;\r\n&lt;p&gt;Потужний процесор забезпечує швидку роботу програм, ігор та мультимедіа. Для комфортної роботи рекомендується щонайменше 8 ГБ оперативної пам\'яті.&lt;/p&gt;\r\n&lt;h2&gt;Якість камери&lt;/h2&gt;\r\n&lt;p&gt;Камера &amp;ndash; одна з головних характеристик сучасного смартфона. Зверніть увагу на мегапікселі, якість сенсора та можливості нічної зйомки.&lt;/p&gt;','Як вибрати правильний смартфон','Вичерпний посібник з вибору смартфона з урахуванням процесора, камери та акумулятора','смартфон, путівник, покупка, технологія, гаджет'),
(1,3,'Как выбрать смартфон в 2025 году','Подробное руководство по выбору подходящего смартфона с учетом всех важных характеристик и функций.','&lt;h2&gt;Определите свой бюджет&lt;/h2&gt;\r\n&lt;p&gt;Первый шаг при выборе смартфона &amp;mdash; определить свой бюджет. На рынке представлены устройства от доступных моделей начального уровня до флагманских устройств премиум-класса.&lt;/p&gt;\r\n&lt;h2&gt;Размер и разрешение экрана&lt;/h2&gt;\r\n&lt;p&gt;Выберите удобный для вас размер экрана. Большие экраны (6&amp;ndash;6,8 дюймов) идеально подходят для просмотра видео и игр, а компактные модели (5,5&amp;ndash;6 дюймов) удобнее держать в руке.&lt;/p&gt;\r\n&lt;h2&gt;Процессор и оперативная память&lt;/h2&gt;\r\n&lt;p&gt;Мощный процессор обеспечивает быструю работу приложений, игр и мультимедиа. Для комфортной работы рекомендуется не менее 8 ГБ оперативной памяти.&lt;/p&gt;\r\n&lt;h2&gt;Качество камеры&lt;/h2&gt;\r\n&lt;p&gt;Камера &amp;mdash; одна из главных характеристик современного смартфона. Обратите внимание на количество мегапикселей, качество сенсора и возможности ночной съемки.&lt;/p&gt;','Как выбрать подходящий смартфон','Подробное руководство по выбору смартфона с учетом процессора, камеры и батареи.','смартфон, руководство, покупка, технология, гаджет'),
(2,1,'Best Laptops 2025: Review and Comparison','Review of top laptops for 2025 with detailed feature and price comparisons','<h2>Laptops for Work</h2><p>For everyday office work, laptops with Intel Core i5/i7 or AMD Ryzen 5/7 processors and 16GB of RAM are suitable.</p>\n<h2>Laptops for Gaming</h2><p>Gamers should pay attention to laptops with powerful NVIDIA RTX 40-50 series graphics cards and high refresh rate screens.</p>\n<h2>Laptops for Creative Work</h2><p>For video editing and graphics work, choose laptops with maximum processor and GPU power, with at least 32GB of memory.</p>','Best Laptops 2025','Review of top laptops 2025 for work, gaming, and creativity','laptop, review, processor, graphics, work'),
(2,2,'Найкращі ноутбуки 2025 року: огляд та порівняння','Огляд найкращих ноутбуків 2025 року з детальним порівнянням характеристик та цін','<h2>Ноутбуки для роботи</h2><p> Для повсякденної офісної роботи підійдуть ноутбуки з процесорами Intel Core i5/i7 або AMD Ryzen 5/7 та 16 ГБ оперативної пам\'яті.</p><h2> Ноутбуки для ігор</h2><p> Геймерам варто звернути увагу на ноутбуки з потужними відеокартами NVIDIA RTX серії 40-50 та екранами з високою частотою оновлення.</p><h2> Ноутбуки для творчої роботи</h2><p> Для редагування відео та роботи з графікою вибирайте ноутбуки з максимальною потужністю процесора та графічного процесора, щонайменше 32 ГБ пам\'яті.</p>','Найкращі ноутбуки 2025 року','Огляд найкращих ноутбуків 2025 року для роботи, ігор та творчості','ноутбук, огляд, процесор, графіка, робота'),
(2,3,'Лучшие ноутбуки 2025 года: обзор и сравнение.','Обзор лучших ноутбуков 2025 года с подробным сравнением характеристик и цен.','<h2>Ноутбуки для работы</h2><p> Для повседневной офисной работы подойдут ноутбуки с процессорами Intel Core i5/i7 или AMD Ryzen 5/7 и 16 ГБ оперативной памяти.</p><h2> Ноутбуки для игр</h2><p> Геймерам следует обратить внимание на ноутбуки с мощными видеокартами NVIDIA RTX 40-50 серии и экранами с высокой частотой обновления.</p><h2> Ноутбуки для творческой работы</h2><p> Для видеомонтажа и работы с графикой выбирайте ноутбуки с максимальной мощностью процессора и видеокарты, а также с объемом оперативной памяти не менее 32 ГБ.</p>','Лучшие ноутбуки 2025 года','Обзор лучших ноутбуков 2025 года для работы, игр и творчества.','ноутбук, обзор, процессор, графика, работа'),
(3,1,'How to Choose Headphones: Complete Guide','Detailed guide on selecting the right headphones based on your preferences','<h2>Types of Headphones</h2><p>There are several types of headphones: over-ear, in-ear, closed-back, and sports models. Each type has its own advantages and disadvantages.</p>\n<h2>Sound Quality</h2><p>Pay attention to frequency range (20Hz-20kHz), impedance, and sensitivity. A wider range means better music reproduction.</p>\n<h2>Comfort and Fit</h2><p>Headphones should fit well and not cause discomfort during extended use. Try them before purchasing.</p>','Complete Guide: How to Choose Headphones','Tips for selecting quality headphones for music, sports, and daily use','headphones, guide, sound, music, audio'),
(3,2,'Як вибрати навушники: повний посібник','Детальний посібник з вибору правильних навушників відповідно до ваших уподобань','<h2>Типи навушників</h2><p> Існує кілька типів навушників: накладні, внутрішньоканальні, закриті та спортивні моделі. Кожен тип має свої переваги та недоліки.</p><h2> Якість звуку</h2><p> Зверніть увагу на діапазон частот (20 Гц-20 кГц), імпеданс та чутливість. Ширший діапазон означає краще відтворення музики.</p><h2> Комфорт та посадка</h2><p> Навушники повинні добре сидіти та не викликати дискомфорту під час тривалого використання. Приміряйте їх перед покупкою.</p>','Повний посібник: Як вибрати навушники','Поради щодо вибору якісних навушників для музики, спорту та щоденного використання','навушники, гід, звук, музика, аудіо'),
(3,3,'Как выбрать наушники: полное руководство','Подробное руководство по выбору подходящих наушников в зависимости от ваших предпочтений.','<h2>Виды наушников</h2><p> Существует несколько типов наушников: накладные, внутриканальные, закрытые и спортивные. Каждый тип имеет свои преимущества и недостатки.</p><h2> Качество звука</h2><p> Обратите внимание на частотный диапазон (20 Гц-20 кГц), импеданс и чувствительность. Более широкий диапазон означает лучшее воспроизведение музыки.</p><h2> Комфорт и посадка</h2><p> Наушники должны хорошо сидеть и не вызывать дискомфорта при длительном использовании. Перед покупкой обязательно протестируйте их.</p>','Полное руководство: Как выбрать наушники','Советы по выбору качественных наушников для прослушивания музыки, занятий спортом и повседневного использования.','наушники, руководство, звук, музыка, аудио'),
(4,1,'Everything About Smartphone Cameras','Complete breakdown of how smartphone cameras work and what to look for','<h2>Megapixels and Sensor Size</h2><p>While megapixels matter, sensor size is even more important. A larger sensor collects more light and improves low-light photo quality.</p>\n<h2>Aperture and Stabilization</h2><p>Aperture (f-number) affects light intake. Optical image stabilization (OIS) helps prevent blur when shooting.</p>\n<h2>Zoom and Telephoto</h2><p>Optical zoom provides better quality than digital zoom. Telephoto lenses are useful for distant shots without losing detail.</p>','Smartphone Cameras: Complete Guide','How smartphone cameras work and what to consider when choosing','camera, smartphone, photography, sensor, zoom'),
(4,2,'Все про камери смартфонів','Повний розбір того, як працюють камери смартфонів і на що звертати увагу','<h2>Мегапікселі та розмір сенсора</h2><p> Хоча мегапікселі мають значення, розмір сенсора ще важливіший. Більший сенсор збирає більше світла та покращує якість фотографій за умов слабкого освітлення.</p><h2> Діафрагма та стабілізація</h2><p> Діафрагма (f-число) впливає на споживання світла. Оптична стабілізація зображення (OIS) допомагає запобігти розмиття під час зйомки.</p><h2> Зум та телеоб\'єктив</h2><p> Оптичний зум забезпечує кращу якість, ніж цифровий. Телеоб\'єктиви корисні для зйомки на відстані без втрати деталей.</p>','Камери смартфонів: повний посібник','Як працюють камери смартфонів і що враховувати під час вибору','камера, смартфон, фотографія, сенсор, масштабування'),
(4,3,'Всё о камерах смартфонов','Подробный разбор принципа работы камер смартфонов и на что следует обращать внимание.','<h2>Мегапиксели и размер сенсора</h2><p> Хотя количество мегапикселей имеет значение, размер сенсора еще важнее. Больший сенсор собирает больше света и улучшает качество фотографий, сделанных в условиях недостаточного освещения.</p><h2> Апертура и стабилизация</h2><p> Диафрагма (число f) влияет на светоотдачу. Оптическая стабилизация изображения (OIS) помогает предотвратить размытие при съемке.</p><h2> зум и телеобъектив</h2><p> Оптический зум обеспечивает лучшее качество, чем цифровой. Телеобъективы полезны для съемки удаленных объектов без потери детализации.</p>','Камеры смартфонов: полное руководство','Как работают камеры смартфонов и на что следует обращать внимание при выборе.','камера, смартфон, фотография, сенсор, зум'),
(5,1,'How to Choose a Monitor for Office Work','Practical tips on selecting a monitor for comfortable and productive office work','<h2>Size and Resolution</h2><p>For office work, a 24-27 inch monitor with 1920x1080 (Full HD) resolution or higher is recommended for comfortable document and spreadsheet work.</p>\n<h2>Refresh Rate and Viewing Angles</h2><p>60Hz is enough for office work. Wide viewing angles are important so the image is visible from all sides during collaborative work.</p>\n<h2>Protection from Eye Strain</h2><p>Choose monitors with blue light reduction and flicker-free features to reduce eye strain during extended work.</p>','Choosing a Monitor for Office','How to choose the perfect monitor for working with documents','monitor, office, work, screen, ergonomics'),
(5,2,'Як вибрати монітор для офісної роботи','Практичні поради щодо вибору монітора для комфортної та продуктивної роботи в офісі','<h2>Розмір і роздільна здатність</h2><p> Для офісної роботи рекомендується монітор з діагоналлю 24-27 дюймів та роздільною здатністю 1920x1080 (Full HD) або вище, щоб забезпечити комфортну роботу з документами та електронними таблицями.</p><h2> Частота оновлення та кути огляду</h2><p> 60 Гц достатньо для офісної роботи. Широкі кути огляду важливі, щоб зображення було видно з усіх боків під час спільної роботи.</p><h2> Захист від перенапруження очей</h2><p> Оберіть монітори з функціями зменшення синього світла та усунення мерехтіння, щоб зменшити навантаження на очі під час тривалої роботи.</p>','Вибір монітора для офісу','Як вибрати ідеальний монітор для роботи з документами','монітор, офіс, робота, екран, ергономіка'),
(5,3,'Как выбрать монитор для офисной работы','Практические советы по выбору монитора для комфортной и продуктивной работы в офисе.','<h2>Размер и разрешение</h2><p> Для офисной работы рекомендуется монитор диагональю 24-27 дюймов с разрешением 1920x1080 (Full HD) или выше, обеспечивающим комфортную работу с документами и электронными таблицами.</p><h2> Частота обновления и углы обзора</h2><p> 60 Гц достаточно для офисной работы. Широкие углы обзора важны, чтобы изображение было видно со всех сторон во время совместной работы.</p><h2> Защита от перенапряжения глаз</h2><p> Выбирайте мониторы с функцией подавления синего света и отсутствием мерцания, чтобы уменьшить нагрузку на глаза во время длительной работы.</p>','Выбор монитора для офиса','Как выбрать идеальный монитор для работы с документами','монитор, офис, работа, экран, эргономика'),
(6,1,'Review of New Tablets 2025: Best Models','Complete review of top tablets for 2025 with ratings and recommendations','<h2>Tablets for Learning</h2><p>For students and schoolchildren, 10-11 inch tablets with good stylus sensitivity and long battery life are suitable.</p>\n<h2>Tablets for Entertainment</h2><p>For watching videos and gaming, choose tablets with large screens (11-13 inches), high refresh rates (90-120Hz), and stereo sound.</p>\n<h2>Tablets for Professionals</h2><p>Designers and video editors should focus on high screen resolution, accurate color reproduction, and fast processors.</p>','Best Tablets 2025','Review of top tablets 2025 for learning, entertainment, and professional work','tablet, review, mobile, screen, performance'),
(6,2,'Огляд нових планшетів 2025 року: найкращі моделі','Повний огляд найкращих планшетів 2025 року з рейтингами та рекомендаціями','<h2>Планшети для навчання</h2><p> Для студентів та школярів підійдуть планшети діагоналлю 10-11 дюймів з хорошою чутливістю стилуса та тривалим часом автономної роботи.</p><h2> Планшети для розваг</h2><p> Для перегляду відео та ігор обирайте планшети з великими екранами (11-13 дюймів), високою частотою оновлення (90-120 Гц) та стереозвуком.</p><h2> Планшети для професіоналів</h2><p> Дизайнерам та відеоредакторам слід зосередитися на високій роздільній здатності екрана, точному відтворенні кольорів та швидких процесорах.</p>','Найкращі планшети 2025 року','Огляд найкращих планшетів 2025 року для навчання, розваг та професійної роботи','планшет, огляд, мобільний, екран, продуктивність'),
(6,3,'Обзор новых планшетов 2025 года: лучшие модели.','Полный обзор лучших планшетов 2025 года с рейтингами и рекомендациями.','<h2>Планшеты для обучения</h2><p> Для студентов и школьников подойдут планшеты с диагональю экрана 10-11 дюймов, хорошей чувствительностью стилуса и длительным временем автономной работы.</p><h2> Планшеты для развлечений</h2><p> Для просмотра видео и игр выбирайте планшеты с большими экранами (11-13 дюймов), высокой частотой обновления (90-120 Гц) и стереозвуком.</p><h2> Планшеты для профессионалов</h2><p> Дизайнерам и видеоредакторам следует уделять особое внимание высокому разрешению экрана, точной цветопередаче и быстрым процессорам.</p>','Лучшие планшеты 2025 года','Обзор лучших планшетов 2025 года для обучения, развлечений и профессиональной работы.','планшет, обзор, мобильный, экран, производительность'),
(7,1,'Choosing a Keyboard for Office: Complete Guide','Detailed guide on selecting a comfortable and reliable keyboard for office work','<h2>Mechanical or Membrane Keyboard</h2><p>Mechanical keyboards offer better tactile feedback and durability but are more expensive. Membrane keyboards are quieter and cheaper but less durable.</p>\n<h2>Switch Types</h2><p>Red switches are good for speed, blue for tactile feedback, brown for a balance. Choose based on your preferences.</p>\n<h2>Ergonomics and Comfort</h2><p>A good keyboard should have a comfortable angle, wrist rest, and proper desk height placement.</p>','How to Choose a Keyboard for Office','Recommendations for selecting a comfortable keyboard to help with productive work','keyboard, office, mechanical, switches, accessories'),
(7,2,'Вибір клавіатури для офісу: повний посібник','Детальний посібник з вибору зручної та надійної клавіатури для офісної роботи','<h2>Механічна або мембранна клавіатура</h2><p> Механічні клавіатури пропонують кращий тактильний зворотний зв\'язок і довговічність, але дорожчі. Мембранні клавіатури тихіші та дешевші, але менш довговічні.</p><h2> Типи перемикачів</h2><p> Червоні перемикачі підходять для швидкості, сині для тактильного зворотного зв\'язку, коричневі для балансу. Вибирайте на основі своїх уподобань.</p><h2> Ергономіка та комфорт</h2><p> Гарна клавіатура повинна мати зручний кут нахилу, підставку для зап\'ястя та правильне розташування на висоті столу.</p>','Як вибрати клавіатуру для офісу','Рекомендації щодо вибору зручної клавіатури для продуктивної роботи','клавіатура, офіс, механічна, перемикачі, аксесуари'),
(7,3,'Выбор клавиатуры для офисных приложений: полное руководство','Подробное руководство по выбору удобной и надежной клавиатуры для офисной работы.','<h2>Механическая или мембранная клавиатура</h2><p> Механические клавиатуры обеспечивают лучшую тактильную отдачу и долговечность, но стоят дороже. Мембранные клавиатуры тише и дешевле, но менее долговечны.</p><h2> Типы переключателей</h2><p> Красные переключатели хороши для скорости, синие — для тактильной отдачи, коричневые — для баланса. Выбирайте в зависимости от ваших предпочтений.</p><h2> Эргономика и комфорт</h2><p> Хорошая клавиатура должна иметь удобный угол наклона, подставку для запястий и располагаться на правильной высоте на столе.</p>','Как выбрать клавиатуру для офиса','Рекомендации по выбору удобной клавиатуры для повышения производительности труда.','клавиатура, офисная, механическая, переключатели, аксессуары');
/*!40000 ALTER TABLE `oc_blog_post_description` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `oc_blog_post_tag`
--

DROP TABLE IF EXISTS `oc_blog_post_tag`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `oc_blog_post_tag` (
  `post_id` int(11) NOT NULL,
  `language_id` int(11) NOT NULL,
  `tag` varchar(255) NOT NULL,
  KEY `idx_post_id` (`post_id`),
  KEY `idx_tag` (`tag`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `oc_blog_post_tag`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `oc_blog_post_tag` WRITE;
/*!40000 ALTER TABLE `oc_blog_post_tag` DISABLE KEYS */;
INSERT INTO `oc_blog_post_tag` VALUES
(2,1,'laptops'),
(2,1,'review'),
(2,1,'processors'),
(2,2,'процесори'),
(2,2,'процесори'),
(2,2,'процесори'),
(3,1,'headphones'),
(3,1,'guide'),
(3,1,'sound'),
(3,2,'звук'),
(3,2,'звук'),
(3,2,'звук'),
(4,1,'camera'),
(4,1,'smartphone'),
(4,1,'photography'),
(4,2,'фотографія'),
(4,2,'фотографія'),
(4,2,'фотографія'),
(5,1,'monitor'),
(5,1,'work'),
(5,1,'screen'),
(5,2,'екран'),
(5,2,'екран'),
(5,2,'екран'),
(6,1,'tablet'),
(6,1,'review'),
(6,1,'mobile'),
(6,2,'мобільний'),
(6,2,'мобільний'),
(6,2,'мобільний'),
(7,1,'keyboard'),
(7,1,'office'),
(7,1,'accessories'),
(7,2,'аксесуари'),
(7,2,'аксесуари'),
(7,2,'аксесуари'),
(2,3,'процессоры'),
(3,3,'звук'),
(4,3,'фотография'),
(5,3,'экран'),
(6,3,'мобильный'),
(7,3,'аксессуары'),
(1,1,'smartphones'),
(1,1,'guide'),
(1,1,'technology'),
(1,2,'смартфони'),
(1,2,'путівник'),
(1,2,'технології'),
(1,3,'технологии');
/*!40000 ALTER TABLE `oc_blog_post_tag` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `oc_blog_post_to_category`
--

DROP TABLE IF EXISTS `oc_blog_post_to_category`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `oc_blog_post_to_category` (
  `post_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  PRIMARY KEY (`post_id`,`category_id`),
  KEY `idx_category_id` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `oc_blog_post_to_category`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `oc_blog_post_to_category` WRITE;
/*!40000 ALTER TABLE `oc_blog_post_to_category` DISABLE KEYS */;
INSERT INTO `oc_blog_post_to_category` VALUES
(1,1),
(3,1),
(5,1),
(7,1),
(2,2),
(4,2),
(6,2);
/*!40000 ALTER TABLE `oc_blog_post_to_category` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `oc_blog_post_to_manufacturer`
--

DROP TABLE IF EXISTS `oc_blog_post_to_manufacturer`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `oc_blog_post_to_manufacturer` (
  `post_id` int(11) NOT NULL,
  `manufacturer_id` int(11) NOT NULL,
  PRIMARY KEY (`post_id`,`manufacturer_id`),
  KEY `idx_manufacturer_id` (`manufacturer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `oc_blog_post_to_manufacturer`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `oc_blog_post_to_manufacturer` WRITE;
/*!40000 ALTER TABLE `oc_blog_post_to_manufacturer` DISABLE KEYS */;
/*!40000 ALTER TABLE `oc_blog_post_to_manufacturer` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `oc_blog_post_to_product`
--

DROP TABLE IF EXISTS `oc_blog_post_to_product`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `oc_blog_post_to_product` (
  `post_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  PRIMARY KEY (`post_id`,`product_id`),
  KEY `idx_product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `oc_blog_post_to_product`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `oc_blog_post_to_product` WRITE;
/*!40000 ALTER TABLE `oc_blog_post_to_product` DISABLE KEYS */;
INSERT INTO `oc_blog_post_to_product` VALUES
(1,5001),
(1,5006),
(1,5037),
(1,5077);
/*!40000 ALTER TABLE `oc_blog_post_to_product` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `oc_blog_post_to_product_category`
--

DROP TABLE IF EXISTS `oc_blog_post_to_product_category`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `oc_blog_post_to_product_category` (
  `post_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  PRIMARY KEY (`post_id`,`category_id`),
  KEY `idx_category_id` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `oc_blog_post_to_product_category`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `oc_blog_post_to_product_category` WRITE;
/*!40000 ALTER TABLE `oc_blog_post_to_product_category` DISABLE KEYS */;
/*!40000 ALTER TABLE `oc_blog_post_to_product_category` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `oc_blog_post_to_store`
--

DROP TABLE IF EXISTS `oc_blog_post_to_store`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `oc_blog_post_to_store` (
  `post_id` int(11) NOT NULL,
  `store_id` int(11) NOT NULL,
  PRIMARY KEY (`post_id`,`store_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `oc_blog_post_to_store`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `oc_blog_post_to_store` WRITE;
/*!40000 ALTER TABLE `oc_blog_post_to_store` DISABLE KEYS */;
INSERT INTO `oc_blog_post_to_store` VALUES
(1,0),
(2,0),
(3,0),
(4,0),
(5,0),
(6,0),
(7,0);
/*!40000 ALTER TABLE `oc_blog_post_to_store` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `oc_blog_seo_url`
--

DROP TABLE IF EXISTS `oc_blog_seo_url`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `oc_blog_seo_url` (
  `seo_url_id` int(11) NOT NULL AUTO_INCREMENT,
  `store_id` int(11) NOT NULL,
  `language_id` int(11) NOT NULL,
  `query` varchar(255) NOT NULL,
  `keyword` varchar(255) NOT NULL,
  PRIMARY KEY (`seo_url_id`),
  UNIQUE KEY `idx_query_store_language` (`query`,`store_id`,`language_id`),
  UNIQUE KEY `idx_keyword_store_language` (`keyword`,`store_id`,`language_id`)
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `oc_blog_seo_url`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `oc_blog_seo_url` WRITE;
/*!40000 ALTER TABLE `oc_blog_seo_url` DISABLE KEYS */;
INSERT INTO `oc_blog_seo_url` VALUES
(27,0,1,'blog_post_id=1','how-to-choose-a-smartphone-in-2025'),
(28,0,2,'blog_post_id=1','yak-vibrati-smartfon-u-2025-rotsi'),
(29,0,3,'blog_post_id=1','kak-vybrat-smartfon-v-2025-godu');
/*!40000 ALTER TABLE `oc_blog_seo_url` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `oc_blog_setting`
--

DROP TABLE IF EXISTS `oc_blog_setting`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `oc_blog_setting` (
  `setting_id` int(11) NOT NULL AUTO_INCREMENT,
  `store_id` int(11) NOT NULL DEFAULT 0,
  `code` varchar(128) NOT NULL,
  `key` varchar(128) NOT NULL,
  `value` text NOT NULL,
  `serialized` tinyint(1) NOT NULL,
  PRIMARY KEY (`setting_id`),
  KEY `idx_store_code` (`store_id`,`code`)
) ENGINE=InnoDB AUTO_INCREMENT=51 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `oc_blog_setting`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `oc_blog_setting` WRITE;
/*!40000 ALTER TABLE `oc_blog_setting` DISABLE KEYS */;
INSERT INTO `oc_blog_setting` VALUES
(1,0,'module_dockercart_blog','module_dockercart_blog_status','1',0),
(2,0,'module_dockercart_blog','module_dockercart_blog_posts_per_page','10',0),
(3,0,'module_dockercart_blog','module_dockercart_blog_allow_comments','1',0),
(4,0,'module_dockercart_blog','module_dockercart_blog_moderate_comments','1',0),
(5,0,'module_dockercart_blog','module_dockercart_blog_captcha','1',0),
(6,0,'module_dockercart_blog','module_dockercart_blog_show_author','1',0),
(7,0,'module_dockercart_blog','module_dockercart_blog_show_date','1',0),
(8,0,'module_dockercart_blog','module_dockercart_blog_show_views','1',0),
(9,0,'module_dockercart_blog','module_dockercart_blog_latest_limit','5',0),
(10,0,'module_dockercart_blog','module_dockercart_blog_sitemap','1',0),
(11,0,'module_dockercart_blog','module_dockercart_blog_status','1',0),
(12,0,'module_dockercart_blog','module_dockercart_blog_posts_per_page','10',0),
(13,0,'module_dockercart_blog','module_dockercart_blog_allow_comments','1',0),
(14,0,'module_dockercart_blog','module_dockercart_blog_moderate_comments','1',0),
(15,0,'module_dockercart_blog','module_dockercart_blog_captcha','1',0),
(16,0,'module_dockercart_blog','module_dockercart_blog_show_author','1',0),
(17,0,'module_dockercart_blog','module_dockercart_blog_show_date','1',0),
(18,0,'module_dockercart_blog','module_dockercart_blog_show_views','1',0),
(19,0,'module_dockercart_blog','module_dockercart_blog_latest_limit','5',0),
(20,0,'module_dockercart_blog','module_dockercart_blog_sitemap','1',0),
(21,0,'module_dockercart_blog','module_dockercart_blog_status','1',0),
(22,0,'module_dockercart_blog','module_dockercart_blog_posts_per_page','10',0),
(23,0,'module_dockercart_blog','module_dockercart_blog_allow_comments','1',0),
(24,0,'module_dockercart_blog','module_dockercart_blog_moderate_comments','1',0),
(25,0,'module_dockercart_blog','module_dockercart_blog_captcha','1',0),
(26,0,'module_dockercart_blog','module_dockercart_blog_show_author','1',0),
(27,0,'module_dockercart_blog','module_dockercart_blog_show_date','1',0),
(28,0,'module_dockercart_blog','module_dockercart_blog_show_views','1',0),
(29,0,'module_dockercart_blog','module_dockercart_blog_latest_limit','5',0),
(30,0,'module_dockercart_blog','module_dockercart_blog_sitemap','1',0),
(31,0,'module_dockercart_blog','module_dockercart_blog_status','1',0),
(32,0,'module_dockercart_blog','module_dockercart_blog_posts_per_page','10',0),
(33,0,'module_dockercart_blog','module_dockercart_blog_allow_comments','1',0),
(34,0,'module_dockercart_blog','module_dockercart_blog_moderate_comments','1',0),
(35,0,'module_dockercart_blog','module_dockercart_blog_captcha','1',0),
(36,0,'module_dockercart_blog','module_dockercart_blog_show_author','1',0),
(37,0,'module_dockercart_blog','module_dockercart_blog_show_date','1',0),
(38,0,'module_dockercart_blog','module_dockercart_blog_show_views','1',0),
(39,0,'module_dockercart_blog','module_dockercart_blog_latest_limit','5',0),
(40,0,'module_dockercart_blog','module_dockercart_blog_sitemap','1',0),
(41,0,'module_dockercart_blog','module_dockercart_blog_status','1',0),
(42,0,'module_dockercart_blog','module_dockercart_blog_posts_per_page','10',0),
(43,0,'module_dockercart_blog','module_dockercart_blog_allow_comments','1',0),
(44,0,'module_dockercart_blog','module_dockercart_blog_moderate_comments','1',0),
(45,0,'module_dockercart_blog','module_dockercart_blog_captcha','1',0),
(46,0,'module_dockercart_blog','module_dockercart_blog_show_author','1',0),
(47,0,'module_dockercart_blog','module_dockercart_blog_show_date','1',0),
(48,0,'module_dockercart_blog','module_dockercart_blog_show_views','1',0),
(49,0,'module_dockercart_blog','module_dockercart_blog_latest_limit','5',0),
(50,0,'module_dockercart_blog','module_dockercart_blog_sitemap','1',0);
/*!40000 ALTER TABLE `oc_blog_setting` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `oc_cart`
--

DROP TABLE IF EXISTS `oc_cart`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `oc_cart` (
  `cart_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `api_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `session_id` varchar(32) NOT NULL,
  `product_id` int(11) NOT NULL,
  `recurring_id` int(11) NOT NULL,
  `option` mediumtext NOT NULL,
  `quantity` decimal(15,2) NOT NULL,
  `date_added` datetime NOT NULL,
  PRIMARY KEY (`cart_id`),
  KEY `cart_id` (`api_id`,`customer_id`,`session_id`,`product_id`,`recurring_id`)
) ENGINE=InnoDB AUTO_INCREMENT=907 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `oc_cart`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `oc_cart` WRITE;
/*!40000 ALTER TABLE `oc_cart` DISABLE KEYS */;
INSERT INTO `oc_cart` VALUES
(23,0,1,'aec13783b7deaef064fd472163',40,0,'[]',1.00,'2025-12-06 08:34:36'),
(38,0,2,'498fde754a83274b04b90035ae',43,0,'[]',1.00,'2025-12-12 14:52:52'),
(174,0,3,'9fa7582aee1fa976d784906d77',5001,0,'{\"9001\":\"9501\",\"9002\":\"9503\"}',1.00,'2026-03-03 13:44:26'),
(175,0,3,'9fa7582aee1fa976d784906d77',5031,0,'{\"9061\":\"9622\",\"9062\":\"9624\"}',1.00,'2026-03-03 20:49:34'),
(777,0,6,'142b62fca194c3f9d91ca06f15',5011,0,'{\"9021\":\"9541\",\"9022\":\"9543\"}',1.00,'2026-04-10 16:06:29'),
(779,0,7,'142b62fca194c3f9d91ca06f15',5023,0,'{\"9045\":\"9589\",\"9046\":\"9591\"}',1.00,'2026-04-14 11:18:24'),
(835,0,8,'0a889eddeefcb9e98de2c00e65',5023,0,'{\"9045\":\"9589\",\"9046\":\"9591\"}',1.00,'2026-04-30 18:38:42'),
(896,0,0,'8874573203432d7a6905172ab5',5006,0,'{\"9011\":\"9521\",\"9012\":\"9523\"}',1.00,'2026-06-09 09:13:39'),
(906,0,9,'e7e468f477bd0fac15ca8ea890',5070,0,'{\"9139\":\"9777\",\"9140\":\"9779\"}',1.00,'2026-06-24 15:40:47');
/*!40000 ALTER TABLE `oc_cart` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `oc_category`
--

DROP TABLE IF EXISTS `oc_category`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `oc_category` (
  `category_id` int(11) NOT NULL AUTO_INCREMENT,
  `image` varchar(255) DEFAULT NULL,
  `icon` varchar(255) DEFAULT NULL,
  `background_image` varchar(255) DEFAULT NULL,
  `parent_id` int(11) NOT NULL DEFAULT 0,
  `top` tinyint(1) NOT NULL,
  `column` int(11) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `status` tinyint(1) NOT NULL,
  `date_added` datetime NOT NULL,
  `date_modified` datetime NOT NULL,
  PRIMARY KEY (`category_id`),
  KEY `parent_id` (`parent_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1975 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `oc_category`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `oc_category` WRITE;
/*!40000 ALTER TABLE `oc_category` DISABLE KEYS */;
INSERT INTO `oc_category` VALUES
(101,'catalog/demo/demo-seed/categories/electronics.jpg',NULL,'catalog/demo/demo-seed/products/electronics/apple-iphone-15-pro-2.jpg',0,1,1,1,1,'2026-03-03 08:51:04','2026-05-30 18:12:21'),
(103,'catalog/demo/demo-seed/categories/fashion.jpg',NULL,'catalog/demo/demo-seed/products/fashion/converse-chuck-taylor-all-star.jpg',0,1,1,3,1,'2026-03-03 08:51:05','2026-05-28 08:38:53'),
(104,'catalog/demo/demo-seed/categories/sports-outdoors.jpg',NULL,'catalog/demo/demo-seed/products/sports-outdoors/trx-suspension-trainer.jpg',0,1,1,4,1,'2026-03-03 08:51:05','2026-05-28 08:39:26'),
(105,'catalog/demo/demo-seed/categories/beauty-health.jpg',NULL,'catalog/demo/demo-seed/products/electronics/bose-quietcomfort-ultra.jpg',0,1,1,5,1,'2026-03-03 08:51:06','2026-05-30 16:39:49'),
(106,'catalog/demo/demo-seed/categories/toys-games.jpg',NULL,'catalog/demo/demo-seed/products/toys-games/mattel-uno-card-game.jpg',0,1,1,6,1,'2026-03-03 08:51:07','2026-05-28 08:39:54'),
(107,'catalog/demo/demo-seed/categories/books-media.jpg',NULL,'catalog/demo/demo-seed/products/books-media/the-hobbit-by-j.r.r.-tolkien.jpg',0,1,1,7,1,'2026-03-03 08:51:07','2026-05-28 08:40:14'),
(108,'catalog/demo/demo-seed/categories/home-kitchen-small-appliances.jpg','catalog/cart.png','catalog/demo/demo-seed/products/automotive/anker-roav-bluetooth-receiver-2.jpg',0,1,1,8,1,'2026-03-03 08:51:08','2026-06-07 08:57:51'),
(109,'catalog/demo/demo-seed/categories/pet-supplies.jpg',NULL,'catalog/demo/demo-seed/products/pet-supplies/whiskas-dry-cat-food.jpg',0,1,1,9,1,'2026-03-03 08:51:09','2026-05-28 08:40:50'),
(1101,'catalog/demo/demo-seed/categories/electronics-smartphones.jpg',NULL,NULL,101,0,1,1,1,'2026-03-03 08:51:04','2026-03-03 08:51:04'),
(1102,'catalog/demo/demo-seed/categories/electronics-laptops.jpg',NULL,NULL,101,0,1,2,1,'2026-03-03 08:51:04','2026-03-03 08:51:04'),
(1103,'catalog/demo/demo-seed/categories/electronics-audio.jpg',NULL,NULL,101,0,1,3,1,'2026-03-03 08:51:04','2026-03-03 08:51:04'),
(1301,'catalog/demo/demo-seed/categories/fashion-womens-clothing.jpg',NULL,NULL,103,0,1,1,1,'2026-03-03 08:51:05','2026-03-03 08:51:05'),
(1302,'catalog/demo/demo-seed/categories/fashion-mens-clothing.jpg',NULL,NULL,103,0,1,2,1,'2026-03-03 08:51:05','2026-03-03 08:51:05'),
(1303,'catalog/demo/demo-seed/categories/fashion-shoes-accessories.jpg',NULL,NULL,103,0,1,3,1,'2026-03-03 08:51:05','2026-03-03 08:51:05'),
(1401,'catalog/demo/demo-seed/categories/sports-outdoors-fitness.jpg',NULL,NULL,104,0,1,1,1,'2026-03-03 08:51:05','2026-03-03 08:51:05'),
(1402,'catalog/demo/demo-seed/categories/sports-outdoors-camping.jpg',NULL,NULL,104,0,1,2,1,'2026-03-03 08:51:06','2026-03-03 08:51:06'),
(1403,'catalog/demo/demo-seed/categories/sports-outdoors-cycling.jpg',NULL,NULL,104,0,1,3,1,'2026-03-03 08:51:06','2026-03-03 08:51:06'),
(1501,'catalog/demo/demo-seed/categories/beauty-health-skincare.jpg',NULL,NULL,105,0,1,1,1,'2026-03-03 08:51:06','2026-03-03 08:51:06'),
(1502,'catalog/demo/demo-seed/categories/beauty-health-haircare.jpg',NULL,NULL,105,0,1,2,1,'2026-03-03 08:51:06','2026-03-03 08:51:06'),
(1503,'catalog/demo/demo-seed/categories/beauty-health-wellness.jpg',NULL,NULL,105,0,1,3,1,'2026-03-03 08:51:06','2026-03-03 08:51:06'),
(1601,'catalog/demo/demo-seed/categories/toys-games-board-games.jpg',NULL,NULL,106,0,1,1,1,'2026-03-03 08:51:07','2026-03-03 08:51:07'),
(1602,'catalog/demo/demo-seed/categories/toys-games-construction-toys.jpg',NULL,NULL,106,0,1,2,1,'2026-03-03 08:51:07','2026-03-03 08:51:07'),
(1603,'catalog/demo/demo-seed/categories/toys-games-gaming-consoles.jpg',NULL,NULL,106,0,1,3,1,'2026-03-03 08:51:07','2026-03-03 08:51:07'),
(1701,'catalog/demo/demo-seed/categories/books-media-fiction.jpg',NULL,NULL,107,0,1,1,1,'2026-03-03 08:51:07','2026-03-03 08:51:07'),
(1702,'catalog/demo/demo-seed/categories/books-media-business.jpg',NULL,NULL,107,0,1,2,1,'2026-03-03 08:51:07','2026-03-03 08:51:07'),
(1703,'catalog/demo/demo-seed/categories/books-media-kids-books.jpg',NULL,NULL,107,0,1,3,1,'2026-03-03 08:51:07','2026-03-03 08:51:07'),
(1801,'catalog/demo/demo-seed/categories/automotive-car-electronics.jpg','catalog/cart.png','',108,0,1,1,1,'2026-03-03 08:51:08','2026-06-07 09:02:42'),
(1802,'catalog/demo/demo-seed/categories/automotive-car-care.jpg',NULL,NULL,108,0,1,2,1,'2026-03-03 08:51:08','2026-03-03 08:51:08'),
(1803,'catalog/demo/demo-seed/categories/automotive-interior-accessories.jpg',NULL,NULL,108,0,1,3,1,'2026-03-03 08:51:08','2026-03-03 08:51:08'),
(1901,'catalog/demo/demo-seed/categories/pet-supplies-dog-care.jpg',NULL,NULL,109,0,1,1,1,'2026-03-03 08:51:09','2026-03-03 08:51:09'),
(1902,'catalog/demo/demo-seed/categories/pet-supplies-cat-care.jpg',NULL,NULL,109,0,1,2,1,'2026-03-03 08:51:09','2026-03-03 08:51:09'),
(1903,'catalog/demo/demo-seed/categories/pet-supplies-pet-tech.jpg',NULL,NULL,109,0,1,3,1,'2026-03-03 08:51:09','2026-03-03 08:51:09'),
(1904,'',NULL,NULL,49,0,1,0,1,'2026-04-26 15:39:51','2026-04-26 15:39:51'),
(1905,'',NULL,NULL,49,0,1,0,1,'2026-04-26 15:39:51','2026-04-26 15:39:51'),
(1906,'',NULL,NULL,49,0,1,0,1,'2026-04-26 15:39:51','2026-04-26 15:39:51'),
(1907,'',NULL,NULL,1906,0,1,0,1,'2026-04-26 15:39:51','2026-04-26 15:39:51'),
(1908,'',NULL,NULL,1906,0,1,0,1,'2026-04-26 15:39:51','2026-04-26 15:39:51'),
(1909,'',NULL,NULL,1906,0,1,0,1,'2026-04-26 15:39:51','2026-04-26 15:39:51'),
(1910,'',NULL,NULL,1906,0,1,0,1,'2026-04-26 15:39:51','2026-04-26 15:39:51'),
(1911,'',NULL,NULL,1910,0,1,0,1,'2026-04-26 15:39:51','2026-04-26 15:39:51'),
(1912,'',NULL,NULL,1910,0,1,0,1,'2026-04-26 15:39:51','2026-04-26 15:39:51'),
(1913,'',NULL,NULL,1910,0,1,0,1,'2026-04-26 15:39:51','2026-04-26 15:39:51'),
(1914,'',NULL,NULL,1910,0,1,0,1,'2026-04-26 15:39:51','2026-04-26 15:39:51'),
(1915,'',NULL,NULL,1904,0,1,0,1,'2026-04-26 15:39:52','2026-04-26 15:39:52'),
(1916,'',NULL,NULL,1906,0,1,0,1,'2026-04-26 15:39:52','2026-04-26 15:39:52'),
(1917,'',NULL,NULL,1906,0,1,0,1,'2026-04-26 15:39:52','2026-04-26 15:39:52'),
(1918,'',NULL,NULL,1904,0,1,0,1,'2026-04-26 15:39:52','2026-04-26 15:39:52'),
(1919,'',NULL,NULL,1916,0,1,0,1,'2026-04-26 15:39:52','2026-04-26 15:39:52'),
(1920,'',NULL,NULL,1905,0,1,0,1,'2026-04-26 15:39:52','2026-04-26 15:39:52'),
(1921,'',NULL,NULL,1905,0,1,0,1,'2026-04-26 15:39:52','2026-04-26 15:39:52'),
(1922,'',NULL,NULL,1905,0,1,0,1,'2026-04-26 15:39:52','2026-04-26 15:39:52'),
(1923,'',NULL,NULL,1906,0,1,0,1,'2026-04-26 15:39:52','2026-04-26 15:39:52'),
(1924,'',NULL,NULL,1917,0,1,0,1,'2026-04-26 15:39:52','2026-04-26 15:39:52'),
(1925,'',NULL,NULL,1917,0,1,0,1,'2026-04-26 15:39:52','2026-04-26 15:39:52'),
(1926,'',NULL,NULL,1910,0,1,0,1,'2026-04-26 15:39:52','2026-04-26 15:39:52'),
(1927,'',NULL,NULL,1910,0,1,0,1,'2026-04-26 15:39:52','2026-04-26 15:39:52'),
(1928,'',NULL,NULL,1917,0,1,0,1,'2026-04-26 15:39:52','2026-04-26 15:39:52'),
(1929,'',NULL,NULL,1906,0,1,0,1,'2026-04-26 15:39:52','2026-04-26 15:39:52'),
(1930,'',NULL,NULL,1906,0,1,0,1,'2026-04-26 15:39:52','2026-04-26 15:39:52'),
(1931,'',NULL,NULL,1906,0,1,0,1,'2026-04-26 15:39:52','2026-04-26 15:39:52'),
(1932,'',NULL,NULL,1910,0,1,0,1,'2026-04-26 15:39:52','2026-04-26 15:39:52'),
(1933,'',NULL,NULL,1910,0,1,0,1,'2026-04-26 15:39:52','2026-04-26 15:39:52'),
(1934,'',NULL,NULL,1910,0,1,0,1,'2026-04-26 15:39:52','2026-04-26 15:39:52'),
(1935,'',NULL,NULL,1910,0,1,0,1,'2026-04-26 15:39:52','2026-04-26 15:39:52'),
(1936,'',NULL,NULL,1910,0,1,0,1,'2026-04-26 15:39:52','2026-04-26 15:39:52'),
(1937,'',NULL,NULL,1910,0,1,0,1,'2026-04-26 15:39:52','2026-04-26 15:39:52'),
(1938,'',NULL,NULL,1906,0,1,0,1,'2026-04-26 15:39:52','2026-04-26 15:39:52'),
(1939,'',NULL,NULL,1906,0,1,0,1,'2026-04-26 15:39:52','2026-04-26 15:39:52'),
(1940,'',NULL,NULL,1906,0,1,0,1,'2026-04-26 15:39:52','2026-04-26 15:39:52'),
(1941,'',NULL,NULL,1906,0,1,0,1,'2026-04-26 15:39:52','2026-04-26 15:39:52'),
(1942,'',NULL,NULL,1906,0,1,0,1,'2026-04-26 15:39:52','2026-04-26 15:39:52'),
(1943,'',NULL,NULL,1918,0,1,0,1,'2026-04-26 15:39:52','2026-04-26 15:39:52'),
(1944,'',NULL,NULL,1910,0,1,0,1,'2026-04-26 15:39:52','2026-04-26 15:39:52'),
(1945,'',NULL,NULL,1916,0,1,0,1,'2026-04-26 15:39:52','2026-04-26 15:39:52'),
(1946,'',NULL,NULL,1916,0,1,0,1,'2026-04-26 15:39:52','2026-04-26 15:39:52'),
(1947,'',NULL,NULL,1906,0,1,0,1,'2026-04-26 15:39:52','2026-04-26 15:39:52'),
(1948,'',NULL,NULL,1916,0,1,0,1,'2026-04-26 15:39:52','2026-04-26 15:39:52'),
(1949,'',NULL,NULL,1948,0,1,0,1,'2026-04-26 15:39:52','2026-04-26 15:39:52'),
(1950,'',NULL,NULL,1919,0,1,0,1,'2026-04-26 15:39:52','2026-04-26 15:39:52'),
(1951,'',NULL,NULL,1919,0,1,0,1,'2026-04-26 15:39:52','2026-04-26 15:39:52'),
(1952,'',NULL,NULL,1910,0,1,0,1,'2026-04-26 15:39:52','2026-04-26 15:39:52'),
(1953,'',NULL,NULL,1917,0,1,0,1,'2026-04-26 15:39:52','2026-04-26 15:39:52'),
(1954,'',NULL,NULL,1915,0,1,0,1,'2026-04-26 15:39:52','2026-04-26 15:39:52'),
(1955,'',NULL,NULL,1916,0,1,0,1,'2026-04-26 15:39:52','2026-04-26 15:39:52'),
(1956,'',NULL,NULL,1948,0,1,0,1,'2026-04-26 15:39:52','2026-04-26 15:39:52'),
(1957,'',NULL,NULL,1956,0,1,0,1,'2026-04-26 15:39:52','2026-04-26 15:39:52'),
(1971,'catalog/demo/demo-seed/categories/home-kitchen.jpg',NULL,'catalog/demo/demo-seed/products/home-kitchen/kitchenaid-artisan-mixer.jpg',0,1,1,2,1,'2026-06-03 13:44:48','2026-06-03 18:02:14'),
(1972,'catalog/demo/demo-seed/categories/home-kitchen-cookware.jpg',NULL,NULL,1971,0,1,1,1,'2026-06-03 13:44:48','2026-06-03 13:44:48'),
(1973,'catalog/demo/demo-seed/categories/home-kitchen-small-appliances.jpg',NULL,NULL,1971,0,1,2,1,'2026-06-03 13:44:48','2026-06-03 13:44:48'),
(1974,'catalog/demo/demo-seed/categories/home-kitchen-home-decor.jpg',NULL,NULL,1971,0,1,3,1,'2026-06-03 13:44:48','2026-06-03 13:44:48');
/*!40000 ALTER TABLE `oc_category` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `oc_category_description`
--

DROP TABLE IF EXISTS `oc_category_description`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `oc_category_description` (
  `category_id` int(11) NOT NULL,
  `language_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` mediumtext NOT NULL,
  `meta_title` varchar(255) NOT NULL,
  `meta_description` varchar(255) NOT NULL,
  `meta_keyword` varchar(255) NOT NULL,
  PRIMARY KEY (`category_id`,`language_id`),
  KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `oc_category_description`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `oc_category_description` WRITE;
/*!40000 ALTER TABLE `oc_category_description` DISABLE KEYS */;
INSERT INTO `oc_category_description` VALUES
(101,1,'Electronics','&lt;p&gt;Smart devices and premium gadgets for work, gaming, and everyday life.&lt;/p&gt;','Electronics','Smart devices and premium gadgets for work, gaming, and everyday life.','electronics'),
(101,2,'Електроніка','&lt;p&gt;Розумні пристрої та преміальні гаджети для роботи, ігор та повсякденного життя.&lt;/p&gt;','Електроніка','Розумні пристрої та преміальні гаджети для роботи, ігор та повсякденного життя.','електроніка'),
(101,3,'Электроника','&lt;p&gt;Умные устройства и гаджеты премиум-класса для работы, игр и повседневной жизни.&lt;/p&gt;','Электроника','Умные устройства и гаджеты премиум-класса для работы, игр и повседневной жизни.','электроника'),
(103,1,'Fashion','&lt;p&gt;Everyday wardrobe staples and premium pieces from trusted global brands.&lt;/p&gt;','Fashion','Everyday wardrobe staples and premium pieces from trusted global brands.','fashion'),
(103,2,'Мода','&lt;p&gt;Основні речі щоденного гардеробу та преміальні речі від перевірених світових брендів.&lt;/p&gt;','Мода','Основні речі щоденного гардеробу та преміальні речі від перевірених світових брендів.','мода'),
(103,3,'Мода','&lt;p&gt;Повседневные предметы гардероба и вещи премиум-класса от проверенных мировых брендов.&lt;/p&gt;','Мода','Повседневные предметы гардероба и вещи премиум-класса от проверенных мировых брендов.','мода'),
(104,1,'Sports &amp; Outdoors','&lt;p&gt;Gear and equipment for training, recovery, hiking, and outdoor adventures.&lt;/p&gt;','Sports &amp; Outdoors','Gear and equipment for training, recovery, hiking, and outdoor adventures.','sports-outdoors'),
(104,2,'Спорт','&lt;p&gt;Спорядження та обладнання для тренувань, відновлення, походів та пригод на природі.&lt;/p&gt;','Спорт та активний відпочинок','Спорядження та обладнання для тренувань, відновлення, походів та пригод на природі.','спорт на свіжому повітрі'),
(104,3,'Спорт','&lt;p&gt;Снаряжение и оборудование для тренировок, восстановления, походов и приключений на открытом воздухе.&lt;/p&gt;','Спорт и отдых на природе','Снаряжение и оборудование для тренировок, восстановления, походов и приключений на природе.','спорт на открытом воздухе'),
(105,1,'Beauty &amp; Health','&lt;p&gt;Top skincare, wellness, and personal care products for everyday routines.&lt;/p&gt;','Beauty &amp; Health','Top skincare, wellness, and personal care products for everyday routines.','beauty-health'),
(105,2,'Краса та здоров\'я','&lt;p&gt;Найкращі засоби догляду за шкірою, здоров\'я та особистої гігієни для щоденного використання.&lt;/p&gt;','Краса та здоров\'я','Найкращі засоби догляду за шкірою, здоров\'я та особистої гігієни для щоденного використання.','краса-здоров\'я'),
(105,3,'Красота и здоровье','&lt;p&gt;Лучшие средства по уходу за кожей, для хорошего самочувствия и личной гигиены для повседневного использования.&lt;/p&gt;','Красота и здоровье','Лучшие средства по уходу за кожей, для хорошего самочувствия и личной гигиены для повседневного использования.','красота-здоровье'),
(106,1,'Toys &amp; Games','&lt;p&gt;Fun and educational products for kids, families, and hobby enthusiasts.&lt;/p&gt;','Toys &amp; Games','Fun and educational products for kids, families, and hobby enthusiasts.','toys-games'),
(106,2,'Іграшки та ігри','&lt;p&gt;Цікаві та розвиваючі товари для дітей, сімей та любителів хобі.&lt;/p&gt;','Іграшки та ігри','Цікаві та розвиваючі товари для дітей, сімей та любителів хобі.','іграшки-ігри'),
(106,3,'Игры','&lt;p&gt;Развлекательные и познавательные товары для детей, семей и любителей различных хобби.&lt;/p&gt;','Игрушки и игры','Развлекательные и познавательные товары для детей, семей и любителей различных хобби.','игрушки-игры'),
(107,1,'Books &amp; Media','&lt;p&gt;Bestselling books and media formats for learning, entertainment, and growth.&lt;/p&gt;','Books &amp; Media','Bestselling books and media formats for learning, entertainment, and growth.','books-media'),
(107,2,'Книги та медіа','&lt;p&gt;Найпопулярніші книги та медіаформати для навчання, розваг та розвитку.&lt;/p&gt;','Книги та медіа','Найпопулярніші книги та медіаформати для навчання, розваг та розвитку.','книги-медіа'),
(107,3,'Книги и медиа','&lt;p&gt;Бестселлеры и медиаформаты для обучения, развлечения и личностного роста.&lt;/p&gt;','Книги и медиа','Бестселлеры и медиаформаты для обучения, развлечения и личностного роста.','книги-медиа'),
(108,1,'Automotive','&lt;p&gt;Car care essentials, electronics, and accessories for safer daily driving.&lt;/p&gt;','Automotive','Car care essentials, electronics, and accessories for safer daily driving.','automotive'),
(108,2,'Автомобільна промисловість','&lt;p&gt;Основні засоби догляду за автомобілем, електроніка та аксесуари для безпечнішого щоденного водіння.&lt;/p&gt;','Автомобільна промисловість','Основні засоби догляду за автомобілем, електроніка та аксесуари для безпечнішого щоденного водіння.','автомобільна'),
(108,3,'Автомобильная промышленность','&lt;p&gt;Товары первой необходимости для ухода за автомобилем, электроника и аксессуары для более безопасного вождения в повседневной жизни.&lt;/p&gt;','Автомобильная промышленность','Товары первой необходимости для ухода за автомобилем, электроника и аксессуары для более безопасного вождения в повседневной жизни.','автомобильный'),
(109,1,'Pet Supplies','&lt;p&gt;Food, toys, and care products for healthy and happy dogs and cats.&lt;/p&gt;','Pet Supplies','Food, toys, and care products for healthy and happy dogs and cats.','pet-supplies'),
(109,2,'Зоотовари','&lt;p&gt;Корм, іграшки та засоби догляду для здорових та щасливих собак і котів.&lt;/p&gt;','Зоотовари','Корм, іграшки та засоби догляду для здорових та щасливих собак і котів.','товари для домашніх тварин'),
(109,3,'Товары для животных','&lt;p&gt;Корм, игрушки и средства по уходу за здоровыми и счастливыми собаками и кошками.&lt;/p&gt;','Товары для животных','Корм, игрушки и средства по уходу за здоровыми и счастливыми собаками и кошками.','товары для животных'),
(1101,1,'Smartphones','Smartphones in the Electronics department.','Smartphones','Smartphones in the Electronics department.','smartphones'),
(1101,2,'Смартфони','Смартфони у відділі електроніки.','Смартфони','Смартфони у відділі електроніки.','смартфони'),
(1101,3,'Смартфоны','Смартфоны в отделе электроники.','Смартфоны','Смартфоны в отделе электроники.','смартфоны'),
(1102,1,'Laptops','Laptops in the Electronics department.','Laptops','Laptops in the Electronics department.','laptops'),
(1102,2,'Ноутбуки','Ноутбуки у відділі електроніки.','Ноутбуки','Ноутбуки у відділі електроніки.','ноутбуки'),
(1102,3,'Ноутбуки','Ноутбуки в отделе электроники.','Ноутбуки','Ноутбуки в отделе электроники.','ноутбуки'),
(1103,1,'Audio','Audio in the Electronics department.','Audio','Audio in the Electronics department.','audio'),
(1103,2,'Аудіо','Аудіо у відділі електроніки.','Аудіо','Аудіо у відділі електроніки.','аудіо'),
(1103,3,'Аудио','Аудиооборудование в отделе электроники.','Аудио','Аудиооборудование в отделе электроники.','аудио'),
(1301,1,'Women\'s Clothing','Women\'s Clothing in the Fashion department.','Women\'s Clothing','Women\'s Clothing in the Fashion department.','womens-clothing'),
(1301,2,'Жіночий одяг','Жіночий одяг у відділі моди.','Жіночий одяг','Жіночий одяг у відділі моди.','жіночий одяг'),
(1301,3,'Женская одежда','Женская одежда в отделе моды.','Женская одежда','Женская одежда в отделе моды.','женская одежда'),
(1302,1,'Men\'s Clothing','Men\'s Clothing in the Fashion department.','Men\'s Clothing','Men\'s Clothing in the Fashion department.','mens-clothing'),
(1302,2,'Чоловічий одяг','Чоловічий одяг у відділі моди.','Чоловічий одяг','Чоловічий одяг у відділі моди.','чоловічий одяг'),
(1302,3,'Мужская одежда','Мужская одежда в отделе моды.','Мужская одежда','Мужская одежда в отделе моды.','мужская одежда'),
(1303,1,'Shoes & Accessories','Shoes & Accessories in the Fashion department.','Shoes & Accessories','Shoes & Accessories in the Fashion department.','shoes-accessories'),
(1303,2,'Взуття та аксесуари','Взуття та аксесуари у відділі моди.','Взуття та аксесуари','Взуття та аксесуари у відділі моди.','взуття-аксесуари'),
(1303,3,'Обувь и аксессуары','Обувь и аксессуары в отделе моды.','Обувь и аксессуары','Обувь и аксессуары в отделе моды.','обувь-аксессуары'),
(1401,1,'Fitness','Fitness in the Sports & Outdoors department.','Fitness','Fitness in the Sports & Outdoors department.','fitness'),
(1401,2,'Фітнес','Фітнес у відділі «Спорт та активний відпочинок».','Фітнес','Фітнес у відділі «Спорт та активний відпочинок».','фітнес'),
(1401,3,'Фитнес','Фитнес в отделе «Спорт и отдых на природе».','Фитнес','Фитнес в отделе «Спорт и отдых на природе».','фитнес'),
(1402,1,'Camping','Camping in the Sports & Outdoors department.','Camping','Camping in the Sports & Outdoors department.','camping'),
(1402,2,'Кемпінг','Кемпінг у відділі «Спорт та відпочинок на природі».','Кемпінг','Кемпінг у відділі «Спорт та відпочинок на природі».','кемпінг'),
(1402,3,'Кемпинг','Кемпинг в отделе «Спорт и отдых на природе».','Кемпинг','Кемпинг в отделе «Спорт и отдых на природе».','кемпинг'),
(1403,1,'Cycling','Cycling in the Sports & Outdoors department.','Cycling','Cycling in the Sports & Outdoors department.','cycling'),
(1403,2,'Велоспорт','Велоспорт у відділі «Спорт та активний відпочинок».','Велоспорт','Велоспорт у відділі «Спорт та активний відпочинок».','їзда на велосипеді'),
(1403,3,'Велоспорт','Велоспорт в отделе «Спорт и отдых на природе».','Велоспорт','Велоспорт в отделе «Спорт и отдых на природе».','велоспорт'),
(1501,1,'Skincare','Skincare in the Beauty & Health department.','Skincare','Skincare in the Beauty & Health department.','skincare'),
(1501,2,'Догляд за шкірою','Догляд за шкірою у відділі краси та здоров\'я.','Догляд за шкірою','Догляд за шкірою у відділі краси та здоров\'я.','догляд за шкірою'),
(1501,3,'Уход за кожей','Уход за кожей в отделе «Красота и здоровье».','Уход за кожей','Уход за кожей в отделе «Красота и здоровье».','уход за кожей'),
(1502,1,'Haircare','Haircare in the Beauty & Health department.','Haircare','Haircare in the Beauty & Health department.','haircare'),
(1502,2,'Догляд за волоссям','Догляд за волоссям у відділі краси та здоров\'я.','Догляд за волоссям','Догляд за волоссям у відділі краси та здоров\'я.','догляд за волоссям'),
(1502,3,'Уход за волосами','Уход за волосами в отделе «Красота и здоровье».','Уход за волосами','Уход за волосами в отделе «Красота и здоровье».','уход за волосами'),
(1503,1,'Wellness','Wellness in the Beauty & Health department.','Wellness','Wellness in the Beauty & Health department.','wellness'),
(1503,2,'Велнес','Велнес у відділі краси та здоров\'я.','Велнес','Велнес у відділі краси та здоров\'я.','оздоровлення'),
(1503,3,'Здоровье','Оздоровление в отделе красоты и здоровья.','Здоровье','Оздоровление в отделе красоты и здоровья.','благополучие'),
(1601,1,'Board Games','Board Games in the Toys & Games department.','Board Games','Board Games in the Toys & Games department.','board-games'),
(1601,2,'Настільні ігри','Настільні ігри у відділі іграшок та ігор.','Настільні ігри','Настільні ігри у відділі іграшок та ігор.','настільні ігри'),
(1601,3,'Настольные игры','Настольные игры в отделе игрушек и игр.','Настольные игры','Настольные игры в отделе игрушек и игр.','настольные игры'),
(1602,1,'Construction Toys','Construction Toys in the Toys & Games department.','Construction Toys','Construction Toys in the Toys & Games department.','construction-toys'),
(1602,2,'Іграшки-будівельники','Конструювальні іграшки у відділі іграшок та ігор.','Іграшки-будівельники','Конструювальні іграшки у відділі іграшок та ігор.','конструктори-іграшки'),
(1602,3,'Конструкторы','Конструкторы в отделе игрушек и игр.','Конструкторы','Конструкторы в отделе игрушек и игр.','конструкторы'),
(1603,1,'Gaming Consoles','Gaming Consoles in the Toys & Games department.','Gaming Consoles','Gaming Consoles in the Toys & Games department.','gaming-consoles'),
(1603,2,'Ігрові консолі','Ігрові консолі у відділі іграшок та ігор.','Ігрові консолі','Ігрові консолі у відділі іграшок та ігор.','ігрові консолі'),
(1603,3,'Игровые консоли','Игровые приставки в отделе игрушек и игр.','Игровые консоли','Игровые приставки в отделе игрушек и игр.','игровые консоли'),
(1701,1,'Fiction','Fiction in the Books & Media department.','Fiction','Fiction in the Books & Media department.','fiction'),
(1701,2,'Художня література','Художня література у відділі книг та медіа.','Художня література','Художня література у відділі «Книги та медіа».','художня література'),
(1701,3,'Вымысел','Художественная литература в отделе книг и медиа.','Вымысел','Художественная литература в отделе книг и медиа.','вымысел'),
(1702,1,'Business','Business in the Books & Media department.','Business','Business in the Books & Media department.','business'),
(1702,2,'Бізнес','Бізнес у відділі книг та медіа.','Бізнес','Бізнес у відділі книг та медіа.','бізнес'),
(1702,3,'Бизнес','Бизнес в отделе книг и медиа.','Бизнес','Бизнес в отделе книг и медиа.','бизнес'),
(1703,1,'Kids Books','Kids Books in the Books & Media department.','Kids Books','Kids Books in the Books & Media department.','kids-books'),
(1703,2,'Дитячі книги','Дитячі книги у відділі «Книги та медіа».','Дитячі книги','Дитячі книги у відділі «Книги та медіа».','дитячі книги'),
(1703,3,'Детские книги','Детские книги в отделе «Книги и медиа».','Детские книги','Детские книги в отделе «Книги и медиа».','детские книги'),
(1801,1,'Car Electronics','&lt;p&gt;Car Electronics in the Automotive department.&lt;/p&gt;','Car Electronics','Car Electronics in the Automotive department.','car-electronics'),
(1801,2,'Автомобільна електроніка','&lt;p&gt;Автомобільна електроніка в автомобільному відділі.&lt;/p&gt;','Автомобільна електроніка','Автомобільна електроніка в автомобільному відділі.','автомобільна електроніка'),
(1801,3,'Автомобильная электроника','&lt;p&gt;Автомобильная электроника в автомобильном отделе.&lt;/p&gt;','Автомобильная электроника','Автомобильная электроника в автомобильном отделе.','автомобильная электроника'),
(1802,1,'Car Care','Car Care in the Automotive department.','Car Care','Car Care in the Automotive department.','car-care'),
(1802,2,'Догляд за автомобілем','Догляд за автомобілем у відділі автомобілів.','Догляд за автомобілем','Догляд за автомобілем у відділі автомобілів.','догляд за автомобілем'),
(1802,3,'Уход за автомобилем','Уход за автомобилем в автомобильном отделе.','Уход за автомобилем','Уход за автомобилем в автомобильном отделе.','уход за автомобилем'),
(1803,1,'Interior Accessories','Interior Accessories in the Automotive department.','Interior Accessories','Interior Accessories in the Automotive department.','interior-accessories'),
(1803,2,'Аксесуари для інтер\'єру','Аксесуари для салону в автомобільному відділі.','Аксесуари для інтер\'єру','Аксесуари для салону в автомобільному відділі.','аксесуари для інтер\'єру'),
(1803,3,'Аксессуары для интерьера','Аксессуары для салона автомобиля.','Аксессуары для интерьера','Аксессуары для салона автомобиля.','аксессуары для интерьера'),
(1901,1,'Dog Care','Dog Care in the Pet Supplies department.','Dog Care','Dog Care in the Pet Supplies department.','dog-care'),
(1901,2,'Догляд за собаками','Догляд за собаками у відділі зоотоварів.','Догляд за собаками','Догляд за собаками у відділі зоотоварів.','догляд за собаками'),
(1901,3,'Уход за собаками','Уход за собаками в отделе товаров для животных.','Уход за собаками','Уход за собаками в отделе товаров для животных.','уход за собаками'),
(1902,1,'Cat Care','Cat Care in the Pet Supplies department.','Cat Care','Cat Care in the Pet Supplies department.','cat-care'),
(1902,2,'Догляд за котами','Догляд за котами у відділі зоотоварів.','Догляд за котами','Догляд за котами у відділі зоотоварів.','догляд за котами'),
(1902,3,'Уход за кошками','Уход за кошками в отделе товаров для животных.','Уход за кошками','Уход за кошками в отделе товаров для животных.','уход за кошками'),
(1903,1,'Pet Tech','Pet Tech in the Pet Supplies department.','Pet Tech','Pet Tech in the Pet Supplies department.','pet-tech'),
(1903,2,'Зоотехніка','Зоотехнік у відділі зоотоварів.','Зоотехніка','Зоотехнік у відділі зоотоварів.','зоотехніка'),
(1903,3,'Технологии для домашних животных','Специалист по товарам для животных в отделе товаров для животных.','Технологии для домашних животных','Специалист по товарам для животных в отделе товаров для животных.','зоотех'),
(1904,1,'Инструмент','','Инструмент','',''),
(1905,1,'Оборудование для перекачки топлива','','Оборудование для перекачки топлива','',''),
(1906,1,'Автоинструмент','','Автоинструмент','',''),
(1907,1,'Инструмент для шиномонтажа','','Инструмент для шиномонтажа','',''),
(1908,1,'Инструмент для тестирования технического состояния','','Инструмент для тестирования технического состояния','',''),
(1909,1,'Инструмент для смазочных материалов','','Инструмент для смазочных материалов','',''),
(1910,1,'Съемники и обжимки','','Съемники и обжимки','',''),
(1911,1,'Съемники и ключи для замены масла','','Съемники и ключи для замены масла','',''),
(1912,1,'Съемники подшипников и шкивов','','Съемники подшипников и шкивов','',''),
(1913,1,'Съемники изоляции и обжимные клещи','','Съемники изоляции и обжимные клещи','',''),
(1914,1,'Обжимки для поршневых колец','','Обжимки для поршневых колец','',''),
(1915,1,'Крепежный инструмент','','Крепежный инструмент','',''),
(1916,1,'Ручной инструмент (ключи, наборы, головки)','','Ручной инструмент (ключи, наборы, головки)','',''),
(1917,1,'Вспомогательные инструменты и приспособления','','Вспомогательные инструменты и приспособления','',''),
(1918,1,'Техника для дома и сада','','Техника для дома и сада','',''),
(1919,1,'Отвертки и отверточные насадки','','Отвертки и отверточные насадки','',''),
(1920,1,'Мини АЗС с емкостью для ГСМ','','Мини АЗС с емкостью для ГСМ','',''),
(1921,1,'Ручные насосы','','Ручные насосы','',''),
(1922,1,'Счетчики топлива','','Счетчики топлива','',''),
(1923,1,'Специнструмент для СТО','','Специнструмент для СТО','',''),
(1924,1,'Магнитные захваты, держатели, зеркала','','Магнитные захваты, держатели, зеркала','',''),
(1925,1,'Наборы для ремонта и восстановления резьб','','Наборы для ремонта и восстановления резьб','',''),
(1926,1,'Съемники пружин и амортизаторов','','Съемники пружин и амортизаторов','',''),
(1927,1,'Съемники шаровых опор сайлентблоков и рулевых тяг','','Съемники шаровых опор сайлентблоков и рулевых тяг','',''),
(1928,1,'Шпильковерты','','Шпильковерты','',''),
(1929,1,'Фиксаторы валов ДВС','','Фиксаторы валов ДВС','',''),
(1930,1,'Инструмент для кузовных и рихтовочных работ','','Инструмент для кузовных и рихтовочных работ','',''),
(1931,1,'Диагностика и ремонт тормозной системы','','Диагностика и ремонт тормозной системы','',''),
(1932,1,'Инструмент для трубопроводов','','Инструмент для трубопроводов','',''),
(1933,1,'Инструмент для разборки салона','','Инструмент для разборки салона','',''),
(1934,1,'Съемники ШРУСа','','Съемники ШРУСа','',''),
(1935,1,'Инструмент для клапанной группы','','Инструмент для клапанной группы','',''),
(1936,1,'Клещи и зажимы для хомутов','','Клещи и зажимы для хомутов','',''),
(1937,1,'Съемники и приспособления для свечей зажигания и накаливания','','Съемники и приспособления для свечей зажигания и накаливания','',''),
(1938,1,'Инструмент для обслуживания дизельной топливной аппаратуры','','Инструмент для обслуживания дизельной топливной аппаратуры','',''),
(1939,1,'Обслуживание автокондиционеров','','Обслуживание автокондиционеров','',''),
(1940,1,'Инструмент моторной группы','','Инструмент моторной группы','',''),
(1941,1,'Инструмент для трансмиссии','','Инструмент для трансмиссии','',''),
(1942,1,'Инструмент для работы с электрооборудованием','','Инструмент для работы с электрооборудованием','',''),
(1943,1,'Портативные зарядные станции','','Портативные зарядные станции','',''),
(1944,1,'Инструмент для демонтажа сальников и уплотнений','','Инструмент для демонтажа сальников и уплотнений','',''),
(1945,1,'Наборы инструментов для авто','','Наборы инструментов для авто','',''),
(1946,1,'Ключи для откручивания колес авто','','Ключи для откручивания колес авто','',''),
(1947,1,'Гайковерты пневматические','','Гайковерты пневматические','',''),
(1948,1,'Торцевые головки, шестигранники и звезды','','Торцевые головки, шестигранники и звезды','',''),
(1949,1,'Наборы торцевых головок','','Наборы торцевых головок','',''),
(1950,1,'Наборы отверток','','Наборы отверток','',''),
(1951,1,'Отвертки ударные','','Отвертки ударные','',''),
(1952,1,'Щипцы для стопорных колец','','Щипцы для стопорных колец','',''),
(1953,1,'Инструмент для снятия и установки стекол','','Инструмент для снятия и установки стекол','',''),
(1954,1,'Заклепочники механические','','Заклепочники механические','',''),
(1955,1,'Ключи динамометрические','','Ключи динамометрические','',''),
(1956,1,'Шестигранники и звезды','','Шестигранники и звезды','',''),
(1957,1,'Биты, наборы бит','','Биты, наборы бит','',''),
(1971,1,'Home &amp; Kitchen','&lt;p&gt;Practical and stylish essentials to upgrade every room in your home.&lt;/p&gt;','Home &amp; Kitchen','Practical and stylish essentials to upgrade every room in your home.','home-kitchen'),
(1971,2,'Дім і кухня','&lt;p&gt;Практичні та стильні речі, які допоможуть оновити кожну кімнату у вашому домі.&lt;/p&gt;','Дім і кухня','Практичні та стильні речі, які допоможуть оновити кожну кімнату у вашому домі.','домашня кухня'),
(1971,3,'Дом и кухня','&lt;p&gt;Практичные и стильные вещи, которые помогут обновить каждую комнату в вашем доме.&lt;/p&gt;','Дом и кухня','Практичные и стильные вещи, которые помогут обновить каждую комнату в вашем доме.','домашняя кухня'),
(1972,1,'Cookware','Cookware in the Home & Kitchen department.','Cookware','Cookware in the Home & Kitchen department.','cookware'),
(1972,2,'Посуд','Кухонне начиння у відділі \"Дім і кухня\".','Посуд','Кухонне начиння у відділі \"Дім і кухня\".','посуд'),
(1972,3,'Посуда','Посуда в отделе «Дом и кухня».','Посуда','Посуда в отделе «Дом и кухня».','кухонная посуда'),
(1973,1,'Small Appliances','Small Appliances in the Home & Kitchen department.','Small Appliances','Small Appliances in the Home & Kitchen department.','small-appliances'),
(1973,2,'Дрібна побутова техніка','Дрібна побутова техніка у відділі \"Дім і кухня\".','Дрібна побутова техніка','Дрібна побутова техніка у відділі \"Дім і кухня\".','дрібна побутова техніка'),
(1973,3,'Мелкая бытовая техника','Мелкая бытовая техника в отделе «Дом и кухня».','Мелкая бытовая техника','Мелкая бытовая техника в отделе «Дом и кухня».','мелкая бытовая техника'),
(1974,1,'Home Decor','Home Decor in the Home & Kitchen department.','Home Decor','Home Decor in the Home & Kitchen department.','home-decor'),
(1974,2,'Домашній декор','Домашній декор у відділі \"Дім і кухня\".','Домашній декор','Домашній декор у відділі \"Дім і кухня\".','декор дому'),
(1974,3,'Декор для дома','Товары для дома и кухни в отделе «Дом и кухня».','Декор для дома','Товары для дома и кухни в отделе «Дом и кухня».','домашний декор');
/*!40000 ALTER TABLE `oc_category_description` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `oc_category_path`
--

DROP TABLE IF EXISTS `oc_category_path`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `oc_category_path` (
  `category_id` int(11) NOT NULL,
  `path_id` int(11) NOT NULL,
  `level` int(11) NOT NULL,
  PRIMARY KEY (`category_id`,`path_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `oc_category_path`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `oc_category_path` WRITE;
/*!40000 ALTER TABLE `oc_category_path` DISABLE KEYS */;
INSERT INTO `oc_category_path` VALUES
(101,101,0),
(103,103,0),
(104,104,0),
(105,105,0),
(106,106,0),
(107,107,0),
(108,108,0),
(109,109,0),
(1101,101,0),
(1101,1101,1),
(1102,101,0),
(1102,1102,1),
(1103,101,0),
(1103,1103,1),
(1301,103,0),
(1301,1301,1),
(1302,103,0),
(1302,1302,1),
(1303,103,0),
(1303,1303,1),
(1401,104,0),
(1401,1401,1),
(1402,104,0),
(1402,1402,1),
(1403,104,0),
(1403,1403,1),
(1501,105,0),
(1501,1501,1),
(1502,105,0),
(1502,1502,1),
(1503,105,0),
(1503,1503,1),
(1601,106,0),
(1601,1601,1),
(1602,106,0),
(1602,1602,1),
(1603,106,0),
(1603,1603,1),
(1701,107,0),
(1701,1701,1),
(1702,107,0),
(1702,1702,1),
(1703,107,0),
(1703,1703,1),
(1801,108,0),
(1801,1801,1),
(1802,108,0),
(1802,1802,1),
(1803,108,0),
(1803,1803,1),
(1901,109,0),
(1901,1901,1),
(1902,109,0),
(1902,1902,1),
(1903,109,0),
(1903,1903,1),
(1904,1904,0),
(1905,1905,0),
(1906,1906,0),
(1907,1906,0),
(1907,1907,1),
(1908,1906,0),
(1908,1908,1),
(1909,1906,0),
(1909,1909,1),
(1910,1906,0),
(1910,1910,1),
(1911,1906,0),
(1911,1910,1),
(1911,1911,2),
(1912,1906,0),
(1912,1910,1),
(1912,1912,2),
(1913,1906,0),
(1913,1910,1),
(1913,1913,2),
(1914,1906,0),
(1914,1910,1),
(1914,1914,2),
(1915,1904,0),
(1915,1915,1),
(1916,1906,0),
(1916,1916,1),
(1917,1906,0),
(1917,1917,1),
(1918,1904,0),
(1918,1918,1),
(1919,1906,0),
(1919,1916,1),
(1919,1919,2),
(1920,1905,0),
(1920,1920,1),
(1921,1905,0),
(1921,1921,1),
(1922,1905,0),
(1922,1922,1),
(1923,1906,0),
(1923,1923,1),
(1924,1906,0),
(1924,1917,1),
(1924,1924,2),
(1925,1906,0),
(1925,1917,1),
(1925,1925,2),
(1926,1906,0),
(1926,1910,1),
(1926,1926,2),
(1927,1906,0),
(1927,1910,1),
(1927,1927,2),
(1928,1906,0),
(1928,1917,1),
(1928,1928,2),
(1929,1906,0),
(1929,1929,1),
(1930,1906,0),
(1930,1930,1),
(1931,1906,0),
(1931,1931,1),
(1932,1906,0),
(1932,1910,1),
(1932,1932,2),
(1933,1906,0),
(1933,1910,1),
(1933,1933,2),
(1934,1906,0),
(1934,1910,1),
(1934,1934,2),
(1935,1906,0),
(1935,1910,1),
(1935,1935,2),
(1936,1906,0),
(1936,1910,1),
(1936,1936,2),
(1937,1906,0),
(1937,1910,1),
(1937,1937,2),
(1938,1906,0),
(1938,1938,1),
(1939,1906,0),
(1939,1939,1),
(1940,1906,0),
(1940,1940,1),
(1941,1906,0),
(1941,1941,1),
(1942,1906,0),
(1942,1942,1),
(1943,1904,0),
(1943,1918,1),
(1943,1943,2),
(1944,1906,0),
(1944,1910,1),
(1944,1944,2),
(1945,1906,0),
(1945,1916,1),
(1945,1945,2),
(1946,1906,0),
(1946,1916,1),
(1946,1946,2),
(1947,1906,0),
(1947,1947,1),
(1948,1906,0),
(1948,1916,1),
(1948,1948,2),
(1949,1906,0),
(1949,1916,1),
(1949,1948,2),
(1949,1949,3),
(1950,1906,0),
(1950,1916,1),
(1950,1919,2),
(1950,1950,3),
(1951,1906,0),
(1951,1916,1),
(1951,1919,2),
(1951,1951,3),
(1952,1906,0),
(1952,1910,1),
(1952,1952,2),
(1953,1906,0),
(1953,1917,1),
(1953,1953,2),
(1954,1904,0),
(1954,1915,1),
(1954,1954,2),
(1955,1906,0),
(1955,1916,1),
(1955,1955,2),
(1956,1906,0),
(1956,1916,1),
(1956,1948,2),
(1956,1956,3),
(1957,1906,0),
(1957,1916,1),
(1957,1948,2),
(1957,1956,3),
(1957,1957,4),
(1971,1971,0),
(1972,1971,0),
(1972,1972,1),
(1973,1971,0),
(1973,1973,1),
(1974,1971,0),
(1974,1974,1);
/*!40000 ALTER TABLE `oc_category_path` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `oc_category_to_layout`
--

DROP TABLE IF EXISTS `oc_category_to_layout`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `oc_category_to_layout` (
  `category_id` int(11) NOT NULL,
  `store_id` int(11) NOT NULL,
  `layout_id` int(11) NOT NULL,
  PRIMARY KEY (`category_id`,`store_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `oc_category_to_layout`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `oc_category_to_layout` WRITE;
/*!40000 ALTER TABLE `oc_category_to_layout` DISABLE KEYS */;
INSERT INTO `oc_category_to_layout` VALUES
(101,0,0),
(103,0,0),
(104,0,0),
(105,0,0),
(106,0,0),
(107,0,0),
(108,0,0),
(109,0,0),
(1801,0,0),
(1971,0,0);
/*!40000 ALTER TABLE `oc_category_to_layout` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `oc_category_to_store`
--

DROP TABLE IF EXISTS `oc_category_to_store`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `oc_category_to_store` (
  `category_id` int(11) NOT NULL,
  `store_id` int(11) NOT NULL,
  PRIMARY KEY (`category_id`,`store_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `oc_category_to_store`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `oc_category_to_store` WRITE;
/*!40000 ALTER TABLE `oc_category_to_store` DISABLE KEYS */;
INSERT INTO `oc_category_to_store` VALUES
(101,0),
(103,0),
(104,0),
(105,0),
(106,0),
(107,0),
(108,0),
(109,0),
(1101,0),
(1102,0),
(1103,0),
(1301,0),
(1302,0),
(1303,0),
(1401,0),
(1402,0),
(1403,0),
(1501,0),
(1502,0),
(1503,0),
(1601,0),
(1602,0),
(1603,0),
(1701,0),
(1702,0),
(1703,0),
(1801,0),
(1802,0),
(1803,0),
(1901,0),
(1902,0),
(1903,0),
(1904,0),
(1905,0),
(1906,0),
(1907,0),
(1908,0),
(1909,0),
(1910,0),
(1911,0),
(1912,0),
(1913,0),
(1914,0),
(1915,0),
(1916,0),
(1917,0),
(1918,0),
(1919,0),
(1920,0),
(1921,0),
(1922,0),
(1923,0),
(1924,0),
(1925,0),
(1926,0),
(1927,0),
(1928,0),
(1929,0),
(1930,0),
(1931,0),
(1932,0),
(1933,0),
(1934,0),
(1935,0),
(1936,0),
(1937,0),
(1938,0),
(1939,0),
(1940,0),
(1941,0),
(1942,0),
(1943,0),
(1944,0),
(1945,0),
(1946,0),
(1947,0),
(1948,0),
(1949,0),
(1950,0),
(1951,0),
(1952,0),
(1953,0),
(1954,0),
(1955,0),
(1956,0),
(1957,0),
(1971,0),
(1972,0),
(1973,0),
(1974,0);
/*!40000 ALTER TABLE `oc_category_to_store` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `oc_country`
--

DROP TABLE IF EXISTS `oc_country`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `oc_country` (
  `country_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(128) NOT NULL,
  `iso_code_2` varchar(2) NOT NULL,
  `iso_code_3` varchar(3) NOT NULL,
  `address_format` mediumtext NOT NULL,
  `phone_format` varchar(64) NOT NULL DEFAULT '',
  `postcode_required` tinyint(1) NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`country_id`)
) ENGINE=InnoDB AUTO_INCREMENT=258 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `oc_country`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `oc_country` WRITE;
/*!40000 ALTER TABLE `oc_country` DISABLE KEYS */;
INSERT INTO `oc_country` VALUES
(1,'Afghanistan','AF','AFG','','',0,1),
(2,'Albania','AL','ALB','','',0,1),
(3,'Algeria','DZ','DZA','','',0,1),
(4,'American Samoa','AS','ASM','','',0,1),
(5,'Andorra','AD','AND','','',0,1),
(6,'Angola','AO','AGO','','',0,1),
(7,'Anguilla','AI','AIA','','',0,1),
(8,'Antarctica','AQ','ATA','','',0,1),
(9,'Antigua and Barbuda','AG','ATG','','',0,1),
(10,'Argentina','AR','ARG','','',0,1),
(11,'Armenia','AM','ARM','','',0,1),
(12,'Aruba','AW','ABW','','',0,1),
(13,'Australia','AU','AUS','','',0,1),
(14,'Austria','AT','AUT','','',0,1),
(15,'Azerbaijan','AZ','AZE','','',0,1),
(16,'Bahamas','BS','BHS','','',0,1),
(17,'Bahrain','BH','BHR','','',0,1),
(18,'Bangladesh','BD','BGD','','',0,1),
(19,'Barbados','BB','BRB','','',0,1),
(20,'Belarus','BY','BLR','','',0,1),
(21,'Belgium','BE','BEL','{firstname} {lastname}\r\n{company}\r\n{address_1}\r\n{address_2}\r\n{postcode} {city}\r\n{country}','',0,1),
(22,'Belize','BZ','BLZ','','',0,1),
(23,'Benin','BJ','BEN','','',0,1),
(24,'Bermuda','BM','BMU','','',0,1),
(25,'Bhutan','BT','BTN','','',0,1),
(26,'Bolivia','BO','BOL','','',0,1),
(27,'Bosnia and Herzegovina','BA','BIH','','',0,1),
(28,'Botswana','BW','BWA','','',0,1),
(29,'Bouvet Island','BV','BVT','','',0,1),
(30,'Brazil','BR','BRA','','',0,1),
(31,'British Indian Ocean Territory','IO','IOT','','',0,1),
(32,'Brunei Darussalam','BN','BRN','','',0,1),
(33,'Bulgaria','BG','BGR','','',0,1),
(34,'Burkina Faso','BF','BFA','','',0,1),
(35,'Burundi','BI','BDI','','',0,1),
(36,'Cambodia','KH','KHM','','',0,1),
(37,'Cameroon','CM','CMR','','',0,1),
(38,'Canada','CA','CAN','','',0,1),
(39,'Cape Verde','CV','CPV','','',0,1),
(40,'Cayman Islands','KY','CYM','','',0,1),
(41,'Central African Republic','CF','CAF','','',0,1),
(42,'Chad','TD','TCD','','',0,1),
(43,'Chile','CL','CHL','','',0,1),
(44,'China','CN','CHN','','',0,1),
(45,'Christmas Island','CX','CXR','','',0,1),
(46,'Cocos (Keeling) Islands','CC','CCK','','',0,1),
(47,'Colombia','CO','COL','','',0,1),
(48,'Comoros','KM','COM','','',0,1),
(49,'Congo','CG','COG','','',0,1),
(50,'Cook Islands','CK','COK','','',0,1),
(51,'Costa Rica','CR','CRI','','',0,1),
(52,'Cote D\'Ivoire','CI','CIV','','',0,1),
(53,'Croatia','HR','HRV','','',0,1),
(54,'Cuba','CU','CUB','','',0,1),
(55,'Cyprus','CY','CYP','','',0,1),
(56,'Czech Republic','CZ','CZE','','',0,1),
(57,'Denmark','DK','DNK','','',0,1),
(58,'Djibouti','DJ','DJI','','',0,1),
(59,'Dominica','DM','DMA','','',0,1),
(60,'Dominican Republic','DO','DOM','','',0,1),
(61,'East Timor','TL','TLS','','',0,1),
(62,'Ecuador','EC','ECU','','',0,1),
(63,'Egypt','EG','EGY','','',0,1),
(64,'El Salvador','SV','SLV','','',0,1),
(65,'Equatorial Guinea','GQ','GNQ','','',0,1),
(66,'Eritrea','ER','ERI','','',0,1),
(67,'Estonia','EE','EST','','',0,1),
(68,'Ethiopia','ET','ETH','','',0,1),
(69,'Falkland Islands (Malvinas)','FK','FLK','','',0,1),
(70,'Faroe Islands','FO','FRO','','',0,1),
(71,'Fiji','FJ','FJI','','',0,1),
(72,'Finland','FI','FIN','','',0,1),
(74,'France, Metropolitan','FR','FRA','{firstname} {lastname}\r\n{company}\r\n{address_1}\r\n{address_2}\r\n{postcode} {city}\r\n{country}','',1,1),
(75,'French Guiana','GF','GUF','','',0,1),
(76,'French Polynesia','PF','PYF','','',0,1),
(77,'French Southern Territories','TF','ATF','','',0,1),
(78,'Gabon','GA','GAB','','',0,1),
(79,'Gambia','GM','GMB','','',0,1),
(80,'Georgia','GE','GEO','','',0,1),
(81,'Germany','DE','DEU','{company}\r\n{firstname} {lastname}\r\n{address_1}\r\n{address_2}\r\n{postcode} {city}\r\n{country}','',1,1),
(82,'Ghana','GH','GHA','','',0,1),
(83,'Gibraltar','GI','GIB','','',0,1),
(84,'Greece','GR','GRC','','',0,1),
(85,'Greenland','GL','GRL','','',0,1),
(86,'Grenada','GD','GRD','','',0,1),
(87,'Guadeloupe','GP','GLP','','',0,1),
(88,'Guam','GU','GUM','','',0,1),
(89,'Guatemala','GT','GTM','','',0,1),
(90,'Guinea','GN','GIN','','',0,1),
(91,'Guinea-Bissau','GW','GNB','','',0,1),
(92,'Guyana','GY','GUY','','',0,1),
(93,'Haiti','HT','HTI','','',0,1),
(94,'Heard and Mc Donald Islands','HM','HMD','','',0,1),
(95,'Honduras','HN','HND','','',0,1),
(96,'Hong Kong','HK','HKG','','',0,1),
(97,'Hungary','HU','HUN','','',0,1),
(98,'Iceland','IS','ISL','','',0,1),
(99,'India','IN','IND','','',0,1),
(100,'Indonesia','ID','IDN','','',0,1),
(101,'Iran (Islamic Republic of)','IR','IRN','','',0,1),
(102,'Iraq','IQ','IRQ','','',0,1),
(103,'Ireland','IE','IRL','','',0,1),
(104,'Israel','IL','ISR','','',0,1),
(105,'Italy','IT','ITA','','',0,1),
(106,'Jamaica','JM','JAM','','',0,1),
(107,'Japan','JP','JPN','','',0,1),
(108,'Jordan','JO','JOR','','',0,1),
(109,'Kazakhstan','KZ','KAZ','','',0,1),
(110,'Kenya','KE','KEN','','',0,1),
(111,'Kiribati','KI','KIR','','',0,1),
(112,'North Korea','KP','PRK','','',0,1),
(113,'South Korea','KR','KOR','','',0,1),
(114,'Kuwait','KW','KWT','','',0,1),
(115,'Kyrgyzstan','KG','KGZ','','',0,1),
(116,'Lao People\'s Democratic Republic','LA','LAO','','',0,1),
(117,'Latvia','LV','LVA','','',0,1),
(118,'Lebanon','LB','LBN','','',0,1),
(119,'Lesotho','LS','LSO','','',0,1),
(120,'Liberia','LR','LBR','','',0,1),
(121,'Libyan Arab Jamahiriya','LY','LBY','','',0,1),
(122,'Liechtenstein','LI','LIE','','',0,1),
(123,'Lithuania','LT','LTU','','',0,1),
(124,'Luxembourg','LU','LUX','','',0,1),
(125,'Macau','MO','MAC','','',0,1),
(126,'FYROM','MK','MKD','','',0,1),
(127,'Madagascar','MG','MDG','','',0,1),
(128,'Malawi','MW','MWI','','',0,1),
(129,'Malaysia','MY','MYS','','',0,1),
(130,'Maldives','MV','MDV','','',0,1),
(131,'Mali','ML','MLI','','',0,1),
(132,'Malta','MT','MLT','','',0,1),
(133,'Marshall Islands','MH','MHL','','',0,1),
(134,'Martinique','MQ','MTQ','','',0,1),
(135,'Mauritania','MR','MRT','','',0,1),
(136,'Mauritius','MU','MUS','','',0,1),
(137,'Mayotte','YT','MYT','','',0,1),
(138,'Mexico','MX','MEX','','',0,1),
(139,'Micronesia, Federated States of','FM','FSM','','',0,1),
(140,'Moldova, Republic of','MD','MDA','','',0,1),
(141,'Monaco','MC','MCO','','',0,1),
(142,'Mongolia','MN','MNG','','',0,1),
(143,'Montserrat','MS','MSR','','',0,1),
(144,'Morocco','MA','MAR','','',0,1),
(145,'Mozambique','MZ','MOZ','','',0,1),
(146,'Myanmar','MM','MMR','','',0,1),
(147,'Namibia','NA','NAM','','',0,1),
(148,'Nauru','NR','NRU','','',0,1),
(149,'Nepal','NP','NPL','','',0,1),
(150,'Netherlands','NL','NLD','','',0,1),
(151,'Netherlands Antilles','AN','ANT','','',0,1),
(152,'New Caledonia','NC','NCL','','',0,1),
(153,'New Zealand','NZ','NZL','','',0,1),
(154,'Nicaragua','NI','NIC','','',0,1),
(155,'Niger','NE','NER','','',0,1),
(156,'Nigeria','NG','NGA','','',0,1),
(157,'Niue','NU','NIU','','',0,1),
(158,'Norfolk Island','NF','NFK','','',0,1),
(159,'Northern Mariana Islands','MP','MNP','','',0,1),
(160,'Norway','NO','NOR','','',0,1),
(161,'Oman','OM','OMN','','',0,1),
(162,'Pakistan','PK','PAK','','',0,1),
(163,'Palau','PW','PLW','','',0,1),
(164,'Panama','PA','PAN','','',0,1),
(165,'Papua New Guinea','PG','PNG','','',0,1),
(166,'Paraguay','PY','PRY','','',0,1),
(167,'Peru','PE','PER','','',0,1),
(168,'Philippines','PH','PHL','','',0,1),
(169,'Pitcairn','PN','PCN','','',0,1),
(170,'Poland','PL','POL','','',0,1),
(171,'Portugal','PT','PRT','','',0,1),
(172,'Puerto Rico','PR','PRI','','',0,1),
(173,'Qatar','QA','QAT','','',0,1),
(174,'Reunion','RE','REU','','',0,1),
(175,'Romania','RO','ROM','','',0,1),
(176,'Russian Federation','RU','RUS','','+7 (XXX) XXX-XX-XX',0,1),
(177,'Rwanda','RW','RWA','','',0,1),
(178,'Saint Kitts and Nevis','KN','KNA','','',0,1),
(179,'Saint Lucia','LC','LCA','','',0,1),
(180,'Saint Vincent and the Grenadines','VC','VCT','','',0,1),
(181,'Samoa','WS','WSM','','',0,1),
(182,'San Marino','SM','SMR','','',0,1),
(183,'Sao Tome and Principe','ST','STP','','',0,1),
(184,'Saudi Arabia','SA','SAU','','',0,1),
(185,'Senegal','SN','SEN','','',0,1),
(186,'Seychelles','SC','SYC','','',0,1),
(187,'Sierra Leone','SL','SLE','','',0,1),
(188,'Singapore','SG','SGP','','',0,1),
(189,'Slovak Republic','SK','SVK','{firstname} {lastname}\r\n{company}\r\n{address_1}\r\n{address_2}\r\n{city} {postcode}\r\n{zone}\r\n{country}','',0,1),
(190,'Slovenia','SI','SVN','','',0,1),
(191,'Solomon Islands','SB','SLB','','',0,1),
(192,'Somalia','SO','SOM','','',0,1),
(193,'South Africa','ZA','ZAF','','',0,1),
(194,'South Georgia &amp; South Sandwich Islands','GS','SGS','','',0,1),
(195,'Spain','ES','ESP','','',0,1),
(196,'Sri Lanka','LK','LKA','','',0,1),
(197,'St. Helena','SH','SHN','','',0,1),
(198,'St. Pierre and Miquelon','PM','SPM','','',0,1),
(199,'Sudan','SD','SDN','','',0,1),
(200,'Suriname','SR','SUR','','',0,1),
(201,'Svalbard and Jan Mayen Islands','SJ','SJM','','',0,1),
(202,'Swaziland','SZ','SWZ','','',0,1),
(203,'Sweden','SE','SWE','{company}\r\n{firstname} {lastname}\r\n{address_1}\r\n{address_2}\r\n{postcode} {city}\r\n{country}','',1,1),
(204,'Switzerland','CH','CHE','','',0,1),
(205,'Syrian Arab Republic','SY','SYR','','',0,1),
(206,'Taiwan','TW','TWN','','',0,1),
(207,'Tajikistan','TJ','TJK','','',0,1),
(208,'Tanzania, United Republic of','TZ','TZA','','',0,1),
(209,'Thailand','TH','THA','','',0,1),
(210,'Togo','TG','TGO','','',0,1),
(211,'Tokelau','TK','TKL','','',0,1),
(212,'Tonga','TO','TON','','',0,1),
(213,'Trinidad and Tobago','TT','TTO','','',0,1),
(214,'Tunisia','TN','TUN','','',0,1),
(215,'Turkey','TR','TUR','','',0,1),
(216,'Turkmenistan','TM','TKM','','',0,1),
(217,'Turks and Caicos Islands','TC','TCA','','',0,1),
(218,'Tuvalu','TV','TUV','','',0,1),
(219,'Uganda','UG','UGA','','',0,1),
(220,'Украина','UA','UKR','{lastname} {firstname}\r\n{company}\r\n{country}\r\n{zone}\r\n{city} {postcode}\r\n{address_1}','+380 (XX) XXX-XX-XX',0,1),
(221,'United Arab Emirates','AE','ARE','','',0,1),
(222,'United Kingdom','GB','GBR','','',1,1),
(223,'United States','US','USA','{firstname} {lastname}\r\n{company}\r\n{address_1}\r\n{address_2}\r\n{city}, {zone} {postcode}\r\n{country}','+1 (XXX) XXX-XXXX',0,1),
(224,'United States Minor Outlying Islands','UM','UMI','','',0,1),
(225,'Uruguay','UY','URY','','',0,1),
(226,'Uzbekistan','UZ','UZB','','',0,1),
(227,'Vanuatu','VU','VUT','','',0,1),
(228,'Vatican City State (Holy See)','VA','VAT','','',0,1),
(229,'Venezuela','VE','VEN','','',0,1),
(230,'Viet Nam','VN','VNM','','',0,1),
(231,'Virgin Islands (British)','VG','VGB','','',0,1),
(232,'Virgin Islands (U.S.)','VI','VIR','','',0,1),
(233,'Wallis and Futuna Islands','WF','WLF','','',0,1),
(234,'Western Sahara','EH','ESH','','',0,1),
(235,'Yemen','YE','YEM','','',0,1),
(237,'Democratic Republic of Congo','CD','COD','','',0,1),
(238,'Zambia','ZM','ZMB','','',0,1),
(239,'Zimbabwe','ZW','ZWE','','',0,1),
(242,'Montenegro','ME','MNE','','',0,1),
(243,'Serbia','RS','SRB','','',0,1),
(244,'Aaland Islands','AX','ALA','','',0,1),
(245,'Bonaire, Sint Eustatius and Saba','BQ','BES','','',0,1),
(246,'Curacao','CW','CUW','','',0,1),
(247,'Palestinian Territory, Occupied','PS','PSE','','',0,1),
(248,'South Sudan','SS','SSD','','',0,1),
(249,'St. Barthelemy','BL','BLM','','',0,1),
(250,'St. Martin (French part)','MF','MAF','','',0,1),
(251,'Canary Islands','IC','ICA','','',0,1),
(252,'Ascension Island (British)','AC','ASC','','',0,1),
(253,'Kosovo, Republic of','XK','UNK','','',0,1),
(254,'Isle of Man','IM','IMN','','',0,1),
(255,'Tristan da Cunha','TA','SHN','','',0,1),
(256,'Guernsey','GG','GGY','','',0,1),
(257,'Jersey','JE','JEY','','',0,1);
/*!40000 ALTER TABLE `oc_country` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `oc_country_description`
--

DROP TABLE IF EXISTS `oc_country_description`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `oc_country_description` (
  `country_id` int(11) NOT NULL,
  `language_id` int(11) NOT NULL,
  `name` varchar(128) NOT NULL,
  `address_format` mediumtext NOT NULL,
  PRIMARY KEY (`country_id`,`language_id`),
  KEY `language_id` (`language_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `oc_country_description`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `oc_country_description` WRITE;
/*!40000 ALTER TABLE `oc_country_description` DISABLE KEYS */;
INSERT INTO `oc_country_description` VALUES
(1,1,'Afghanistan',''),
(1,2,'Afghanistan',''),
(1,3,'Afghanistan',''),
(2,1,'Albania',''),
(3,1,'Algeria',''),
(4,1,'American Samoa',''),
(5,1,'Andorra',''),
(6,1,'Angola',''),
(7,1,'Anguilla',''),
(8,1,'Antarctica',''),
(9,1,'Antigua and Barbuda',''),
(10,1,'Argentina',''),
(11,1,'Armenia',''),
(12,1,'Aruba',''),
(13,1,'Australia',''),
(14,1,'Austria',''),
(15,1,'Azerbaijan',''),
(16,1,'Bahamas',''),
(17,1,'Bahrain',''),
(18,1,'Bangladesh',''),
(19,1,'Barbados',''),
(20,1,'Belarus',''),
(21,1,'Belgium','{firstname} {lastname}\r\n{company}\r\n{address_1}\r\n{address_2}\r\n{postcode} {city}\r\n{country}'),
(22,1,'Belize',''),
(23,1,'Benin',''),
(24,1,'Bermuda',''),
(25,1,'Bhutan',''),
(26,1,'Bolivia',''),
(27,1,'Bosnia and Herzegovina',''),
(28,1,'Botswana',''),
(29,1,'Bouvet Island',''),
(30,1,'Brazil',''),
(31,1,'British Indian Ocean Territory',''),
(32,1,'Brunei Darussalam',''),
(33,1,'Bulgaria',''),
(34,1,'Burkina Faso',''),
(35,1,'Burundi',''),
(36,1,'Cambodia',''),
(37,1,'Cameroon',''),
(38,1,'Canada',''),
(39,1,'Cape Verde',''),
(40,1,'Cayman Islands',''),
(41,1,'Central African Republic',''),
(42,1,'Chad',''),
(43,1,'Chile',''),
(44,1,'China',''),
(45,1,'Christmas Island',''),
(46,1,'Cocos (Keeling) Islands',''),
(47,1,'Colombia',''),
(48,1,'Comoros',''),
(49,1,'Congo',''),
(50,1,'Cook Islands',''),
(51,1,'Costa Rica',''),
(52,1,'Cote D\'Ivoire',''),
(53,1,'Croatia',''),
(54,1,'Cuba',''),
(55,1,'Cyprus',''),
(56,1,'Czech Republic',''),
(57,1,'Denmark',''),
(58,1,'Djibouti',''),
(59,1,'Dominica',''),
(60,1,'Dominican Republic',''),
(61,1,'East Timor',''),
(62,1,'Ecuador',''),
(63,1,'Egypt',''),
(64,1,'El Salvador',''),
(65,1,'Equatorial Guinea',''),
(66,1,'Eritrea',''),
(67,1,'Estonia',''),
(68,1,'Ethiopia',''),
(69,1,'Falkland Islands (Malvinas)',''),
(70,1,'Faroe Islands',''),
(71,1,'Fiji',''),
(72,1,'Finland',''),
(74,1,'France, Metropolitan','{firstname} {lastname}\r\n{company}\r\n{address_1}\r\n{address_2}\r\n{postcode} {city}\r\n{country}'),
(75,1,'French Guiana',''),
(76,1,'French Polynesia',''),
(77,1,'French Southern Territories',''),
(78,1,'Gabon',''),
(79,1,'Gambia',''),
(80,1,'Georgia',''),
(81,1,'Germany','{company}\r\n{firstname} {lastname}\r\n{address_1}\r\n{address_2}\r\n{postcode} {city}\r\n{country}'),
(82,1,'Ghana',''),
(83,1,'Gibraltar',''),
(84,1,'Greece',''),
(85,1,'Greenland',''),
(86,1,'Grenada',''),
(87,1,'Guadeloupe',''),
(88,1,'Guam',''),
(89,1,'Guatemala',''),
(90,1,'Guinea',''),
(91,1,'Guinea-Bissau',''),
(92,1,'Guyana',''),
(93,1,'Haiti',''),
(94,1,'Heard and Mc Donald Islands',''),
(95,1,'Honduras',''),
(96,1,'Hong Kong',''),
(97,1,'Hungary',''),
(98,1,'Iceland',''),
(99,1,'India',''),
(100,1,'Indonesia',''),
(101,1,'Iran (Islamic Republic of)',''),
(102,1,'Iraq',''),
(103,1,'Ireland',''),
(104,1,'Israel',''),
(105,1,'Italy',''),
(106,1,'Jamaica',''),
(107,1,'Japan',''),
(108,1,'Jordan',''),
(109,1,'Kazakhstan',''),
(110,1,'Kenya',''),
(111,1,'Kiribati',''),
(112,1,'North Korea',''),
(113,1,'South Korea',''),
(114,1,'Kuwait',''),
(115,1,'Kyrgyzstan',''),
(116,1,'Lao People\'s Democratic Republic',''),
(117,1,'Latvia',''),
(118,1,'Lebanon',''),
(119,1,'Lesotho',''),
(120,1,'Liberia',''),
(121,1,'Libyan Arab Jamahiriya',''),
(122,1,'Liechtenstein',''),
(123,1,'Lithuania',''),
(124,1,'Luxembourg',''),
(125,1,'Macau',''),
(126,1,'FYROM',''),
(127,1,'Madagascar',''),
(128,1,'Malawi',''),
(129,1,'Malaysia',''),
(130,1,'Maldives',''),
(131,1,'Mali',''),
(132,1,'Malta',''),
(133,1,'Marshall Islands',''),
(134,1,'Martinique',''),
(135,1,'Mauritania',''),
(136,1,'Mauritius',''),
(137,1,'Mayotte',''),
(138,1,'Mexico',''),
(139,1,'Micronesia, Federated States of',''),
(140,1,'Moldova, Republic of',''),
(141,1,'Monaco',''),
(142,1,'Mongolia',''),
(143,1,'Montserrat',''),
(144,1,'Morocco',''),
(145,1,'Mozambique',''),
(146,1,'Myanmar',''),
(147,1,'Namibia',''),
(148,1,'Nauru',''),
(149,1,'Nepal',''),
(150,1,'Netherlands',''),
(151,1,'Netherlands Antilles',''),
(152,1,'New Caledonia',''),
(153,1,'New Zealand',''),
(154,1,'Nicaragua',''),
(155,1,'Niger',''),
(156,1,'Nigeria',''),
(157,1,'Niue',''),
(158,1,'Norfolk Island',''),
(159,1,'Northern Mariana Islands',''),
(160,1,'Norway',''),
(161,1,'Oman',''),
(162,1,'Pakistan',''),
(163,1,'Palau',''),
(164,1,'Panama',''),
(165,1,'Papua New Guinea',''),
(166,1,'Paraguay',''),
(167,1,'Peru',''),
(168,1,'Philippines',''),
(169,1,'Pitcairn',''),
(170,1,'Poland',''),
(171,1,'Portugal',''),
(172,1,'Puerto Rico',''),
(173,1,'Qatar',''),
(174,1,'Reunion',''),
(175,1,'Romania',''),
(176,1,'Russian Federation',''),
(177,1,'Rwanda',''),
(178,1,'Saint Kitts and Nevis',''),
(179,1,'Saint Lucia',''),
(180,1,'Saint Vincent and the Grenadines',''),
(181,1,'Samoa',''),
(182,1,'San Marino',''),
(183,1,'Sao Tome and Principe',''),
(184,1,'Saudi Arabia',''),
(185,1,'Senegal',''),
(186,1,'Seychelles',''),
(187,1,'Sierra Leone',''),
(188,1,'Singapore',''),
(189,1,'Slovak Republic','{firstname} {lastname}\r\n{company}\r\n{address_1}\r\n{address_2}\r\n{city} {postcode}\r\n{zone}\r\n{country}'),
(190,1,'Slovenia',''),
(191,1,'Solomon Islands',''),
(192,1,'Somalia',''),
(193,1,'South Africa',''),
(194,1,'South Georgia &amp; South Sandwich Islands',''),
(195,1,'Spain',''),
(196,1,'Sri Lanka',''),
(197,1,'St. Helena',''),
(198,1,'St. Pierre and Miquelon',''),
(199,1,'Sudan',''),
(200,1,'Suriname',''),
(201,1,'Svalbard and Jan Mayen Islands',''),
(202,1,'Swaziland',''),
(203,1,'Sweden','{company}\r\n{firstname} {lastname}\r\n{address_1}\r\n{address_2}\r\n{postcode} {city}\r\n{country}'),
(204,1,'Switzerland',''),
(205,1,'Syrian Arab Republic',''),
(206,1,'Taiwan',''),
(207,1,'Tajikistan',''),
(208,1,'Tanzania, United Republic of',''),
(209,1,'Thailand',''),
(210,1,'Togo',''),
(211,1,'Tokelau',''),
(212,1,'Tonga',''),
(213,1,'Trinidad and Tobago',''),
(214,1,'Tunisia',''),
(215,1,'Turkey',''),
(216,1,'Turkmenistan',''),
(217,1,'Turks and Caicos Islands',''),
(218,1,'Tuvalu',''),
(219,1,'Uganda',''),
(220,1,'Ukraine','{lastname} {firstname}\r\n{company}\r\n{country}\r\n{zone}\r\n{city} {postcode}\r\n{address_1}'),
(220,2,'Ukraine','{lastname} {firstname}\r\n{company}\r\n{country}\r\n{zone}\r\n{city} {postcode}\r\n{address_1}'),
(220,3,'Украина','{lastname} {firstname}\r\n{company}\r\n{country}\r\n{zone}\r\n{city} {postcode}\r\n{address_1}'),
(221,1,'United Arab Emirates',''),
(222,1,'United Kingdom',''),
(223,1,'United States','{firstname} {lastname}\r\n{company}\r\n{address_1}\r\n{address_2}\r\n{city}, {zone} {postcode}\r\n{country}'),
(224,1,'United States Minor Outlying Islands',''),
(225,1,'Uruguay',''),
(226,1,'Uzbekistan',''),
(227,1,'Vanuatu',''),
(228,1,'Vatican City State (Holy See)',''),
(229,1,'Venezuela',''),
(230,1,'Viet Nam',''),
(231,1,'Virgin Islands (British)',''),
(232,1,'Virgin Islands (U.S.)',''),
(233,1,'Wallis and Futuna Islands',''),
(234,1,'Western Sahara',''),
(235,1,'Yemen',''),
(237,1,'Democratic Republic of Congo',''),
(238,1,'Zambia',''),
(239,1,'Zimbabwe',''),
(242,1,'Montenegro',''),
(243,1,'Serbia',''),
(244,1,'Aaland Islands',''),
(244,2,'Aaland Islands',''),
(244,3,'Aaland Islands',''),
(245,1,'Bonaire, Sint Eustatius and Saba',''),
(246,1,'Curacao',''),
(247,1,'Palestinian Territory, Occupied',''),
(248,1,'South Sudan',''),
(249,1,'St. Barthelemy',''),
(250,1,'St. Martin (French part)',''),
(251,1,'Canary Islands',''),
(252,1,'Ascension Island (British)',''),
(253,1,'Kosovo, Republic of',''),
(254,1,'Isle of Man',''),
(255,1,'Tristan da Cunha',''),
(256,1,'Guernsey',''),
(257,1,'Jersey','');
/*!40000 ALTER TABLE `oc_country_description` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `oc_coupon`
--

DROP TABLE IF EXISTS `oc_coupon`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `oc_coupon` (
  `coupon_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(128) NOT NULL,
  `code` varchar(20) NOT NULL,
  `type` char(1) NOT NULL,
  `discount` decimal(15,4) NOT NULL,
  `logged` tinyint(1) NOT NULL,
  `shipping` tinyint(1) NOT NULL,
  `total` decimal(15,4) NOT NULL,
  `date_start` date NOT NULL DEFAULT '0000-00-00',
  `date_end` date NOT NULL DEFAULT '0000-00-00',
  `uses_total` int(11) NOT NULL,
  `uses_customer` varchar(11) NOT NULL,
  `status` tinyint(1) NOT NULL,
  `auto_renew` tinyint(1) NOT NULL DEFAULT 0,
  `date_added` datetime NOT NULL,
  PRIMARY KEY (`coupon_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `oc_coupon`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `oc_coupon` WRITE;
/*!40000 ALTER TABLE `oc_coupon` DISABLE KEYS */;
INSERT INTO `oc_coupon` VALUES
(4,'-10% Discount','2222','P',10.0000,0,0,0.0000,'2014-01-01','2026-01-01',10,'10',1,0,'2009-01-27 13:55:03'),
(5,'Free Shipping','3333','P',0.0000,0,1,100.0000,'2014-01-01','2014-02-01',10,'10',0,0,'2009-03-14 21:13:53'),
(6,'-10.00 Discount','1111','F',10.0000,0,0,10.0000,'2014-01-01','2020-01-01',100000,'10000',0,0,'2009-03-14 21:15:18');
/*!40000 ALTER TABLE `oc_coupon` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `oc_coupon_category`
--

DROP TABLE IF EXISTS `oc_coupon_category`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `oc_coupon_category` (
  `coupon_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  PRIMARY KEY (`coupon_id`,`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `oc_coupon_category`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `oc_coupon_category` WRITE;
/*!40000 ALTER TABLE `oc_coupon_category` DISABLE KEYS */;
/*!40000 ALTER TABLE `oc_coupon_category` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `oc_coupon_history`
--

DROP TABLE IF EXISTS `oc_coupon_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `oc_coupon_history` (
  `coupon_history_id` int(11) NOT NULL AUTO_INCREMENT,
  `coupon_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `amount` decimal(15,4) NOT NULL,
  `date_added` datetime NOT NULL,
  PRIMARY KEY (`coupon_history_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `oc_coupon_history`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `oc_coupon_history` WRITE;
/*!40000 ALTER TABLE `oc_coupon_history` DISABLE KEYS */;
/*!40000 ALTER TABLE `oc_coupon_history` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `oc_coupon_product`
--

DROP TABLE IF EXISTS `oc_coupon_product`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `oc_coupon_product` (
  `coupon_product_id` int(11) NOT NULL AUTO_INCREMENT,
  `coupon_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  PRIMARY KEY (`coupon_product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `oc_coupon_product`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `oc_coupon_product` WRITE;
/*!40000 ALTER TABLE `oc_coupon_product` DISABLE KEYS */;
/*!40000 ALTER TABLE `oc_coupon_product` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `oc_currency`
--

DROP TABLE IF EXISTS `oc_currency`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `oc_currency` (
  `currency_id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(32) NOT NULL,
  `code` varchar(3) NOT NULL,
  `symbol_left` varchar(12) NOT NULL,
  `symbol_right` varchar(12) NOT NULL,
  `decimal_place` char(1) NOT NULL,
  `value` double(15,8) NOT NULL,
  `status` tinyint(1) NOT NULL,
  `date_modified` datetime NOT NULL,
  PRIMARY KEY (`currency_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `oc_currency`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `oc_currency` WRITE;
/*!40000 ALTER TABLE `oc_currency` DISABLE KEYS */;
INSERT INTO `oc_currency` VALUES
(1,'Pound Sterling','GBP','£','','2',0.01684976,1,'2026-06-30 08:11:01'),
(2,'US Dollar','USD','$','','2',0.02229176,1,'2026-06-30 08:11:01'),
(3,'Euro','EUR','','€','2',0.01954388,1,'2026-06-30 08:11:01'),
(4,'Гривна','UAH','','₴','0',1.00000000,1,'2026-06-30 08:11:01');
/*!40000 ALTER TABLE `oc_currency` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `oc_custom_field`
--

DROP TABLE IF EXISTS `oc_custom_field`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `oc_custom_field` (
  `custom_field_id` int(11) NOT NULL AUTO_INCREMENT,
  `type` varchar(32) NOT NULL,
  `value` mediumtext NOT NULL,
  `validation` varchar(255) NOT NULL,
  `location` varchar(10) NOT NULL,
  `status` tinyint(1) NOT NULL,
  `sort_order` int(11) NOT NULL,
  PRIMARY KEY (`custom_field_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `oc_custom_field`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `oc_custom_field` WRITE;
/*!40000 ALTER TABLE `oc_custom_field` DISABLE KEYS */;
/*!40000 ALTER TABLE `oc_custom_field` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `oc_custom_field_customer_group`
--

DROP TABLE IF EXISTS `oc_custom_field_customer_group`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `oc_custom_field_customer_group` (
  `custom_field_id` int(11) NOT NULL,
  `customer_group_id` int(11) NOT NULL,
  `required` tinyint(1) NOT NULL,
  PRIMARY KEY (`custom_field_id`,`customer_group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `oc_custom_field_customer_group`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `oc_custom_field_customer_group` WRITE;
/*!40000 ALTER TABLE `oc_custom_field_customer_group` DISABLE KEYS */;
/*!40000 ALTER TABLE `oc_custom_field_customer_group` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `oc_custom_field_description`
--

DROP TABLE IF EXISTS `oc_custom_field_description`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `oc_custom_field_description` (
  `custom_field_id` int(11) NOT NULL,
  `language_id` int(11) NOT NULL,
  `name` varchar(128) NOT NULL,
  PRIMARY KEY (`custom_field_id`,`language_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `oc_custom_field_description`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `oc_custom_field_description` WRITE;
/*!40000 ALTER TABLE `oc_custom_field_description` DISABLE KEYS */;
/*!40000 ALTER TABLE `oc_custom_field_description` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `oc_custom_field_value`
--

DROP TABLE IF EXISTS `oc_custom_field_value`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `oc_custom_field_value` (
  `custom_field_value_id` int(11) NOT NULL AUTO_INCREMENT,
  `custom_field_id` int(11) NOT NULL,
  `sort_order` int(11) NOT NULL,
  PRIMARY KEY (`custom_field_value_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `oc_custom_field_value`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `oc_custom_field_value` WRITE;
/*!40000 ALTER TABLE `oc_custom_field_value` DISABLE KEYS */;
/*!40000 ALTER TABLE `oc_custom_field_value` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `oc_custom_field_value_description`
--

DROP TABLE IF EXISTS `oc_custom_field_value_description`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `oc_custom_field_value_description` (
  `custom_field_value_id` int(11) NOT NULL,
  `language_id` int(11) NOT NULL,
  `custom_field_id` int(11) NOT NULL,
  `name` varchar(128) NOT NULL,
  PRIMARY KEY (`custom_field_value_id`,`language_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `oc_custom_field_value_description`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `oc_custom_field_value_description` WRITE;
/*!40000 ALTER TABLE `oc_custom_field_value_description` DISABLE KEYS */;
/*!40000 ALTER TABLE `oc_custom_field_value_description` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `oc_customer`
--

DROP TABLE IF EXISTS `oc_customer`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `oc_customer` (
  `customer_id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_group_id` int(11) NOT NULL,
  `store_id` int(11) NOT NULL DEFAULT 0,
  `language_id` int(11) NOT NULL,
  `firstname` varchar(32) NOT NULL,
  `lastname` varchar(32) NOT NULL,
  `email` varchar(96) NOT NULL,
  `telephone` varchar(32) NOT NULL,
  `company` varchar(64) NOT NULL DEFAULT '',
  `tax_number` varchar(32) NOT NULL DEFAULT '',
  `fax` varchar(32) NOT NULL,
  `password` varchar(255) NOT NULL,
  `salt` varchar(32) NOT NULL,
  `cart` mediumtext DEFAULT NULL,
  `wishlist` mediumtext DEFAULT NULL,
  `newsletter` tinyint(1) NOT NULL DEFAULT 0,
  `address_id` int(11) NOT NULL DEFAULT 0,
  `custom_field` mediumtext NOT NULL,
  `ip` varchar(40) NOT NULL,
  `status` tinyint(1) NOT NULL,
  `safe` tinyint(1) NOT NULL,
  `token` mediumtext NOT NULL,
  `code` varchar(40) NOT NULL,
  `date_added` datetime NOT NULL,
  `remember_token` varchar(128) NOT NULL DEFAULT '',
  PRIMARY KEY (`customer_id`),
  KEY `idx_customer_date` (`date_added`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `oc_customer`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `oc_customer` WRITE;
/*!40000 ALTER TABLE `oc_customer` DISABLE KEYS */;
INSERT INTO `oc_customer` VALUES
(9,1,0,1,'Test','Test','test@test.com','+380 (17) 554-67-72','','','','$argon2id$v=19$m=65536,t=4,p=1$bTJtQUIxVFJjbFpTc2dSbw$6pMPfs78GeB8EBXF+NjWjXbqovAoscgDp9CCuzWzSms','UX0CQTOjX',NULL,NULL,0,19,'','10.89.2.8',1,0,'','','2026-05-29 11:59:34','a3ca2c9b0c8900ed4a5511e2af89fe5efa1e7b3a0bb987f5b5f5b458a700c866');
/*!40000 ALTER TABLE `oc_customer` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `oc_customer_activity`
--

DROP TABLE IF EXISTS `oc_customer_activity`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `oc_customer_activity` (
  `customer_activity_id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `key` varchar(64) NOT NULL,
  `data` mediumtext NOT NULL,
  `ip` varchar(40) NOT NULL,
  `date_added` datetime NOT NULL,
  PRIMARY KEY (`customer_activity_id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `oc_customer_activity`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `oc_customer_activity` WRITE;
/*!40000 ALTER TABLE `oc_customer_activity` DISABLE KEYS */;
INSERT INTO `oc_customer_activity` VALUES
(1,9,'order_account','{\"customer_id\":\"9\",\"name\":\"Test Test\",\"order_id\":75}','10.89.2.7','2026-06-22 12:07:37'),
(2,9,'order_account','{\"customer_id\":\"9\",\"name\":\"Test Test\",\"order_id\":76}','10.89.2.7','2026-06-22 12:07:40'),
(3,9,'order_account','{\"customer_id\":\"9\",\"name\":\"Test Test\",\"order_id\":77}','10.89.2.7','2026-06-22 12:07:58'),
(4,9,'order_account','{\"customer_id\":\"9\",\"name\":\"Test Test\",\"order_id\":78}','10.89.2.7','2026-06-22 12:08:58'),
(5,9,'order_account','{\"customer_id\":\"9\",\"name\":\"Test Test\",\"order_id\":79}','10.89.2.7','2026-06-22 12:22:11'),
(6,9,'order_account','{\"customer_id\":\"9\",\"name\":\"Test Test\",\"order_id\":80}','10.89.2.7','2026-06-22 12:34:11'),
(7,9,'order_account','{\"customer_id\":\"9\",\"name\":\"Test Test\",\"order_id\":81}','10.89.2.7','2026-06-22 12:46:19'),
(8,9,'order_account','{\"customer_id\":\"9\",\"name\":\"Test Test\",\"order_id\":82}','10.89.2.7','2026-06-22 12:51:52'),
(9,9,'order_account','{\"customer_id\":\"9\",\"name\":\"Test Test\",\"order_id\":83}','10.89.2.7','2026-06-22 12:58:11'),
(10,9,'order_account','{\"customer_id\":\"9\",\"name\":\"Test Test\",\"order_id\":84}','10.89.2.7','2026-06-22 13:16:27'),
(11,9,'order_account','{\"customer_id\":\"9\",\"name\":\"Test Test\",\"order_id\":85}','10.89.2.7','2026-06-22 13:47:40'),
(12,9,'order_account','{\"customer_id\":\"9\",\"name\":\"Test Test\",\"order_id\":86}','10.89.2.7','2026-06-22 13:48:01'),
(13,9,'order_account','{\"customer_id\":\"9\",\"name\":\"Test Test\",\"order_id\":87}','10.89.2.7','2026-06-22 14:14:35'),
(14,9,'order_account','{\"customer_id\":\"9\",\"name\":\"Test Test\",\"order_id\":88}','10.89.2.7','2026-06-22 14:16:09');
/*!40000 ALTER TABLE `oc_customer_activity` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `oc_customer_affiliate`
--

DROP TABLE IF EXISTS `oc_customer_affiliate`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `oc_customer_affiliate` (
  `customer_id` int(11) NOT NULL,
  `company` varchar(40) NOT NULL,
  `website` varchar(255) NOT NULL,
  `tracking` varchar(64) NOT NULL,
  `commission` decimal(4,2) NOT NULL DEFAULT 0.00,
  `tax` varchar(64) NOT NULL,
  `payment` varchar(6) NOT NULL,
  `cheque` varchar(100) NOT NULL,
  `paypal` varchar(64) NOT NULL,
  `bank_name` varchar(64) NOT NULL,
  `bank_branch_number` varchar(64) NOT NULL,
  `bank_swift_code` varchar(64) NOT NULL,
  `bank_account_name` varchar(64) NOT NULL,
  `bank_account_number` varchar(64) NOT NULL,
  `custom_field` mediumtext NOT NULL,
  `status` tinyint(1) NOT NULL,
  `date_added` datetime NOT NULL,
  PRIMARY KEY (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `oc_customer_affiliate`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `oc_customer_affiliate` WRITE;
/*!40000 ALTER TABLE `oc_customer_affiliate` DISABLE KEYS */;
/*!40000 ALTER TABLE `oc_customer_affiliate` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `oc_customer_approval`
--

DROP TABLE IF EXISTS `oc_customer_approval`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `oc_customer_approval` (
  `customer_approval_id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `type` varchar(9) NOT NULL,
  `date_added` datetime NOT NULL,
  PRIMARY KEY (`customer_approval_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `oc_customer_approval`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `oc_customer_approval` WRITE;
/*!40000 ALTER TABLE `oc_customer_approval` DISABLE KEYS */;
/*!40000 ALTER TABLE `oc_customer_approval` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `oc_customer_group`
--

DROP TABLE IF EXISTS `oc_customer_group`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `oc_customer_group` (
  `customer_group_id` int(11) NOT NULL AUTO_INCREMENT,
  `approval` int(11) NOT NULL,
  `discount_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `markup_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `sort_order` int(11) NOT NULL,
  PRIMARY KEY (`customer_group_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `oc_customer_group`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `oc_customer_group` WRITE;
/*!40000 ALTER TABLE `oc_customer_group` DISABLE KEYS */;
INSERT INTO `oc_customer_group` VALUES
(1,0,90.00,0.00,1);
/*!40000 ALTER TABLE `oc_customer_group` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `oc_customer_group_description`
--

DROP TABLE IF EXISTS `oc_customer_group_description`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `oc_customer_group_description` (
  `customer_group_id` int(11) NOT NULL,
  `language_id` int(11) NOT NULL,
  `name` varchar(32) NOT NULL,
  `description` mediumtext NOT NULL,
  PRIMARY KEY (`customer_group_id`,`language_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `oc_customer_group_description`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `oc_customer_group_description` WRITE;
/*!40000 ALTER TABLE `oc_customer_group_description` DISABLE KEYS */;
INSERT INTO `oc_customer_group_description` VALUES
(1,1,'Default',''),
(1,2,'За замовчуванням',''),
(1,3,'По умолчанию','');
/*!40000 ALTER TABLE `oc_customer_group_description` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `oc_customer_history`
--

DROP TABLE IF EXISTS `oc_customer_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `oc_customer_history` (
  `customer_history_id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `comment` mediumtext NOT NULL,
  `date_added` datetime NOT NULL,
  PRIMARY KEY (`customer_history_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `oc_customer_history`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `oc_customer_history` WRITE;
/*!40000 ALTER TABLE `oc_customer_history` DISABLE KEYS */;
/*!40000 ALTER TABLE `oc_customer_history` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `oc_customer_ip`
--

DROP TABLE IF EXISTS `oc_customer_ip`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `oc_customer_ip` (
  `customer_ip_id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `ip` varchar(40) NOT NULL,
  `date_added` datetime NOT NULL,
  PRIMARY KEY (`customer_ip_id`),
  KEY `ip` (`ip`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `oc_customer_ip`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `oc_customer_ip` WRITE;
/*!40000 ALTER TABLE `oc_customer_ip` DISABLE KEYS */;
INSERT INTO `oc_customer_ip` VALUES
(14,9,'10.89.1.7','2026-05-29 11:59:34'),
(15,9,'10.89.2.7','2026-06-17 10:59:50'),
(16,9,'10.89.2.8','2026-06-29 16:32:44');
/*!40000 ALTER TABLE `oc_customer_ip` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `oc_customer_login`
--

DROP TABLE IF EXISTS `oc_customer_login`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `oc_customer_login` (
  `customer_login_id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(96) NOT NULL,
  `ip` varchar(40) NOT NULL,
  `total` int(11) NOT NULL,
  `date_added` datetime NOT NULL,
  `date_modified` datetime NOT NULL,
  PRIMARY KEY (`customer_login_id`),
  KEY `email` (`email`),
  KEY `ip` (`ip`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `oc_customer_login`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `oc_customer_login` WRITE;
/*!40000 ALTER TABLE `oc_customer_login` DISABLE KEYS */;
INSERT INTO `oc_customer_login` VALUES
(6,'','127.0.0.1',5,'2025-12-19 15:56:27','2026-04-01 16:22:31');
/*!40000 ALTER TABLE `oc_customer_login` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `oc_customer_online`
--

DROP TABLE IF EXISTS `oc_customer_online`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `oc_customer_online` (
  `ip` varchar(40) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `url` mediumtext NOT NULL,
  `referer` mediumtext NOT NULL,
  `date_added` datetime NOT NULL,
  PRIMARY KEY (`ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `oc_customer_online`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `oc_customer_online` WRITE;
/*!40000 ALTER TABLE `oc_customer_online` DISABLE KEYS */;
/*!40000 ALTER TABLE `oc_customer_online` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `oc_customer_reward`
--

DROP TABLE IF EXISTS `oc_customer_reward`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `oc_customer_reward` (
  `customer_reward_id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL DEFAULT 0,
  `order_id` int(11) NOT NULL DEFAULT 0,
  `description` mediumtext NOT NULL,
  `points` int(11) NOT NULL DEFAULT 0,
  `date_added` datetime NOT NULL,
  PRIMARY KEY (`customer_reward_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `oc_customer_reward`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `oc_customer_reward` WRITE;
/*!40000 ALTER TABLE `oc_customer_reward` DISABLE KEYS */;
/*!40000 ALTER TABLE `oc_customer_reward` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `oc_customer_search`
--

DROP TABLE IF EXISTS `oc_customer_search`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `oc_customer_search` (
  `customer_search_id` int(11) NOT NULL AUTO_INCREMENT,
  `store_id` int(11) NOT NULL,
  `language_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `keyword` varchar(255) NOT NULL,
  `category_id` int(11) DEFAULT NULL,
  `sub_category` tinyint(1) NOT NULL,
  `description` tinyint(1) NOT NULL,
  `products` int(11) NOT NULL,
  `ip` varchar(40) NOT NULL,
  `date_added` datetime NOT NULL,
  PRIMARY KEY (`customer_search_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `oc_customer_search`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `oc_customer_search` WRITE;
/*!40000 ALTER TABLE `oc_customer_search` DISABLE KEYS */;
/*!40000 ALTER TABLE `oc_customer_search` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `oc_customer_transaction`
--

DROP TABLE IF EXISTS `oc_customer_transaction`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `oc_customer_transaction` (
  `customer_transaction_id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `description` mediumtext NOT NULL,
  `amount` decimal(15,4) NOT NULL,
  `date_added` datetime NOT NULL,
  PRIMARY KEY (`customer_transaction_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `oc_customer_transaction`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `oc_customer_transaction` WRITE;
/*!40000 ALTER TABLE `oc_customer_transaction` DISABLE KEYS */;
/*!40000 ALTER TABLE `oc_customer_transaction` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `oc_customer_wishlist`
--

DROP TABLE IF EXISTS `oc_customer_wishlist`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `oc_customer_wishlist` (
  `customer_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `date_added` datetime NOT NULL,
  PRIMARY KEY (`customer_id`,`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci ROW_FORMAT=DYNAMIC;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `oc_customer_wishlist`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `oc_customer_wishlist` WRITE;
/*!40000 ALTER TABLE `oc_customer_wishlist` DISABLE KEYS */;
/*!40000 ALTER TABLE `oc_customer_wishlist` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `oc_dockercart_export_yml_profile`
--

DROP TABLE IF EXISTS `oc_dockercart_export_yml_profile`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `oc_dockercart_export_yml_profile` (
  `profile_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `store_id` int(11) NOT NULL DEFAULT 0,
  `currency_code` varchar(3) NOT NULL DEFAULT 'USD',
  `language_id` int(11) NOT NULL DEFAULT 1,
  `shop_name` varchar(255) NOT NULL DEFAULT '',
  `company_name` varchar(255) NOT NULL DEFAULT '',
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `max_products` int(11) NOT NULL DEFAULT 50000,
  `split_files` tinyint(1) NOT NULL DEFAULT 0,
  `products_per_file` int(11) NOT NULL DEFAULT 10000,
  `settings` text DEFAULT NULL,
  `date_added` datetime NOT NULL,
  `date_modified` datetime NOT NULL,
  PRIMARY KEY (`profile_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `oc_dockercart_export_yml_profile`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `oc_dockercart_export_yml_profile` WRITE;
/*!40000 ALTER TABLE `oc_dockercart_export_yml_profile` DISABLE KEYS */;
/*!40000 ALTER TABLE `oc_dockercart_export_yml_profile` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Dumping events for database 'dockercart'
--

--
-- Dumping routines for database 'dockercart'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*M!100616 SET NOTE_VERBOSITY=@OLD_NOTE_VERBOSITY */;

-- Dump completed on 2026-07-09 10:32:21
