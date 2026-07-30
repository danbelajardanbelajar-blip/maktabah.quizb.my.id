-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Jul 26, 2026 at 08:42 PM
-- Server version: 11.4.12-MariaDB-cll-lve
-- PHP Version: 8.4.23

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `quic1934_maktabah`
--

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `catord` int(11) DEFAULT 0,
  `lvl` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `catord`, `lvl`) VALUES
(0, 'علوم أخرى', 32, 1),
(1, 'مؤلفات الإمام الحداد', 29, 0),
(2, 'شروح الحديث', 22, 0),
(3, 'مؤلفات الإمام الغزالي', 17, 0),
(4, 'علوم القرأن', 31, 0),
(5, 'كتب اللغة و الأدب', 26, 0),
(6, 'الفتاوي', 10, 0),
(7, 'مؤلفات الإمام البيهقي', 28, 0),
(8, 'القفه المقارن', 11, 0),
(9, 'Hasil Bahtsu Masa\'il', 4, 0),
(10, 'كتب النحو و الصرف', 27, 0),
(11, 'كتب الأخلاق والرقاق و التصوف', 24, 0),
(13, 'Hasil FMPP', 1, 0),
(14, 'العقيــدة و علم الكلام', 20, 0),
(15, 'كتب في الطب والتداوي', 30, 0),
(16, 'BUKU IBARAT', 0, 0),
(17, 'كتب الأدعية و الأذكار', 25, 0),
(18, 'Rumusan BMK', 6, 0),
(19, 'متون الحديث', 21, 0),
(20, 'MAKALAH ISLAMI', 8, 0),
(21, 'Piss-Ktb 2015', 5, 0),
(22, 'مؤلفات الحافظ السيوطي', 18, 0),
(23, 'BM-PBNU,PWNU,PCNU', 3, 0),
(24, 'كتب أهل السنة والجماعة', 12, 0),
(25, 'Maktabah Al-Anwar Sarang', 7, 0),
(26, 'Hasil FMP3', 2, 0),
(28, 'التفسير', 19, 0),
(35, 'كتب فقه المذهب الحنفي', 13, 0),
(38, 'كتب فقه المذهب المالكي', 14, 0),
(39, 'كتب فقه المذهب الشافعي', 9, 0),
(41, 'كتب فقه المذهب الحنبلي', 15, 0),
(43, 'فقــه عـام و فتـاوى', 16, 0),
(47, 'أصول الفقه وفواعده', 23, 0);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_catord` (`catord`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
