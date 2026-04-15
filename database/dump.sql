/*M!999999\- enable the sandbox mode */ 

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `async_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `async_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `job_key` char(32) NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `handler` varchar(120) NOT NULL,
  `title` varchar(255) NOT NULL,
  `status` varchar(24) NOT NULL DEFAULT 'queued',
  `pid` bigint(20) DEFAULT NULL,
  `payload_json` longtext NOT NULL,
  `progress_bars_json` longtext DEFAULT NULL,
  `warnings_json` longtext DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `download_name` varchar(255) DEFAULT NULL,
  `download_type` varchar(16) DEFAULT NULL,
  `download_path` text DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `finished_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `async_jobs_job_key_unique` (`job_key`),
  KEY `async_jobs_user_id_idx` (`user_id`),
  KEY `async_jobs_status_idx` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `async_jobs` WRITE;
/*!40000 ALTER TABLE `async_jobs` DISABLE KEYS */;
INSERT INTO `async_jobs` VALUES
(1,'bffb30ff1e9beae95f1928926de746a2',10,'pages/history/export/normale.php','BILLS','completed',789480,'{\"scope\":\"selected\",\"export_type\":\"normale\",\"filters\":{\"group\":[],\"name\":[],\"data\":{\"all\":true,\"da\":\"2026-04-06\",\"a\":\"2026-04-06\"}},\"selected_ids\":[1]}','[{\"key\":\"progress_bar_1\",\"title\":\"BILLS\",\"total\":1,\"current\":1,\"percent\":100,\"warnings\":[],\"status\":\"completed\"}]','[]',NULL,'bills-normale-20260406-143906.xlsx','xlsx','/var/www/html/my-bills/storage/async/files/2026-04-06/bffb30ff1e9beae95f1928926de746a2-bills-normale-20260406-143906.xlsx','2026-04-06 14:39:06','2026-04-06 14:39:06','2026-04-06 12:39:06','2026-04-06 12:39:06'),
(2,'4eb9f791be17b94428324b2f4882470f',10,'pages/history/export/normale.php','BILLS','completed',789490,'{\"scope\":\"filter\",\"export_type\":\"normale\",\"filters\":{\"group\":[1],\"name\":[],\"data\":{\"all\":false,\"da\":\"2026-03-01\",\"a\":\"2026-03-31\"}},\"selected_ids\":[]}','[{\"key\":\"progress_bar_1\",\"title\":\"BILLS\",\"total\":1,\"current\":1,\"percent\":100,\"warnings\":[],\"status\":\"completed\"}]','[]',NULL,'bills-normale-20260406-143907.xlsx','xlsx','/var/www/html/my-bills/storage/async/files/2026-04-06/4eb9f791be17b94428324b2f4882470f-bills-normale-20260406-143907.xlsx','2026-04-06 14:39:06','2026-04-06 14:39:07','2026-04-06 12:39:06','2026-04-06 12:39:07'),
(3,'a9132f4c7bb610a47df77c7ee17f068a',10,'pages/history/historic/export/normale.php','BILLS','completed',836163,'{\"scope\":\"filter\",\"export_type\":\"normale\",\"filters\":{\"group\":[],\"name\":[],\"data\":{\"all\":false,\"da\":\"2026-03-23\",\"a\":\"2026-04-23\"}},\"selected_ids\":[]}','[{\"key\":\"progress_bar_1\",\"title\":\"BILLS\",\"total\":48,\"current\":48,\"percent\":100,\"warnings\":[],\"status\":\"completed\"}]','[]',NULL,'bills-normale-20260406-200640.xlsx','xlsx','/var/www/html/my-bills/storage/async/files/2026-04-06/a9132f4c7bb610a47df77c7ee17f068a-bills-normale-20260406-200640.xlsx','2026-04-06 20:06:40','2026-04-06 20:06:40','2026-04-06 18:06:40','2026-04-06 18:06:40'),
(4,'93cf334f6216d253f650bdb505311067',12,'pages/history/historic/export/normale.php','BILLS','failed',865562,'{\"scope\":\"selected\",\"export_type\":\"normale\",\"filters\":{\"group\":[],\"name\":[],\"data\":{\"all\":true,\"da\":\"2026-04-07\",\"a\":\"2026-04-07\"}},\"selected_ids\":[135,134,133,132,131]}','[]','[]','Invalid user.',NULL,NULL,NULL,'2026-04-07 18:07:44','2026-04-07 18:07:44','2026-04-07 16:07:44','2026-04-07 16:07:44');
/*!40000 ALTER TABLE `async_jobs` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `bills`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `bills` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `id_montly_bill` int(11) DEFAULT NULL,
  `id_group` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `value` decimal(12,2) NOT NULL DEFAULT 0.00,
  `date` date NOT NULL,
  `reference_start` date DEFAULT NULL,
  `reference_end` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_bills_id_group` (`id_group`),
  CONSTRAINT `fk_bills_group_id` FOREIGN KEY (`id_group`) REFERENCES `bills_groups` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=144 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `bills` WRITE;
/*!40000 ALTER TABLE `bills` DISABLE KEYS */;
INSERT INTO `bills` VALUES
(1,10,NULL,1,'Electricity March',124.90,'2026-03-15',NULL,NULL),
(2,10,NULL,2,'Streaming Annual Plan',89.99,'2026-02-01',NULL,NULL),
(3,10,NULL,3,'Car Insurance',312.50,'2026-01-20',NULL,NULL),
(8,10,NULL,7,'Pods',54.50,'2026-02-24',NULL,NULL),
(9,10,NULL,8,'Cofee and Cornetto',3.70,'2026-02-24',NULL,NULL),
(10,10,NULL,9,'Help',200.00,'2026-02-24',NULL,NULL),
(11,10,NULL,11,'Baiano',24.42,'2026-02-25',NULL,NULL),
(12,10,NULL,7,'Liquid',20.00,'2026-02-25',NULL,NULL),
(13,10,NULL,12,'Uniform Fix',60.00,'2026-02-26',NULL,NULL),
(14,10,NULL,12,'Game',30.99,'2026-02-26',NULL,NULL),
(15,10,NULL,7,'Liquid',20.00,'2026-02-26',NULL,NULL),
(16,10,NULL,12,'Game',5.99,'2026-02-26',NULL,NULL),
(69,10,1,6,'Home',300.00,'2026-03-24','2026-03-23','2026-04-23'),
(70,10,2,5,'Discovery+',2.89,'2026-03-24','2026-03-23','2026-04-23'),
(71,10,3,10,'Fastweb',39.50,'2026-03-25','2026-03-23','2026-04-23'),
(72,10,4,5,'JetBrains',24.28,'2026-03-25','2026-03-23','2026-04-23'),
(73,10,5,5,'Wakatime',10.99,'2026-03-26','2026-03-23','2026-04-23'),
(74,10,6,13,'Car Prima',96.60,'2026-03-26','2026-03-23','2026-04-23'),
(75,10,7,5,'Spotify',16.99,'2026-03-26','2026-03-23','2026-04-23'),
(76,10,8,5,'Crunchyroll',7.99,'2026-03-27','2026-03-23','2026-04-23'),
(77,10,9,5,'Prime',7.99,'2026-03-28','2026-03-23','2026-04-23'),
(78,10,10,12,'Game Warhammer',19.99,'2026-03-28','2026-03-23','2026-04-23'),
(79,10,11,5,'Prime',4.99,'2026-03-29','2026-03-23','2026-04-23'),
(80,10,12,5,'Hostinger KVM2',26.82,'2026-03-29','2026-03-23','2026-04-23'),
(81,10,13,13,'Bank',8.00,'2026-03-31','2026-03-23','2026-04-23'),
(82,10,14,13,'Scooter Prima',122.11,'2026-04-01','2026-03-23','2026-04-23'),
(83,10,15,5,'Netflix',16.99,'2026-04-21','2026-03-23','2026-04-23'),
(84,10,16,10,'Agos',62.79,'2026-04-20','2026-03-23','2026-04-23'),
(85,10,17,5,'ChatGPT',21.99,'2026-04-19','2026-03-23','2026-04-23'),
(86,10,18,5,'Disney+',15.99,'2026-04-18','2026-03-23','2026-04-23'),
(87,10,19,5,'Adobbe',9.85,'2026-04-16','2026-03-23','2026-04-23'),
(88,10,20,5,'DnD beyond',4.99,'2026-04-16','2026-03-23','2026-04-23'),
(89,10,21,5,'Hostinger KVM8',79.29,'2026-04-15','2026-03-23','2026-04-23'),
(90,10,22,5,'Dazn',42.49,'2026-04-13','2026-03-23','2026-04-23'),
(91,10,23,5,'Now',13.00,'2026-04-10','2026-03-23','2026-04-23'),
(92,10,24,13,'Bank',18.36,'2026-04-08','2026-03-23','2026-04-23'),
(93,10,26,9,'Mom',200.00,'2026-03-24','2026-03-23','2026-04-23'),
(94,10,NULL,11,'Food',11.79,'2026-04-06',NULL,NULL),
(95,10,NULL,12,'We work remotely',10.97,'2026-04-05',NULL,NULL),
(96,10,NULL,12,'Remotejobs.io',2.95,'2026-04-05',NULL,NULL),
(97,10,NULL,7,'Tabacco',3.50,'2026-04-02',NULL,NULL),
(98,10,NULL,7,'Tabacco',8.30,'2026-04-03',NULL,NULL),
(99,10,NULL,12,'Scooter documents',150.00,'2026-04-01',NULL,NULL),
(100,10,NULL,12,'Flight',107.49,'2026-04-01',NULL,NULL),
(101,10,NULL,8,'Thierry',2.50,'2026-03-31',NULL,NULL),
(102,10,NULL,15,'Fuel',60.00,'2026-03-31',NULL,NULL),
(103,10,NULL,11,'Food',60.72,'2026-03-31',NULL,NULL),
(104,10,NULL,8,'Sigma',5.00,'2026-03-30',NULL,NULL),
(105,10,NULL,12,'Gynecologist',200.00,'2026-03-30',NULL,NULL),
(106,10,NULL,12,'Museo',30.00,'2026-03-29',NULL,NULL),
(107,10,NULL,12,'Toll (Pedaggio)',1.05,'2026-03-29',NULL,NULL),
(108,10,NULL,12,'Pappu',10.00,'2026-03-29',NULL,NULL),
(109,10,NULL,12,'Pappu',1.05,'2026-03-29',NULL,NULL),
(110,10,NULL,12,'Pappu',17.50,'2026-03-29',NULL,NULL),
(111,10,NULL,7,'Wiston',5.80,'2026-03-29',NULL,NULL),
(112,10,NULL,12,'Carwash',15.00,'2026-03-28',NULL,NULL),
(113,10,NULL,8,'Erika',15.00,'2026-03-28',NULL,NULL),
(114,10,NULL,11,'Food',13.97,'2026-03-28',NULL,NULL),
(115,10,NULL,11,'Food',6.28,'2026-03-28',NULL,NULL),
(116,10,NULL,7,'Svapo',21.50,'2026-03-27',NULL,NULL),
(117,10,NULL,8,'Thierry',9.50,'2026-03-27',NULL,NULL),
(118,10,NULL,12,'Minoxidil',19.00,'2026-03-27',NULL,NULL),
(119,10,NULL,7,'Svapo',20.00,'2026-03-26',NULL,NULL),
(120,10,NULL,12,'MBL',30.99,'2026-03-26',NULL,NULL),
(121,10,NULL,12,'Clothes fix',60.00,'2026-03-26',NULL,NULL),
(122,10,NULL,7,'Svapo',20.00,'2026-03-25',NULL,NULL),
(123,10,NULL,11,'Food',24.42,'2026-03-25',NULL,NULL),
(124,10,NULL,11,'Food',7.20,'2026-03-25',NULL,NULL),
(125,10,NULL,8,'Thierry',3.70,'2026-03-24',NULL,NULL),
(126,10,NULL,7,'Svapo',54.50,'2026-03-24',NULL,NULL),
(127,10,NULL,11,'Food',9.00,'2026-03-23',NULL,NULL),
(128,10,NULL,11,'Food',4.99,'2026-03-23',NULL,NULL),
(129,10,NULL,7,'Wiston',5.88,'2026-03-23',NULL,NULL),
(130,12,NULL,16,'test1',100.00,'2026-04-07',NULL,NULL),
(131,12,NULL,16,'test2',20.00,'2026-04-07',NULL,NULL),
(132,12,NULL,16,'test3',30.00,'2026-04-07',NULL,NULL),
(133,12,NULL,16,'test4',10.00,'2026-04-08',NULL,NULL),
(134,12,NULL,16,'test6',30.00,'2026-04-09',NULL,NULL),
(135,12,NULL,16,'test8',80.00,'2026-04-11',NULL,NULL),
(136,10,NULL,14,'Minoxidil',34.00,'2026-04-07',NULL,NULL),
(137,10,NULL,7,'Svapo',22.00,'2026-04-07',NULL,NULL),
(138,10,NULL,15,'Fuel',11.57,'2026-04-09',NULL,NULL),
(139,10,NULL,8,'Thierry',2.50,'2026-04-10',NULL,NULL),
(140,10,NULL,8,'Thierry',2.48,'2026-04-07',NULL,NULL),
(141,10,NULL,5,'Prime',5.49,'2026-04-11',NULL,NULL),
(142,10,27,5,'Remote jobs finder',21.84,'2026-04-12','2026-03-23','2026-04-23'),
(143,10,NULL,8,'Sigma',5.00,'2026-04-13',NULL,NULL);
/*!40000 ALTER TABLE `bills` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `bills_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `bills_groups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `name` varchar(120) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_bills_groups_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `bills_groups` WRITE;
/*!40000 ALTER TABLE `bills_groups` DISABLE KEYS */;
INSERT INTO `bills_groups` VALUES
(1,10,'Utilities'),
(2,10,'Subscriptions'),
(3,10,'Insurance'),
(5,10,'Apps'),
(6,10,'Home'),
(7,10,'Cigarettes'),
(8,10,'Breakfast'),
(9,10,'Mom'),
(10,10,'Bill'),
(11,10,'Food'),
(12,10,'Extra'),
(13,10,'Ensurance'),
(14,10,'Pharmacy'),
(15,10,'Fuel'),
(16,12,'test');
/*!40000 ALTER TABLE `bills_groups` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `extra_incomes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `extra_incomes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `value` decimal(12,2) NOT NULL DEFAULT 0.00,
  `date` date NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_extra_incomes_user_id` (`user_id`),
  KEY `idx_extra_incomes_date` (`date`),
  CONSTRAINT `fk_extra_incomes_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `extra_incomes` WRITE;
/*!40000 ALTER TABLE `extra_incomes` DISABLE KEYS */;
INSERT INTO `extra_incomes` VALUES
(1,10,'March Savings',56.73,'2026-03-24');
/*!40000 ALTER TABLE `extra_incomes` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `incoming`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `incoming` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `value` decimal(12,2) NOT NULL DEFAULT 0.00,
  `day` tinyint(2) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_incoming_day` (`day`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `incoming` WRITE;
/*!40000 ALTER TABLE `incoming` DISABLE KEYS */;
INSERT INTO `incoming` VALUES
(1,10,2200.00,23),
(2,12,1000.00,7);
/*!40000 ALTER TABLE `incoming` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `montly_bills`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `montly_bills` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `id_group` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `value` decimal(12,2) NOT NULL DEFAULT 0.00,
  `day` tinyint(2) unsigned NOT NULL,
  `first_date` date DEFAULT NULL,
  `last_date` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_montly_bills_id_group` (`id_group`),
  KEY `idx_montly_bills_day` (`day`),
  CONSTRAINT `fk_montly_bills_group_id` FOREIGN KEY (`id_group`) REFERENCES `bills_groups` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `chk_montly_bills_day` CHECK (`day` between 1 and 31)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `montly_bills` WRITE;
/*!40000 ALTER TABLE `montly_bills` DISABLE KEYS */;
INSERT INTO `montly_bills` VALUES
(1,10,6,'Home',300.00,24,NULL,NULL),
(2,10,5,'Discovery+',2.89,24,NULL,NULL),
(3,10,10,'Fastweb',39.50,25,NULL,NULL),
(4,10,5,'JetBrains',24.28,25,NULL,'2026-04-22'),
(5,10,5,'Wakatime',10.99,26,NULL,NULL),
(6,10,13,'Car Prima',96.60,26,'2025-07-31','2026-07-31'),
(7,10,5,'Spotify',16.99,26,NULL,NULL),
(8,10,5,'Crunchyroll',7.99,27,NULL,NULL),
(9,10,5,'Prime',7.99,28,NULL,NULL),
(10,10,12,'Game Warhammer',19.99,28,'2026-02-28','2026-04-28'),
(11,10,5,'Prime',4.99,29,NULL,NULL),
(12,10,5,'Hostinger KVM2',26.82,29,NULL,NULL),
(13,10,13,'Bank',8.00,31,NULL,NULL),
(14,10,13,'Scooter Prima',122.11,1,'2026-04-01','2027-04-01'),
(15,10,5,'Netflix',16.99,21,NULL,NULL),
(16,10,10,'Agos',62.79,20,NULL,'2027-04-30'),
(17,10,5,'ChatGPT',21.99,19,NULL,NULL),
(18,10,5,'Disney+',15.99,18,NULL,NULL),
(19,10,5,'Adobbe',9.85,16,NULL,NULL),
(20,10,5,'DnD beyond',4.99,16,NULL,NULL),
(21,10,5,'Hostinger KVM8',79.29,15,NULL,'2026-04-22'),
(22,10,5,'Dazn',42.49,13,NULL,NULL),
(23,10,5,'Now',13.00,10,NULL,NULL),
(24,10,13,'Bank',18.36,8,NULL,NULL),
(25,10,10,'Scooter',101.50,1,'2026-05-01','2028-03-01'),
(26,10,9,'Mom',200.00,24,NULL,NULL),
(27,10,5,'Remote jobs finder',21.84,12,NULL,NULL);
/*!40000 ALTER TABLE `montly_bills` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_password_reset_token_hash` (`token_hash`),
  KEY `idx_password_reset_user_id` (`user_id`),
  CONSTRAINT `fk_password_reset_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `password_reset_tokens` WRITE;
/*!40000 ALTER TABLE `password_reset_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_reset_tokens` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `user_preferences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_preferences` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `scope` varchar(100) NOT NULL DEFAULT 'global',
  `preference_key` varchar(60) NOT NULL,
  `preference_value` longtext NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_user_scope_key` (`user_id`,`scope`,`preference_key`),
  KEY `idx_user_preferences_user_id` (`user_id`),
  CONSTRAINT `fk_user_preferences_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=663 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `user_preferences` WRITE;
/*!40000 ALTER TABLE `user_preferences` DISABLE KEYS */;
INSERT INTO `user_preferences` VALUES
(1,10,'global','theme','dark','2026-04-06 12:01:10','2026-04-07 20:37:43'),
(2,10,'global','planning_colors',':root{--base-bg-colloquio:#aad5d8;--base-bg-seduta:#56ebf7;--base-bg-corso:#32ffbb;--base-bg-sbarra:#5d7fff;}','2026-04-06 12:01:10','2026-04-06 21:05:45'),
(3,10,'page:history','rows_per_page','100','2026-04-06 12:38:24','2026-04-15 12:11:06'),
(4,10,'page:history','table_font_size','14','2026-04-06 12:38:24','2026-04-15 12:11:06'),
(70,10,'page:montly','rows_per_page','200','2026-04-06 14:55:22','2026-04-15 12:10:49'),
(71,10,'page:montly','table_font_size','15','2026-04-06 14:55:22','2026-04-15 12:10:49'),
(481,10,'page:history:extra_income','rows_per_page','100','2026-04-06 20:14:23','2026-04-06 20:14:23'),
(482,10,'page:history:extra_income','table_font_size','14','2026-04-06 20:14:23','2026-04-06 20:14:23'),
(509,12,'global','theme','dark','2026-04-07 16:04:59','2026-04-11 07:30:38'),
(511,12,'page:history','rows_per_page','10','2026-04-07 16:06:30','2026-04-11 07:30:29'),
(512,12,'page:history','table_font_size','18','2026-04-07 16:06:30','2026-04-11 07:30:29'),
(604,12,'page:montly','rows_per_page','20','2026-04-11 07:30:11','2026-04-11 07:30:11'),
(605,12,'page:montly','table_font_size','12','2026-04-11 07:30:11','2026-04-11 07:30:11');
/*!40000 ALTER TABLE `user_preferences` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(60) NOT NULL,
  `password_hash` longtext NOT NULL,
  `full_name` varchar(60) NOT NULL,
  `email` varchar(100) NOT NULL,
  `google_id` varchar(191) DEFAULT NULL,
  `token` varchar(64) DEFAULT NULL,
  `token_expires_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_users_username` (`username`),
  UNIQUE KEY `uniq_users_email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES
(10,'teo','$2y$10$ga5hSdkGezQmiYPCohMDke5cWfZfef/U8O2vY3ANh9qVGuZf0xMAW','Teo','teodublin@gmail.com',NULL,'9b914fa148cae7f5a36973c1e985667f39e4e9c0c6b0e6f4e88da697417258e3','2026-04-16 06:00:00','2026-04-06 12:01:10','2026-04-15 12:08:16'),
(12,'paolo','$2y$10$ga5hSdkGezQmiYPCohMDke5cWfZfef/U8O2vY3ANh9qVGuZf0xMAW','Teo','paolo@gmail.com',NULL,'ab2b0aafb92b11005a2b48897eeef3b422b13920cc182e9cf8c58df968cf43a7','2026-04-12 06:00:00','2026-04-06 12:01:10','2026-04-11 07:30:07');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
DROP TABLE IF EXISTS `view_bills`;
/*!50001 DROP VIEW IF EXISTS `view_bills`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `view_bills` AS SELECT
 1 AS `id`,
  1 AS `user_id`,
  1 AS `id_montly_bill`,
  1 AS `id_group`,
  1 AS `name`,
  1 AS `value`,
  1 AS `date`,
  1 AS `reference_start`,
  1 AS `reference_end`,
  1 AS `group_name` */;
SET character_set_client = @saved_cs_client;
DROP TABLE IF EXISTS `view_montly_bills`;
/*!50001 DROP VIEW IF EXISTS `view_montly_bills`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8mb4;
/*!50001 CREATE VIEW `view_montly_bills` AS SELECT
 1 AS `id`,
  1 AS `user_id`,
  1 AS `id_group`,
  1 AS `name`,
  1 AS `value`,
  1 AS `day`,
  1 AS `first_date`,
  1 AS `last_date`,
  1 AS `group_name` */;
SET character_set_client = @saved_cs_client;
/*!50001 DROP VIEW IF EXISTS `view_bills`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb3 */;
/*!50001 SET character_set_results     = utf8mb3 */;
/*!50001 SET collation_connection      = utf8mb3_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`teo`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `view_bills` AS select `b`.`id` AS `id`,`b`.`user_id` AS `user_id`,`b`.`id_montly_bill` AS `id_montly_bill`,`b`.`id_group` AS `id_group`,`b`.`name` AS `name`,`b`.`value` AS `value`,`b`.`date` AS `date`,`b`.`reference_start` AS `reference_start`,`b`.`reference_end` AS `reference_end`,`bg`.`name` AS `group_name` from (`bills` `b` left join `bills_groups` `bg` on(`bg`.`id` = `b`.`id_group` and `bg`.`user_id` = `b`.`user_id`)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!50001 DROP VIEW IF EXISTS `view_montly_bills`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb3 */;
/*!50001 SET character_set_results     = utf8mb3 */;
/*!50001 SET collation_connection      = utf8mb3_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`teo`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `view_montly_bills` AS select `mb`.`id` AS `id`,`mb`.`user_id` AS `user_id`,`mb`.`id_group` AS `id_group`,`mb`.`name` AS `name`,`mb`.`value` AS `value`,`mb`.`day` AS `day`,`mb`.`first_date` AS `first_date`,`mb`.`last_date` AS `last_date`,`bg`.`name` AS `group_name` from (`montly_bills` `mb` left join `bills_groups` `bg` on(`bg`.`id` = `mb`.`id_group` and `bg`.`user_id` = `mb`.`user_id`)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

