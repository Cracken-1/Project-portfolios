CREATE DATABASE  IF NOT EXISTS `hms_db` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci */;
USE `hms_db`;
-- MySQL dump 10.13  Distrib 8.0.42, for Win64 (x86_64)
--
-- Host: 127.0.0.1    Database: hms_db
-- ------------------------------------------------------
-- Server version	5.5.5-10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `admin`
--

DROP TABLE IF EXISTS `admin`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin` (
  `adminid` int(11) NOT NULL AUTO_INCREMENT,
  `adminname` varchar(100) NOT NULL,
  `adminage` int(11) NOT NULL,
  `admingender` varchar(10) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  PRIMARY KEY (`adminid`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admin`
--

LOCK TABLES `admin` WRITE;
/*!40000 ALTER TABLE `admin` DISABLE KEYS */;
INSERT INTO `admin` VALUES (3,'Grace Gicheha',22,'Female','Grace','$2y$10$4GBC2S4zHgrVTLMYkJ7RjuF1Jg2bJewq8lcaNit.sLN5TorzWfPWy');
/*!40000 ALTER TABLE `admin` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `appointment`
--

DROP TABLE IF EXISTS `appointment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `appointment` (
  `appoid` int(11) NOT NULL AUTO_INCREMENT,
  `pid` int(11) NOT NULL,
  `docid` int(11) NOT NULL,
  `appodate` date NOT NULL,
  `appotime` time NOT NULL,
  `diagnosis` text DEFAULT NULL,
  `prescription` text DEFAULT NULL,
  `status` enum('Scheduled','Completed','Cancelled') DEFAULT 'Scheduled',
  `reason` varchar(255) DEFAULT NULL,
  `appointment_type` varchar(50) DEFAULT NULL,
  `pphone` varchar(20) DEFAULT NULL,
  `pdob` date DEFAULT NULL,
  `appodesc` text DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`appoid`),
  KEY `pid` (`pid`),
  KEY `docid` (`docid`),
  CONSTRAINT `appointment_ibfk_1` FOREIGN KEY (`pid`) REFERENCES `patient` (`pid`) ON DELETE CASCADE,
  CONSTRAINT `appointment_ibfk_2` FOREIGN KEY (`docid`) REFERENCES `doctor` (`docid`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `appointment`
--

LOCK TABLES `appointment` WRITE;
/*!40000 ALTER TABLE `appointment` DISABLE KEYS */;
INSERT INTO `appointment` VALUES (5,3,4,'2025-05-10','10:00:00',NULL,NULL,'Completed','Headache, Loss of Appetite, Pain in the Joints',NULL,NULL,NULL,NULL,NULL),(6,7,5,'2025-04-30','00:50:00',NULL,NULL,'Completed',NULL,NULL,NULL,NULL,NULL,NULL),(7,6,5,'2025-04-30','08:30:00',NULL,NULL,'Completed',NULL,NULL,NULL,NULL,NULL,NULL),(8,5,5,'2025-04-29','16:45:00',NULL,NULL,'Completed',NULL,NULL,NULL,NULL,NULL,NULL),(9,3,5,'2025-04-30','13:20:00',NULL,NULL,'Completed','Tooth ache',NULL,NULL,NULL,NULL,NULL),(11,3,6,'2025-05-01','13:16:00',NULL,NULL,'Completed','Chest pain',NULL,NULL,NULL,NULL,NULL),(12,8,5,'2025-05-02','17:40:00',NULL,NULL,'Completed','Toothache',NULL,NULL,NULL,NULL,NULL),(13,11,6,'2025-05-10','09:30:00',NULL,NULL,'Completed','Character development',NULL,NULL,NULL,NULL,NULL),(15,13,6,'2025-05-06','13:10:00',NULL,NULL,'Completed','Chest pain',NULL,NULL,NULL,NULL,NULL);
/*!40000 ALTER TABLE `appointment` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `communication_logs`
--

DROP TABLE IF EXISTS `communication_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `communication_logs` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `communication_type` enum('chat','video_call','audio_call') NOT NULL,
  `sender_type` enum('a','d','p') NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_type` enum('a','d','p') NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `message_content` text DEFAULT NULL,
  `session_details` text DEFAULT NULL,
  `urgent` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`log_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `communication_logs`
--

LOCK TABLES `communication_logs` WRITE;
/*!40000 ALTER TABLE `communication_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `communication_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `doctor`
--

DROP TABLE IF EXISTS `doctor`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `doctor` (
  `docid` int(11) NOT NULL AUTO_INCREMENT,
  `docname` varchar(100) NOT NULL,
  `docemail` varchar(100) NOT NULL,
  `docage` int(11) NOT NULL,
  `docgender` varchar(10) NOT NULL,
  `specialization` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  PRIMARY KEY (`docid`),
  UNIQUE KEY `docemail` (`docemail`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `doctor`
--

LOCK TABLES `doctor` WRITE;
/*!40000 ALTER TABLE `doctor` DISABLE KEYS */;
INSERT INTO `doctor` VALUES (4,'Mary Makau','makau@gmail.com',35,'Female','General Health','$2y$10$HKB3p6/TkL3/bhrudAudv.OfXe74AwUCkf8/85FQ3W.CgOPwRoJK2'),(5,'David Mungamu','mungamu@gmail.com',43,'Male','Dentist','$2y$10$vhTFR2c8tySfx1pfdwIsI.ze83wj2WVIQ4rFZNBUz04vrdMtFBhp.'),(6,'Michael Kamau','michaelkamau@gmail.com',30,'Male','Cardiology','$2y$10$CTGVV0JKLmrBQ/M5ahN4huAMlxryiscJZUiPuFWW3Hm7xTXndTPqK');
/*!40000 ALTER TABLE `doctor` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `doctor_schedules`
--

DROP TABLE IF EXISTS `doctor_schedules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `doctor_schedules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `doctor_id` int(11) NOT NULL,
  `day_of_week` varchar(10) NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `max_appointments` int(11) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `doctor_id` (`doctor_id`),
  CONSTRAINT `doctor_schedules_ibfk_1` FOREIGN KEY (`doctor_id`) REFERENCES `doctor` (`docid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `doctor_schedules`
--

LOCK TABLES `doctor_schedules` WRITE;
/*!40000 ALTER TABLE `doctor_schedules` DISABLE KEYS */;
/*!40000 ALTER TABLE `doctor_schedules` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `equipment_inventory`
--

DROP TABLE IF EXISTS `equipment_inventory`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `equipment_inventory` (
  `equipment_id` int(11) NOT NULL AUTO_INCREMENT,
  `equipment_name` varchar(100) NOT NULL,
  `location` varchar(50) DEFAULT NULL,
  `last_maintenance_date` date DEFAULT NULL,
  `next_maintenance_date` date DEFAULT NULL,
  `status` varchar(30) DEFAULT NULL,
  PRIMARY KEY (`equipment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `equipment_inventory`
--

LOCK TABLES `equipment_inventory` WRITE;
/*!40000 ALTER TABLE `equipment_inventory` DISABLE KEYS */;
/*!40000 ALTER TABLE `equipment_inventory` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `invoice_items`
--

DROP TABLE IF EXISTS `invoice_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `invoice_items` (
  `item_id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_id` int(11) NOT NULL,
  `description` varchar(255) NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`item_id`),
  KEY `invoice_id` (`invoice_id`),
  CONSTRAINT `invoice_items_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`invoice_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `invoice_items`
--

LOCK TABLES `invoice_items` WRITE;
/*!40000 ALTER TABLE `invoice_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `invoice_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `invoice_versions`
--

DROP TABLE IF EXISTS `invoice_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `invoice_versions` (
  `version_id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_id` int(11) NOT NULL,
  `version_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`version_data`)),
  `edited_by` int(11) NOT NULL,
  `edited_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`version_id`),
  KEY `invoice_id` (`invoice_id`),
  CONSTRAINT `invoice_versions_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`invoice_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `invoice_versions`
--

LOCK TABLES `invoice_versions` WRITE;
/*!40000 ALTER TABLE `invoice_versions` DISABLE KEYS */;
/*!40000 ALTER TABLE `invoice_versions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `invoices`
--

DROP TABLE IF EXISTS `invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `invoices` (
  `invoice_id` int(11) NOT NULL AUTO_INCREMENT,
  `appoid` int(11) NOT NULL,
  `invoice_number` varchar(20) NOT NULL,
  `invoice_date` date NOT NULL,
  `due_date` date NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `tax_amount` decimal(10,2) DEFAULT 0.00,
  `discount_amount` decimal(10,2) DEFAULT 0.00,
  `total_amount` decimal(10,2) NOT NULL,
  `status` enum('paid','pending','cancelled') DEFAULT 'pending',
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `edited_by` int(11) DEFAULT NULL,
  `pphoneno` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`invoice_id`),
  UNIQUE KEY `invoice_number` (`invoice_number`),
  KEY `appoid` (`appoid`),
  CONSTRAINT `invoices_ibfk_1` FOREIGN KEY (`appoid`) REFERENCES `appointment` (`appoid`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `invoices`
--

LOCK TABLES `invoices` WRITE;
/*!40000 ALTER TABLE `invoices` DISABLE KEYS */;
INSERT INTO `invoices` VALUES (8,5,'INV-20250507-58D28F','2025-05-07','2025-05-14',0.00,0.00,0.00,0.00,'pending',NULL,NULL,'','2025-05-07 13:59:49','2025-05-07 13:59:49',4,NULL);
/*!40000 ALTER TABLE `invoices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `medical_record`
--

DROP TABLE IF EXISTS `medical_record`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `medical_record` (
  `recordid` int(11) NOT NULL AUTO_INCREMENT,
  `pid` int(11) NOT NULL,
  `docid` int(11) NOT NULL,
  `diagnosis` text NOT NULL,
  `prescription` text DEFAULT NULL,
  `recorddate` date NOT NULL,
  PRIMARY KEY (`recordid`),
  KEY `pid` (`pid`),
  KEY `docid` (`docid`),
  CONSTRAINT `medical_record_ibfk_1` FOREIGN KEY (`pid`) REFERENCES `patient` (`pid`) ON DELETE CASCADE,
  CONSTRAINT `medical_record_ibfk_2` FOREIGN KEY (`docid`) REFERENCES `doctor` (`docid`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `medical_record`
--

LOCK TABLES `medical_record` WRITE;
/*!40000 ALTER TABLE `medical_record` DISABLE KEYS */;
/*!40000 ALTER TABLE `medical_record` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `medicine_inventory`
--

DROP TABLE IF EXISTS `medicine_inventory`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `medicine_inventory` (
  `medicine_id` int(11) NOT NULL AUTO_INCREMENT,
  `medicine_name` varchar(100) NOT NULL,
  `category` varchar(50) DEFAULT NULL,
  `current_stock` int(11) NOT NULL,
  `reorder_level` int(11) NOT NULL,
  `unit` varchar(20) DEFAULT NULL,
  `supplier` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`medicine_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `medicine_inventory`
--

LOCK TABLES `medicine_inventory` WRITE;
/*!40000 ALTER TABLE `medicine_inventory` DISABLE KEYS */;
/*!40000 ALTER TABLE `medicine_inventory` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `patient`
--

DROP TABLE IF EXISTS `patient`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `patient` (
  `pid` int(11) NOT NULL AUTO_INCREMENT,
  `pname` varchar(100) NOT NULL,
  `pemail` varchar(100) NOT NULL,
  `pphoneno` varchar(20) DEFAULT NULL,
  `registered_date` datetime DEFAULT current_timestamp(),
  `page` int(11) NOT NULL,
  `pgender` varchar(10) NOT NULL,
  `password` varchar(255) NOT NULL,
  `pdob` date DEFAULT NULL,
  PRIMARY KEY (`pid`),
  UNIQUE KEY `pemail` (`pemail`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `patient`
--

LOCK TABLES `patient` WRITE;
/*!40000 ALTER TABLE `patient` DISABLE KEYS */;
INSERT INTO `patient` VALUES (3,'James Mungai','mungai@gmail.com',NULL,'2025-04-30 12:43:26',23,'male','$2y$10$jrIZ6m2RfEOjgKhzyBFZq.TfVYQ/dITkAe10uu/9uRFF2nK8DiHeC',NULL),(4,'Alicia Wangari','0','0727497815','2025-04-30 12:43:26',19,'Female','$2y$10$h4I6H5Hw6v3ePuDCG6xN9OmA2SQpdgN2YXKjUbfQ0mS3k.nt06zJO',NULL),(5,'Steve Wafula','wafula@gmail.com',NULL,'2025-04-30 12:43:26',30,'Male','$2y$10$m5CcNU9/kPonWiGUyIb1NuQ0.hKzaXjiyQhNIpgKWrzb90pGzfHx6',NULL),(6,'Keysha Omollo','keysha@gmail.com',NULL,'2025-04-30 12:43:26',21,'Female','$2y$10$xTf6FVACIo6LJNApV8dege5LBr.ftiIJfX3lrC.JI.Hu2W67YonHq',NULL),(7,'Shawn Mwangima','shawn@gmail.com',NULL,'2025-04-30 12:43:26',46,'Male','$2y$10$IWja7DB6CB9aupzVybI79eQlyr4sZ9ZzF3TnljwGLCKPYwszy7aSq',NULL),(8,'Noelle Okato','okato@gmail.com',NULL,'2025-04-30 12:43:26',34,'female','$2y$10$C1LQquFYIgVtFc9C0eppxusamNXY3b.zvtBVYfhuvJ/tGV0fogHUq',NULL),(9,'Joseph Ole Ntirani','joseph@gmail.com','0712345678','2025-05-02 10:56:44',56,'Male','$2y$10$xVbfxM1nuT0FT6/2PYT0kusPMo18qwYF2SritNwYFQI7EiAIC6wei',NULL),(10,'Janet Kamau','janet@gmail.com','0798765432','2025-05-02 11:08:25',24,'Female','$2y$10$mm/V6SkXbaUpv4lflStl5OmYLIk/0Z48SHlyxA7PPXhwk8gHO.epC',NULL),(11,'Julia Wayua','julia@gmail.com','0106533200','2025-05-02 19:30:41',22,'female','$2y$10$LFc0PS0q38q.7Vc6B8LSBu5CWz5dQL6gIjlX8RCK5L5Yx2k5aO1yC',NULL),(12,'Janet Awuor','janet50@gmail.com','0756879248','2025-05-05 11:23:20',28,'Female','$2y$10$kF3MX9OK15FbcqDRO1YN3.rLIaEmbDmc3veOjQ9veUABsg1eZI1v2',NULL),(13,'Janet Wambui','wambuijanet@gmail.com','0754876953','2025-05-05 12:09:17',25,'Female','$2y$10$hfke/DPRL07.cXYwNtMlWeXLSBqZalqP4QQlXgm0Ps2ILtcW1lL52',NULL);
/*!40000 ALTER TABLE `patient` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `prescribed_medicines`
--

DROP TABLE IF EXISTS `prescribed_medicines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `prescribed_medicines` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `prescription_id` int(11) NOT NULL,
  `medicine_name` varchar(100) NOT NULL,
  `quantity` int(11) NOT NULL,
  `dosage` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `prescription_id` (`prescription_id`),
  CONSTRAINT `prescribed_medicines_ibfk_1` FOREIGN KEY (`prescription_id`) REFERENCES `prescription` (`prescription_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `prescribed_medicines`
--

LOCK TABLES `prescribed_medicines` WRITE;
/*!40000 ALTER TABLE `prescribed_medicines` DISABLE KEYS */;
/*!40000 ALTER TABLE `prescribed_medicines` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `prescription`
--

DROP TABLE IF EXISTS `prescription`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `prescription` (
  `prescription_id` int(11) NOT NULL AUTO_INCREMENT,
  `appoid` int(11) NOT NULL,
  `prescription_date` datetime DEFAULT current_timestamp(),
  `medication` text NOT NULL,
  `instructions` text DEFAULT NULL,
  `refills_remaining` int(11) DEFAULT 0,
  PRIMARY KEY (`prescription_id`),
  KEY `appoid` (`appoid`),
  CONSTRAINT `prescription_ibfk_1` FOREIGN KEY (`appoid`) REFERENCES `appointment` (`appoid`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `prescription`
--

LOCK TABLES `prescription` WRITE;
/*!40000 ALTER TABLE `prescription` DISABLE KEYS */;
INSERT INTO `prescription` VALUES (2,7,'2025-04-30 11:57:53','Neuroviral Fatigue Syndrome (NFS),\"\r\nNeurovexin 200mg, taken twice daily for 10 days, along with Vitalamin Complex','Take Neurovexin with food to avoid stomach upset, spacing doses 12 hours apart. Do not skip doses, and complete the full course even if symptoms improve early. Vitalamin should be taken in the morning with a full glass of water. Avoid caffeine and alcohol during treatment, and report any dizziness, tingling, or allergic reactions immediately.',0),(3,6,'2025-04-30 11:58:57','espiraline Stress Disorder (RSD),\r\nRespiracalm 5mg tablets, take once in the evening to reduce airway tension and ease stress-induced respiratory symptoms. Additionally, Zencorine Syrup (15ml).','Take Respiracalm exactly at the same time each evening, preferably after dinner. Do not operate machinery or drive after taking the dose, as drowsiness may occur. Shake Zencorine Syrup well before use and measure with a dosing cup. Avoid cold drinks and exposure to dust or smoke during the treatment period. Follow up after one week to assess improvement and adjust the dosage if necessary.',0),(4,8,'2025-04-30 11:59:43','Neurotempic Fatigue Syndrome (NFS)\r\nNeurovite XR 150mg capsules, to be taken once every morning to enhance cognitive stamina and reduce neural fatigue. \r\nRevitex Solution (10ml)','Swallow Neurovite XR with a full glass of water, ideally 30 minutes before breakfast, to maximize absorption. Do not skip doses, and avoid caffeine or other stimulants during the treatment. Revitex Solution must be taken at 8-hour intervals—morning, afternoon, and evening—with or without food. Maintain a balanced diet, get adequate sleep, and minimize screen exposure to support the effectiveness of the medications. Reassess in 10 days for follow-up evaluation.',0),(5,9,'2025-04-30 12:16:53','Paracetamol 0.5g','Take with water and limit screentime',2),(6,11,'2025-05-01 13:21:02','Ibuprufen 0.5 mg','take with water',0),(7,12,'2025-05-02 17:38:26','Ibuprufen 0.5mg','Take before sleeping',0),(9,15,'2025-05-05 12:15:03','Amoxyl 500mg','1x3',0);
/*!40000 ALTER TABLE `prescription` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `prescription_refills`
--

DROP TABLE IF EXISTS `prescription_refills`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `prescription_refills` (
  `refill_id` int(11) NOT NULL AUTO_INCREMENT,
  `prescription_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `request_date` datetime NOT NULL,
  `processed_date` datetime DEFAULT NULL,
  `status` enum('pending','approved','denied') DEFAULT 'pending',
  `request_notes` text DEFAULT NULL,
  `refill_quantity` int(11) DEFAULT 1,
  `processed_notes` text DEFAULT NULL,
  `processed_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`refill_id`),
  KEY `prescription_id` (`prescription_id`),
  KEY `patient_id` (`patient_id`),
  KEY `processed_by` (`processed_by`),
  CONSTRAINT `prescription_refills_ibfk_1` FOREIGN KEY (`prescription_id`) REFERENCES `prescription` (`prescription_id`),
  CONSTRAINT `prescription_refills_ibfk_2` FOREIGN KEY (`patient_id`) REFERENCES `patient` (`pid`),
  CONSTRAINT `prescription_refills_ibfk_3` FOREIGN KEY (`processed_by`) REFERENCES `doctor` (`docid`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `prescription_refills`
--

LOCK TABLES `prescription_refills` WRITE;
/*!40000 ALTER TABLE `prescription_refills` DISABLE KEYS */;
INSERT INTO `prescription_refills` VALUES (1,5,3,'2025-05-01 11:41:44','2025-05-02 21:28:18','approved','',1,NULL,5),(2,5,3,'2025-05-02 17:19:58','2025-05-02 21:28:13','approved','',1,NULL,5);
/*!40000 ALTER TABLE `prescription_refills` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `schedule`
--

DROP TABLE IF EXISTS `schedule`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `schedule` (
  `scheduleid` int(11) NOT NULL AUTO_INCREMENT,
  `docid` int(11) NOT NULL,
  `scheduledate` date NOT NULL,
  `scheduletime` time NOT NULL,
  `title` varchar(100) NOT NULL,
  `duration` int(11) NOT NULL,
  `description` text NOT NULL,
  PRIMARY KEY (`scheduleid`),
  KEY `docid` (`docid`),
  CONSTRAINT `schedule_ibfk_1` FOREIGN KEY (`docid`) REFERENCES `doctor` (`docid`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `schedule`
--

LOCK TABLES `schedule` WRITE;
/*!40000 ALTER TABLE `schedule` DISABLE KEYS */;
INSERT INTO `schedule` VALUES (3,5,'2025-04-30','08:30:00','Keysha Omollo Routine Visit',30,'Lab session with Keysha Omollo');
/*!40000 ALTER TABLE `schedule` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-05-08 18:42:45
