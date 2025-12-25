-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3309


create databse file_system ;
use file_system ; 

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `student_code` varchar(50) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `group_number` varchar(20) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `student_code`, `full_name`, `group_number`, `created_at`) VALUES
(1, '2222', 'Farid Muradov', '1023', '2025-02-11 19:30:11'),
(2, 'C111', 'Cavad Huseynli', '1023', '2025-02-10 19:41:56'),
(3, '123', 'Administrator', '1023A', '2025-05-14 20:41:20'),
(5, '123', 'Administrator2', '1022A', '2025-05-14 20:44:05'),
(6, '1234', 'Administrator23', '1022A', '2025-05-14 20:44:24'),
(7, '1234', 'Administrator5', '1022A', '2025-05-14 20:44:48'),
(8, '1234', 'Administrator6', '1022B', '2025-05-14 20:46:12');

-- --------------------------------------------------------

--
-- Table structure for table `uploads`
--

CREATE TABLE `uploads` (
  `id` int(11) NOT NULL,
  `student_id` int(11) DEFAULT NULL,
  `file_name` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `file_type` varchar(50) NOT NULL,
  `folder_path` varchar(255) DEFAULT NULL,
  `upload_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `uploads`
--

INSERT INTO `uploads` (`id`, `student_id`, `file_name`, `original_name`, `file_type`, `folder_path`, `upload_date`) VALUES
(1, 1, '1739302753_0.docx', '674202155-Yumsaq-Bacariqlar-Muhazirə-Movzuları.docx', 'docx', '2222_Cavad_Huseynli_1023', '2025-02-11 19:39:13'),
(2, 2, '1739302925_0.doc', 'Manqal milli menyu AZE  5 oktyabr 2024 allergen.doc', 'doc', 'C111_Cavad_Huseynli_1023', '2025-02-10 19:42:05'),
(3, 3, '1747255292_0.xlsx', 'Rabitə  bank  son yekun neticələr.xlsx', 'xlsx', '123_Administrator_1023A', '2025-05-14 20:41:32'),
(4, 3, '1747255296_0.docx', 'Riyaziyyat və  rəqəmsal  texnologiyalar CAvaad Huseynli.docx', 'docx', '123_Administrator_1023A', '2025-05-14 20:41:36'),
(5, 5, '1747255452_0.docx', 'api  errors.docx', 'docx', '123_Administrator2_1022A', '2025-05-14 20:44:12'),
(6, 5, '1747255452_1.docx', 'meqale rey.docx', 'docx', '123_Administrator2_1022A', '2025-05-14 20:44:12'),
(7, 5, '1747255452_2.docx', 'Vin cheker.docx', 'docx', '123_Administrator2_1022A', '2025-05-14 20:44:12'),
(8, 6, '1747255471_0.docx', 'api  errors.docx', 'docx', '1234_Administrator23_1022A', '2025-05-14 20:44:31'),
(9, 6, '1747255471_1.docx', 'meqale rey.docx', 'docx', '1234_Administrator23_1022A', '2025-05-14 20:44:31'),
(10, 6, '1747255471_2.docx', 'Vin cheker.docx', 'docx', '1234_Administrator23_1022A', '2025-05-14 20:44:31'),
(11, 7, '1747255494_0.docx', 'api  errors.docx', 'docx', '1234_Administrator5_1022A', '2025-05-14 20:44:54'),
(12, 7, '1747255494_1.docx', 'meqale rey.docx', 'docx', '1234_Administrator5_1022A', '2025-05-14 20:44:54'),
(13, 7, '1747255494_2.docx', 'Vin cheker.docx', 'docx', '1234_Administrator5_1022A', '2025-05-14 20:44:54'),
(14, 8, '1747255576_0.docx', 'meqale rey.docx', 'docx', '1234_Administrator6_1022B', '2025-05-14 20:46:16');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `uploads`
--
ALTER TABLE `uploads`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `uploads`
--
ALTER TABLE `uploads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `uploads`
--
ALTER TABLE `uploads`
  ADD CONSTRAINT `uploads_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
