-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Aug 16, 2026 at 04:05 AM
-- Server version: 11.8.8-MariaDB-log
-- PHP Version: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `u190037990_mb_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `applicants`
--

CREATE TABLE `applicants` (
  `applicant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `resume_file` varchar(255) DEFAULT NULL,
  `skills` text DEFAULT NULL,
  `qualifications` text DEFAULT NULL,
  `experience_years` int(11) DEFAULT 0,
  `education_level` varchar(100) DEFAULT NULL,
  `employability_score` decimal(5,2) DEFAULT 0.00,
  `profile_completed` tinyint(1) DEFAULT 0,
  `profile_pic` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `applicants`
--

INSERT INTO `applicants` (`applicant_id`, `user_id`, `resume_file`, `skills`, `qualifications`, `experience_years`, `education_level`, `employability_score`, `profile_completed`, `profile_pic`) VALUES
(37, 40, 'uploads/resumes/resume_6a0fa430524930.45236360_1779409968.pdf', 'Communication, Problem Solving, JavaScript, MySQL, PHP, Adaptability, Attention to Detail, Collaboration, Compliance, Project Management, Python, SQL, Time Management, Visual Design', 'Criminology', 1, 'Bachelor', 77.60, 1, 'uploads/profile_pics/photo_40_1779508324.png'),
(39, 42, 'uploads/resumes/resume_69278ca46214f6.51301242_1764199588.pdf', 'Communication, JavaScript, MySQL, PHP, Problem Solving', 'Bachelor of Science in Accounting Information System', 1, 'Bachelor', 43.77, 1, NULL),
(42, 45, 'uploads/resumes/resume_6927987f37def5.23111418_1764202623.pdf', 'Adobe XD, Angular, Babel, Bootstrap, CSS, Figma, Git, GitHub, HTML, JavaScript, React, Sass, TypeScript, UI/UX Design, Vue, Webpack', 'Bachelor of Science in Computer Science', 3, 'High School', 0.00, 1, NULL),
(44, 47, 'uploads/resumes/resume_692798ea7df0d3.42109945_1764202730.pdf', 'python, Animation, Communication', 'Engineering', 5, 'Bachelor', 0.00, 1, NULL),
(48, 51, NULL, NULL, NULL, 0, NULL, 0.00, 0, NULL),
(49, 52, 'uploads/resumes/resume_69626bb2903534.25816120_1768057778.pdf', 'Initiative, Marketing, Multitasking, Project Management, Social Media Marketing, Time Management', 'Bachelor of Science in Information Systems', 5, 'Bachelor', 51.31, 1, NULL),
(50, 53, 'uploads/resumes/resume_696d945c76b253.65922541_1768789084.pdf', 'CSS, HTML, Python, Scripting, Teamwork', 'Bachelor of Science in Information Systems', 2, 'Bachelor', 55.66, 1, NULL),
(51, 54, NULL, NULL, NULL, 0, NULL, 50.17, 0, NULL),
(52, 55, 'uploads/resumes/resume_69717b87b05233.67074225_1769044871.pdf', 'CSS, HTML, Python, Scripting, Teamwork Project Management, Layout Design, Social Media Marketing and Advertising, multitasking, time management ,leadership, initiative', 'Bachelor of Science in Information Systems', 2, 'Bachelor', 51.77, 1, NULL),
(55, 58, NULL, NULL, NULL, 0, NULL, 0.00, 0, NULL),
(56, 59, NULL, NULL, NULL, 0, NULL, 0.00, 0, NULL),
(57, 60, NULL, NULL, NULL, 0, NULL, 0.00, 0, NULL),
(58, 61, 'uploads/resumes/resume_6972e5c55e1075.41095437_1769137605.pdf', 'Initiative, Marketing, Multitasking, Project Management, Social Media Marketing, Time Management', 'Bachelor of Science in Information Systems', 1, 'Bachelor', 67.31, 1, NULL),
(59, 62, NULL, NULL, NULL, 0, NULL, 0.00, 0, NULL),
(60, 63, NULL, NULL, NULL, 0, NULL, 0.00, 0, NULL),
(61, 64, NULL, NULL, NULL, 0, NULL, 0.00, 0, NULL),
(62, 65, NULL, NULL, NULL, 0, NULL, 0.00, 0, NULL),
(63, 66, NULL, NULL, NULL, 0, NULL, 0.00, 0, NULL),
(64, 67, 'uploads/resumes/resume_6997ed2946cb68.86640884_1771564329.pdf', 'CSS, HTML, Python, Scripting, Teamwork', 'Bachelor of Science in Marketing Management', 0, '', 0.00, 1, NULL),
(65, 68, NULL, NULL, NULL, 0, NULL, 0.00, 0, NULL),
(66, 69, NULL, NULL, NULL, 0, NULL, 0.00, 0, NULL),
(67, 70, NULL, NULL, NULL, 0, NULL, 0.00, 0, NULL),
(68, 71, NULL, NULL, NULL, 0, NULL, 0.00, 0, NULL),
(69, 72, NULL, NULL, NULL, 0, NULL, 0.00, 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `applications`
--

CREATE TABLE `applications` (
  `application_id` int(11) NOT NULL,
  `job_id` int(11) NOT NULL,
  `applicant_id` int(11) NOT NULL,
  `status` enum('pending','reviewed','shortlisted','interviewed','accepted','rejected') DEFAULT 'pending',
  `remarks_history` text DEFAULT NULL,
  `applicant_remarks_history` text DEFAULT NULL,
  `reviewed_by_employer_id` int(11) DEFAULT NULL,
  `reviewed_by_name` varchar(255) DEFAULT NULL,
  `match_score` decimal(5,2) DEFAULT 0.00,
  `cover_letter` text DEFAULT NULL,
  `resume_file` varchar(255) DEFAULT NULL,
  `applied_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `classification` varchar(50) DEFAULT NULL,
  `classified_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `applications`
--

INSERT INTO `applications` (`application_id`, `job_id`, `applicant_id`, `status`, `remarks_history`, `applicant_remarks_history`, `reviewed_by_employer_id`, `reviewed_by_name`, `match_score`, `cover_letter`, `resume_file`, `applied_at`, `updated_at`, `classification`, `classified_at`) VALUES
(27, 19, 39, 'shortlisted', '[{\"timestamp\":\"2026-01-07 12:35:04\",\"status\":\"accepted\",\"remarks\":\"You are accepted\",\"reviewed_by\":\"Employer user\"},{\"timestamp\":\"2026-01-07 12:35:04\",\"status\":\"accepted\",\"remarks\":\"You are accepted\",\"reviewed_by\":\"Employer user\"},{\"timestamp\":\"2026-01-07 13:19:29\",\"status\":\"rejected\",\"remarks\":\"ayawkol\",\"reviewed_by\":\"Employer user\"},{\"timestamp\":\"2026-01-07 13:19:41\",\"status\":\"rejected\",\"remarks\":\"adsds\",\"reviewed_by\":\"Employer user\"},{\"timestamp\":\"2026-01-07 13:19:52\",\"status\":\"shortlisted\",\"remarks\":\"adsddasda\",\"reviewed_by\":\"Employer user\"}]', '[{\"timestamp\":\"2026-01-07 13:19:29\",\"status\":\"rejected\",\"remarks\":\"ayawkol\",\"reviewed_by\":\"Employer user\"},{\"timestamp\":\"2026-01-07 13:19:41\",\"status\":\"rejected\",\"remarks\":\"adsds\",\"reviewed_by\":\"Employer user\"},{\"timestamp\":\"2026-01-07 13:19:52\",\"status\":\"shortlisted\",\"remarks\":\"adsddasda\",\"reviewed_by\":\"Employer user\"}]', 2, 'Employer user', 42.69, '', 'uploads/resumes/resume_69278ca46214f6.51301242_1764199588.pdf', '2025-11-26 23:28:29', '2026-01-07 13:19:52', NULL, NULL),
(28, 8, 39, 'accepted', NULL, NULL, 2, 'Employer user', 30.19, '', 'uploads/resumes/resume_69278ca46214f6.51301242_1764199588.pdf', '2025-11-26 23:28:49', '2026-01-04 10:00:19', NULL, NULL),
(29, 20, 37, 'interviewed', '[{\"timestamp\":\"2026-01-21 02:46:00\",\"status\":\"interviewed\",\"remarks\":\"dasdsad\",\"reviewed_by\":\"Employer user\"}]', '[{\"timestamp\":\"2026-01-21 02:46:00\",\"status\":\"interviewed\",\"remarks\":\"dasdsad\",\"reviewed_by\":\"Employer user\"}]', 2, 'Employer user', 25.00, '', 'uploads/resumes/resume_69278ae5d020f7.19954707_1764199141.pdf', '2025-11-26 23:58:55', '2026-01-21 02:46:00', NULL, NULL),
(31, 20, 44, 'pending', NULL, NULL, NULL, NULL, 15.00, '', 'uploads/resumes/resume_692798ea7df0d3.42109945_1764202730.pdf', '2025-11-27 00:19:48', '2025-11-27 00:19:48', NULL, NULL),
(32, 15, 44, 'pending', NULL, NULL, NULL, NULL, 23.33, '', 'uploads/resumes/resume_692798ea7df0d3.42109945_1764202730.pdf', '2025-11-27 00:36:55', '2025-11-27 00:36:55', NULL, NULL),
(33, 20, 49, 'accepted', '[{\"timestamp\":\"2026-01-21 02:05:48\",\"status\":\"accepted\",\"remarks\":\"sdfdfds\",\"reviewed_by\":\"Employer user\"}]', '[{\"timestamp\":\"2026-01-21 02:05:48\",\"status\":\"accepted\",\"remarks\":\"sdfdfds\",\"reviewed_by\":\"Employer user\"}]', 2, 'Employer user', 43.39, '', 'uploads/resumes/resume_69626bb2903534.25816120_1768057778.pdf', '2026-01-12 03:34:08', '2026-01-21 02:05:48', NULL, NULL),
(34, 8, 37, 'pending', NULL, NULL, NULL, NULL, 20.00, '', 'uploads/resumes/resume_69278ae5d020f7.19954707_1764199141.pdf', '2026-01-19 02:11:48', '2026-01-19 02:11:48', NULL, NULL),
(35, 20, 50, 'pending', NULL, NULL, NULL, NULL, 33.78, '', 'uploads/resumes/resume_696d945c76b253.65922541_1768789084.pdf', '2026-01-19 02:21:05', '2026-01-19 02:21:05', NULL, NULL),
(37, 21, 49, '', '[{\"timestamp\":\"2026-01-21 14:30:50\",\"status\":\"interviewed\",\"remarks\":\"sched for interview\",\"reviewed_by\":\"Employer user\"},{\"timestamp\":\"2026-01-22 07:48:16\",\"status\":\"scheduled for interview\",\"remarks\":\".\",\"reviewed_by\":\"Employer user\"}]', '[{\"timestamp\":\"2026-01-21 14:30:50\",\"status\":\"interviewed\",\"remarks\":\"sched for interview\",\"reviewed_by\":\"Employer user\"},{\"timestamp\":\"2026-01-22 07:48:16\",\"status\":\"scheduled for interview\",\"remarks\":\".\",\"reviewed_by\":\"Employer user\"}]', NULL, NULL, 57.67, '', 'uploads/resumes/resume_69626bb2903534.25816120_1768057778.pdf', '2026-01-21 14:22:10', '2026-01-22 07:48:16', NULL, NULL),
(38, 21, 52, 'accepted', '[{\"timestamp\":\"2026-01-22 05:26:39\",\"status\":\"scheduled for interview\",\"remarks\":\"asdasdsa\",\"reviewed_by\":\"Employer user\"},{\"timestamp\":\"2026-01-22 05:55:22\",\"status\":\"scheduled for interview\",\"remarks\":\"asdasdsa\",\"reviewed_by\":\"Employer user\"},{\"timestamp\":\"2026-01-22 06:01:15\",\"status\":\"scheduled for interview\",\"remarks\":\"dfasdf\",\"reviewed_by\":\"Employer user\"},{\"timestamp\":\"2026-01-22 06:16:51\",\"status\":\"accepted\",\"remarks\":\"tanggap ka na\",\"reviewed_by\":\"Employer user\"},{\"timestamp\":\"2026-01-22 13:45:28\",\"status\":\"scheduled for interview\",\"remarks\":\"dsdas\",\"reviewed_by\":\"Employer user\"},{\"timestamp\":\"2026-01-23 01:15:42\",\"status\":\"scheduled for interview\",\"remarks\":\"dsfsdf\",\"reviewed_by\":\"Employer user\"},{\"timestamp\":\"2026-01-23 02:58:12\",\"status\":\"accepted\",\"remarks\":\"sdsad\",\"reviewed_by\":\"Employer user\"}]', '[{\"timestamp\":\"2026-01-22 05:26:39\",\"status\":\"scheduled for interview\",\"remarks\":\"asdasdsa\",\"reviewed_by\":\"Employer user\"},{\"timestamp\":\"2026-01-22 05:55:22\",\"status\":\"scheduled for interview\",\"remarks\":\"asdasdsa\",\"reviewed_by\":\"Employer user\"},{\"timestamp\":\"2026-01-22 06:01:15\",\"status\":\"scheduled for interview\",\"remarks\":\"dfasdf\",\"reviewed_by\":\"Employer user\"},{\"timestamp\":\"2026-01-22 06:16:51\",\"status\":\"accepted\",\"remarks\":\"tanggap ka na\",\"reviewed_by\":\"Employer user\"},{\"timestamp\":\"2026-01-22 13:45:28\",\"status\":\"scheduled for interview\",\"remarks\":\"dsdas\",\"reviewed_by\":\"Employer user\"},{\"timestamp\":\"2026-01-23 01:15:42\",\"status\":\"scheduled for interview\",\"remarks\":\"dsfsdf\",\"reviewed_by\":\"Employer user\"},{\"timestamp\":\"2026-01-23 02:58:12\",\"status\":\"accepted\",\"remarks\":\"sdsad\",\"reviewed_by\":\"Employer user\"}]', 2, 'Employer user', 75.18, '', 'uploads/resumes/application_resume_6971b477dd72e6.25475628_1769059447.pdf', '2026-01-22 05:24:07', '2026-01-23 02:58:12', NULL, NULL),
(40, 20, 52, 'pending', NULL, NULL, NULL, NULL, 30.89, '', 'uploads/resumes/resume_69717b87b05233.67074225_1769044871.pdf', '2026-01-22 11:37:23', '2026-01-22 11:37:23', NULL, NULL),
(41, 13, 52, 'pending', NULL, NULL, NULL, NULL, 36.89, '', 'uploads/resumes/resume_69717b87b05233.67074225_1769044871.pdf', '2026-01-22 21:23:24', '2026-01-22 21:23:24', NULL, NULL),
(42, 15, 52, 'pending', NULL, NULL, NULL, NULL, 25.89, '', 'uploads/resumes/resume_69717b87b05233.67074225_1769044871.pdf', '2026-01-22 21:24:26', '2026-01-22 21:24:26', NULL, NULL),
(43, 12, 52, 'pending', NULL, NULL, NULL, NULL, 39.22, '', 'uploads/resumes/resume_69717b87b05233.67074225_1769044871.pdf', '2026-01-22 21:25:20', '2026-01-22 21:25:20', NULL, NULL),
(44, 21, 58, 'pending', NULL, NULL, NULL, NULL, 66.00, '', 'uploads/resumes/resume_6972e5c55e1075.41095437_1769137605.pdf', '2026-01-23 03:10:49', '2026-01-23 03:10:49', NULL, NULL),
(45, 21, 50, 'pending', NULL, NULL, NULL, NULL, 41.27, '', 'uploads/resumes/resume_696d945c76b253.65922541_1768789084.pdf', '2026-02-19 14:17:57', '2026-02-19 14:17:57', NULL, NULL),
(46, 23, 37, '', NULL, NULL, NULL, NULL, 23.17, '', 'uploads/resumes/resume_6a0fa430524930.45236360_1779409968.pdf', '2026-05-25 03:06:40', '2026-05-25 03:09:24', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `audit_trail`
--

CREATE TABLE `audit_trail` (
  `audit_id` int(11) NOT NULL,
  `admin_user_id` int(11) NOT NULL,
  `admin_name` varchar(255) NOT NULL,
  `action_type` varchar(100) NOT NULL,
  `action_description` text NOT NULL,
  `target_type` varchar(50) DEFAULT NULL COMMENT 'Type of entity affected: employer, applicant, user, job, etc.',
  `target_id` int(11) DEFAULT NULL COMMENT 'ID of the affected entity',
  `target_name` varchar(255) DEFAULT NULL COMMENT 'Name/identifier of the affected entity',
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_trail`
--

INSERT INTO `audit_trail` (`audit_id`, `admin_user_id`, `admin_name`, `action_type`, `action_description`, `target_type`, `target_id`, `target_name`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 1, 'Admin User', 'create_employer', 'Created new employer account: employer user (employer@gmail.com)', 'employer', 9, 'employer user', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 OPR/123.0.0.0', '2025-11-21 07:13:32');

-- --------------------------------------------------------

--
-- Table structure for table `candidate_feedback`
--

CREATE TABLE `candidate_feedback` (
  `feedback_id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `applicant_id` int(11) NOT NULL,
  `employer_id` int(11) NOT NULL,
  `employability_score` decimal(5,2) NOT NULL,
  `feedback_message` text NOT NULL,
  `feedback_type` enum('automatic','manual') DEFAULT 'automatic',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `candidate_feedback`
--

INSERT INTO `candidate_feedback` (`feedback_id`, `application_id`, `applicant_id`, `employer_id`, `employability_score`, `feedback_message`, `feedback_type`, `created_at`, `updated_at`) VALUES
(3, 37, 49, 2, 67.77, '👍 Izel Dela rosa has achieved a satisfactory employability score of 67.77%. This candidate shows moderate alignment with the position of Studio Coordinator. While they have some relevant qualifications, there may be areas for growth. ', 'automatic', '2026-01-21 14:31:37', '2026-01-21 14:31:37');

-- --------------------------------------------------------

--
-- Table structure for table `candidate_ml_features`
--

CREATE TABLE `candidate_ml_features` (
  `feature_id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `applicant_id` int(11) NOT NULL,
  `job_id` int(11) NOT NULL,
  `match_score` decimal(5,2) DEFAULT 0.00,
  `employability_score` decimal(5,2) DEFAULT 0.00,
  `experience_years` int(11) DEFAULT 0,
  `education_score` int(11) DEFAULT 0,
  `skills_match_count` int(11) DEFAULT 0,
  `response_time_hours` int(11) DEFAULT 0,
  `profile_completeness` decimal(5,2) DEFAULT 0.00,
  `certification_count` int(11) DEFAULT 0,
  `resume_quality_score` decimal(5,2) DEFAULT 0.00,
  `chatbot_score` decimal(5,2) DEFAULT 0.00,
  `ml_ranking_score` decimal(5,2) DEFAULT 0.00,
  `ranking_category` enum('excellent','good','average','poor') DEFAULT 'average',
  `relevance_score` int(11) DEFAULT 0,
  `hiring_outcome` enum('hired','interviewed','rejected','no_action') DEFAULT 'no_action',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `candidate_recommendations`
--

CREATE TABLE `candidate_recommendations` (
  `recommendation_id` int(11) NOT NULL,
  `employer_id` int(11) NOT NULL,
  `job_id` int(11) NOT NULL,
  `applicant_id` int(11) NOT NULL,
  `recommendation_score` decimal(5,2) NOT NULL,
  `reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `candidate_recommendations`
--

INSERT INTO `candidate_recommendations` (`recommendation_id`, `employer_id`, `job_id`, `applicant_id`, `recommendation_score`, `reason`, `created_at`) VALUES
(23, 2, 8, 37, 72.00, 'High employability score (100.0%) - Strong candidate based on chatbot assessment', '2026-01-21 14:27:08'),
(25, 2, 9, 37, 64.00, 'High employability score (100.0%) - Strong candidate based on chatbot assessment', '2026-01-21 14:27:08'),
(27, 2, 10, 37, 66.50, 'High employability score (100.0%) - Strong candidate based on chatbot assessment', '2026-01-21 14:27:08'),
(29, 2, 12, 37, 67.33, 'High employability score (100.0%) - Strong candidate based on chatbot assessment', '2026-01-21 14:27:08'),
(31, 2, 13, 37, 68.00, 'High employability score (100.0%) - Strong candidate based on chatbot assessment', '2026-01-21 14:27:08'),
(33, 2, 15, 37, 67.33, 'High employability score (100.0%) - Strong candidate based on chatbot assessment', '2026-01-21 14:27:08'),
(35, 2, 19, 37, 69.00, 'High employability score (100.0%) - Strong candidate based on chatbot assessment', '2026-01-21 14:27:08'),
(37, 2, 20, 37, 74.00, 'High employability score (100.0%) - Strong candidate based on chatbot assessment', '2026-01-21 14:27:08'),
(39, 2, 21, 37, 64.00, 'High employability score (100.0%) - Strong candidate based on chatbot assessment', '2026-01-21 14:27:08'),
(41, 2, 21, 49, 56.85, 'Strong match based on chatbot assessment answers and job requirements', '2026-01-21 14:27:08'),
(42, 2, 21, 52, 51.45, 'Strong match based on chatbot assessment answers and job requirements', '2026-01-22 07:50:37');

-- --------------------------------------------------------

--
-- Table structure for table `chatbot_answers`
--

CREATE TABLE `chatbot_answers` (
  `answer_id` int(11) NOT NULL,
  `applicant_id` int(11) NOT NULL,
  `qualification_id` int(11) DEFAULT NULL,
  `question_number` int(11) NOT NULL,
  `question_text` text NOT NULL,
  `answer_text` text NOT NULL,
  `answer_value` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `category` varchar(255) DEFAULT 'general',
  `score_value` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `chatbot_answers`
--

INSERT INTO `chatbot_answers` (`answer_id`, `applicant_id`, `qualification_id`, `question_number`, `question_text`, `answer_text`, `answer_value`, `created_at`, `category`, `score_value`) VALUES
(421, 39, NULL, 1, 'Which best describes your work background?', 'Entry-level employee', NULL, '2025-11-26 23:27:35', 'education', 15),
(422, 39, NULL, 2, 'How many years of professional experience do you have in your field?', '2-3 years', NULL, '2025-11-26 23:27:35', 'experience', 20),
(423, 39, NULL, 3, 'How would you rate your relevant skills for your field?', 'Intermediate', NULL, '2025-11-26 23:27:35', 'technical', 20),
(424, 39, NULL, 4, 'Do you have any professional certifications or licenses?', '1-2 certifications', NULL, '2025-11-26 23:27:35', 'certifications', 15),
(425, 39, NULL, 5, 'How comfortable are you with working in a team environment?', 'Somewhat comfortable', NULL, '2025-11-26 23:27:35', 'soft_skills', 15),
(426, 39, NULL, 6, 'How would you rate your communication skills?', 'Fair', NULL, '2025-11-26 23:27:35', 'soft_skills', 15),
(427, 39, NULL, 7, 'Are you willing to relocate for a job opportunity?', 'Yes', NULL, '2025-11-26 23:27:35', 'flexibility', 20),
(428, 39, NULL, 8, 'How many relevant tools or software are you proficient in for your field?', '1-2', NULL, '2025-11-26 23:27:35', 'technical', 10),
(429, 39, NULL, 9, 'How would you rate your ability to adapt to new processes and systems?', 'Fair', NULL, '2025-11-26 23:27:35', 'learning', 15),
(430, 39, NULL, 10, 'How would you rate your problem-solving abilities?', 'Fair', NULL, '2025-11-26 23:27:35', 'soft_skills', 15),
(431, 39, NULL, 11, 'Have you worked on projects or tasks with tight deadlines?', 'Rarely', NULL, '2025-11-26 23:27:35', 'experience', 10),
(432, 39, NULL, 12, 'How would you rate your ability to learn new skills quickly?', 'Average', NULL, '2025-11-26 23:27:35', 'learning', 15),
(433, 39, 6, 0, '', '', NULL, '2025-11-26 23:28:21', 'general', 0),
(434, 42, 17, 0, '', '', NULL, '2025-11-27 00:15:32', 'general', 0),
(435, 44, 23, 0, '', '', NULL, '2025-11-27 00:18:04', 'general', 0),
(438, 48, 18, 0, '', '', NULL, '2026-01-08 03:54:35', 'general', 0),
(489, 50, NULL, 1, 'Which best describes your work background?', 'Manager/Executive', NULL, '2026-01-19 02:20:34', 'education', 45),
(490, 50, NULL, 2, 'How many years of professional experience do you have in your field?', '4-5 years', NULL, '2026-01-19 02:20:34', 'experience', 30),
(491, 50, NULL, 3, 'How would you rate your relevant skills for your field?', 'Advanced', NULL, '2026-01-19 02:20:34', 'technical', 30),
(492, 50, NULL, 4, 'Do you have any professional certifications or licenses?', '1-2 certifications', NULL, '2026-01-19 02:20:34', 'certifications', 15),
(493, 50, NULL, 5, 'How comfortable are you with working in a team environment?', 'Somewhat comfortable', NULL, '2026-01-19 02:20:34', 'soft_skills', 15),
(494, 50, NULL, 6, 'How would you rate your communication skills?', 'Fair', NULL, '2026-01-19 02:20:34', 'soft_skills', 15),
(495, 50, NULL, 7, 'Are you willing to relocate for a job opportunity?', 'Maybe', NULL, '2026-01-19 02:20:34', 'flexibility', 10),
(496, 50, NULL, 8, 'How many relevant tools or software are you proficient in for your field?', '1-2', NULL, '2026-01-19 02:20:34', 'technical', 10),
(497, 50, NULL, 9, 'How would you rate your ability to adapt to new processes and systems?', 'Poor', NULL, '2026-01-19 02:20:34', 'learning', 5),
(498, 50, NULL, 10, 'How would you rate your problem-solving abilities?', 'Fair', NULL, '2026-01-19 02:20:34', 'soft_skills', 15),
(499, 50, NULL, 11, 'Have you worked on projects or tasks with tight deadlines?', 'Rarely', NULL, '2026-01-19 02:20:34', 'experience', 10),
(500, 50, NULL, 12, 'How would you rate your ability to learn new skills quickly?', 'Fast', NULL, '2026-01-19 02:20:34', 'learning', 25),
(501, 50, 18, 0, '', '', NULL, '2026-01-19 02:20:51', 'general', 0),
(502, 51, NULL, 1, 'Which best describes your work background?', 'No work experience/ fresh graduate', NULL, '2026-01-21 04:12:32', 'education', 10),
(503, 51, NULL, 2, 'How many years of professional experience do you have in your field?', '0-1 years', NULL, '2026-01-21 04:12:32', 'experience', 10),
(504, 51, NULL, 3, 'How would you rate your relevant skills for your field?', 'Beginner', NULL, '2026-01-21 04:12:32', 'technical', 10),
(505, 51, NULL, 4, 'Do you have any professional certifications or licenses?', 'No', NULL, '2026-01-21 04:12:32', 'certifications', 0),
(506, 51, NULL, 5, 'How comfortable are you with working in a team environment?', 'Comfortable', NULL, '2026-01-21 04:12:32', 'soft_skills', 25),
(507, 51, NULL, 6, 'How would you rate your communication skills?', 'Good', NULL, '2026-01-21 04:12:32', 'soft_skills', 25),
(508, 51, NULL, 7, 'Are you willing to relocate for a job opportunity?', 'Yes', NULL, '2026-01-21 04:12:32', 'flexibility', 20),
(509, 51, NULL, 8, 'How many relevant tools or software are you proficient in for your field?', '1-2', NULL, '2026-01-21 04:12:32', 'technical', 10),
(510, 51, NULL, 9, 'How would you rate your ability to adapt to new processes and systems?', 'Good', NULL, '2026-01-21 04:12:32', 'learning', 25),
(511, 51, NULL, 10, 'How would you rate your problem-solving abilities?', 'Good', NULL, '2026-01-21 04:12:32', 'soft_skills', 25),
(512, 51, NULL, 11, 'Have you worked on projects or tasks with tight deadlines?', 'Frequently', NULL, '2026-01-21 04:12:32', 'experience', 30),
(513, 51, NULL, 12, 'How would you rate your ability to learn new skills quickly?', 'Fast', NULL, '2026-01-21 04:12:32', 'learning', 25),
(514, 52, NULL, 1, 'Which best describes your work background?', 'Entry-level employee', NULL, '2026-01-22 01:20:20', 'education', 15),
(515, 52, NULL, 2, 'How many years of professional experience do you have in your field?', '0-1 years', NULL, '2026-01-22 01:20:20', 'experience', 10),
(516, 52, NULL, 3, 'How would you rate your relevant skills for your field?', 'Beginner', NULL, '2026-01-22 01:20:20', 'technical', 10),
(517, 52, NULL, 4, 'Do you have any professional certifications or licenses?', 'No', NULL, '2026-01-22 01:20:20', 'certifications', 0),
(518, 52, NULL, 5, 'How comfortable are you with working in a team environment?', 'Somewhat comfortable', NULL, '2026-01-22 01:20:20', 'soft_skills', 15),
(519, 52, NULL, 6, 'How would you rate your communication skills?', 'Good', NULL, '2026-01-22 01:20:20', 'soft_skills', 25),
(520, 52, NULL, 7, 'Are you willing to relocate for a job opportunity?', 'Yes', NULL, '2026-01-22 01:20:20', 'flexibility', 20),
(521, 52, NULL, 8, 'How many relevant tools or software are you proficient in for your field?', '1-2', NULL, '2026-01-22 01:20:20', 'technical', 10),
(522, 52, NULL, 9, 'How would you rate your ability to adapt to new processes and systems?', 'Good', NULL, '2026-01-22 01:20:20', 'learning', 25),
(523, 52, NULL, 10, 'How would you rate your problem-solving abilities?', 'Good', NULL, '2026-01-22 01:20:20', 'soft_skills', 25),
(524, 52, NULL, 11, 'Have you worked on projects or tasks with tight deadlines?', 'Frequently', NULL, '2026-01-22 01:20:20', 'experience', 30),
(525, 52, NULL, 12, 'How would you rate your ability to learn new skills quickly?', 'Very Fast', NULL, '2026-01-22 01:20:20', 'learning', 35),
(526, 52, 18, 0, '', '', NULL, '2026-01-22 01:21:11', 'general', 0),
(528, 49, NULL, 1, 'Which best describes your work background?', 'No work experience/ fresh graduate', NULL, '2026-01-22 15:52:56', 'education', 10),
(529, 49, NULL, 2, 'How many years of professional experience do you have in your field?', '0-1 years', NULL, '2026-01-22 15:52:56', 'experience', 10),
(530, 49, NULL, 3, 'How would you rate your relevant skills for your field?', 'Beginner', NULL, '2026-01-22 15:52:56', 'technical', 10),
(531, 49, NULL, 4, 'Do you have any professional certifications or licenses?', 'No', NULL, '2026-01-22 15:52:56', 'certifications', 0),
(532, 49, NULL, 5, 'How comfortable are you with working in a team environment?', 'Very comfortable', NULL, '2026-01-22 15:52:56', 'soft_skills', 35),
(533, 49, NULL, 6, 'How would you rate your communication skills?', 'Good', NULL, '2026-01-22 15:52:56', 'soft_skills', 25),
(534, 49, NULL, 7, 'Are you willing to relocate for a job opportunity?', 'Yes', NULL, '2026-01-22 15:52:56', 'flexibility', 20),
(535, 49, NULL, 8, 'How many relevant tools or software are you proficient in for your field?', '1-2', NULL, '2026-01-22 15:52:56', 'technical', 10),
(536, 49, NULL, 9, 'How would you rate your ability to adapt to new processes and systems?', 'Good', NULL, '2026-01-22 15:52:56', 'learning', 25),
(537, 49, NULL, 10, 'How would you rate your problem-solving abilities?', 'Excellent', NULL, '2026-01-22 15:52:56', 'soft_skills', 35),
(538, 49, NULL, 11, 'Have you worked on projects or tasks with tight deadlines?', 'Sometimes', NULL, '2026-01-22 15:52:56', 'experience', 20),
(539, 49, NULL, 12, 'How would you rate your ability to learn new skills quickly?', 'Fast', NULL, '2026-01-22 15:52:56', 'learning', 25),
(541, 58, NULL, 1, 'Which best describes your work background?', 'No work experience/ fresh graduate', NULL, '2026-01-23 03:09:59', 'education', 10),
(542, 58, NULL, 2, 'How many years of professional experience do you have in your field?', '0-1 years', NULL, '2026-01-23 03:09:59', 'experience', 10),
(543, 58, NULL, 3, 'How would you rate your relevant skills for your field?', 'Beginner', NULL, '2026-01-23 03:09:59', 'technical', 10),
(544, 58, NULL, 4, 'Do you have any professional certifications or licenses?', '1-2 certifications', NULL, '2026-01-23 03:09:59', 'certifications', 15),
(545, 58, NULL, 5, 'How comfortable are you with working in a team environment?', 'Comfortable', NULL, '2026-01-23 03:09:59', 'soft_skills', 25),
(546, 58, NULL, 6, 'How would you rate your communication skills?', 'Excellent', NULL, '2026-01-23 03:09:59', 'soft_skills', 35),
(547, 58, NULL, 7, 'Are you willing to relocate for a job opportunity?', 'Yes', NULL, '2026-01-23 03:09:59', 'flexibility', 20),
(548, 58, NULL, 8, 'How many relevant tools or software are you proficient in for your field?', '5+', NULL, '2026-01-23 03:09:59', 'technical', 30),
(549, 58, NULL, 9, 'How would you rate your ability to adapt to new processes and systems?', 'Excellent', NULL, '2026-01-23 03:09:59', 'learning', 35),
(550, 58, NULL, 10, 'How would you rate your problem-solving abilities?', 'Excellent', NULL, '2026-01-23 03:09:59', 'soft_skills', 35),
(551, 58, NULL, 11, 'Have you worked on projects or tasks with tight deadlines?', 'Frequently', NULL, '2026-01-23 03:09:59', 'experience', 30),
(552, 58, NULL, 12, 'How would you rate your ability to learn new skills quickly?', 'Very Fast', NULL, '2026-01-23 03:09:59', 'learning', 35),
(553, 60, 18, 0, '', '', NULL, '2026-02-19 13:30:28', 'general', 0),
(554, 61, 7, 0, '', '', NULL, '2026-02-19 13:56:36', 'general', 0),
(555, 64, 1, 0, '', '', NULL, '2026-02-20 05:08:21', 'general', 0),
(568, 37, NULL, 1, 'Which best describes your work background?', 'Entry-level employee', NULL, '2026-05-22 00:34:40', 'education', 15),
(569, 37, NULL, 2, 'How many years of professional experience do you have in your field?', '6+ years', NULL, '2026-05-22 00:34:40', 'experience', 40),
(570, 37, NULL, 3, 'How would you rate your relevant skills for your field?', 'Expert', NULL, '2026-05-22 00:34:40', 'technical', 40),
(571, 37, NULL, 4, 'Do you have any professional certifications or licenses?', '5+ certifications', NULL, '2026-05-22 00:34:40', 'certifications', 35),
(572, 37, NULL, 5, 'How comfortable are you with working in a team environment?', 'Comfortable', NULL, '2026-05-22 00:34:40', 'soft_skills', 25),
(573, 37, NULL, 6, 'How would you rate your communication skills?', 'Good', NULL, '2026-05-22 00:34:40', 'soft_skills', 25),
(574, 37, NULL, 7, 'Are you willing to relocate for a job opportunity?', 'Yes', NULL, '2026-05-22 00:34:40', 'flexibility', 20),
(575, 37, NULL, 8, 'How many relevant tools or software are you proficient in for your field?', '3-4', NULL, '2026-05-22 00:34:40', 'technical', 20),
(576, 37, NULL, 9, 'How would you rate your ability to adapt to new processes and systems?', 'Good', NULL, '2026-05-22 00:34:40', 'learning', 25),
(577, 37, NULL, 10, 'How would you rate your problem-solving abilities?', 'Excellent', NULL, '2026-05-22 00:34:40', 'soft_skills', 35),
(578, 37, NULL, 11, 'Have you worked on projects or tasks with tight deadlines?', 'Sometimes', NULL, '2026-05-22 00:34:40', 'experience', 20),
(579, 37, NULL, 12, 'How would you rate your ability to learn new skills quickly?', 'Fast', NULL, '2026-05-22 00:34:40', 'learning', 25);

-- --------------------------------------------------------

--
-- Table structure for table `chatbot_recommendations`
--

CREATE TABLE `chatbot_recommendations` (
  `recommendation_id` int(11) NOT NULL,
  `applicant_id` int(11) NOT NULL,
  `job_id` int(11) DEFAULT NULL,
  `qualification_id` int(11) DEFAULT NULL,
  `recommendation_type` enum('job','qualification') NOT NULL,
  `recommendation_text` text DEFAULT NULL,
  `match_score` decimal(5,2) DEFAULT 0.00,
  `is_selected` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cms_brands`
--

CREATE TABLE `cms_brands` (
  `id` int(11) NOT NULL,
  `brand_name` varchar(255) NOT NULL,
  `brand_description` text DEFAULT NULL,
  `brand_overlay_title` varchar(255) DEFAULT NULL,
  `brand_overlay_description` text DEFAULT NULL,
  `brand_logo` varchar(500) DEFAULT NULL,
  `brand_category` varchar(50) DEFAULT 'cor',
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_uca1400_ai_ci;

--
-- Dumping data for table `cms_brands`
--

INSERT INTO `cms_brands` (`id`, `brand_name`, `brand_description`, `brand_overlay_title`, `brand_overlay_description`, `brand_logo`, `brand_category`, `sort_order`, `is_active`, `created_at`, `updated_at`) VALUES
(3, 'sadsasasd', 'sasd', 'ssa', 'sdasdsa', 'images/brands/brand_1779510288_6a112c105e569.png', 'foo', 0, 1, '2026-05-23 04:24:48', '2026-05-23 04:37:42'),
(4, 'Lungsod ng Bacoor', 'Building progress together.', 'Lungsod ng Bacoor', 'Empowering local communities.', 'images/b1.png', 'gov', 1, 1, '2026-05-23 05:34:18', '2026-05-23 05:34:18'),
(5, 'Lungsod ng Imus', 'Innovative city, empowered citizens.', 'Lungsod ng Imus', 'Advancing sustainable growth.', 'images/b1.png', 'gov', 2, 1, '2026-05-23 05:34:18', '2026-05-23 05:34:18'),
(6, 'City of Santa Rosa', 'The Lion City of the South.', 'City of Santa Rosa', 'Driving innovation and excellence.', 'images/b3.png', 'gov', 3, 1, '2026-05-23 05:34:18', '2026-05-23 05:34:18'),
(7, 'City of Trece Martires', 'Unity in progress.', 'City of Trece Martires', 'Committed to public service.', 'images/b4.png', 'gov', 4, 1, '2026-05-23 05:34:18', '2026-05-23 05:34:18'),
(8, 'City of General Trias', 'Championing development.', 'City of General Trias', 'Creating opportunities for all.', 'images/b5.png', 'gov', 5, 1, '2026-05-23 05:34:18', '2026-05-23 05:34:18'),
(9, 'City of San Pedro', 'Gateway to Laguna.', 'City of San Pedro', 'People-centered governance.', 'images/b6.png', 'gov', 6, 1, '2026-05-23 05:34:18', '2026-05-23 05:34:18'),
(10, 'Lalawigan ng Laguna', 'Heart of Calabarzon.', 'Lalawigan ng Laguna', 'Promoting inclusive prosperity.', 'images/b8.png', 'gov', 7, 1, '2026-05-23 05:34:18', '2026-05-23 05:34:18'),
(11, 'Lalawigan ng Batangas', 'Heart of Calabarzon.', 'Lalawigan ng Batangas', 'Promoting inclusive prosperity.', 'images/bnine.png', 'gov', 8, 1, '2026-05-23 05:34:18', '2026-05-23 05:34:18'),
(12, 'Globe myBusiness', 'Empowering Filipino entrepreneurs.', 'Globe myBusiness', 'Connecting business, powering success.', 'images/b11.png', 'cor', 9, 1, '2026-05-23 05:34:18', '2026-05-23 05:34:18'),
(13, 'Asia Brewery Inc.', 'Refreshing the nation.', 'Asia Brewery Inc.', 'Committed to quality and innovation.', 'images/b12.jpg', 'cor', 10, 1, '2026-05-23 05:34:18', '2026-05-23 05:34:18'),
(14, 'AFFI Entrepreneurs', 'Fueling business dreams.', 'AFFI Entrepreneurs', 'Together, we grow stronger.', 'images/b13.png', 'cor', 11, 1, '2026-05-23 05:34:18', '2026-05-23 05:34:18'),
(15, 'Metrobank', 'You\'re in good hands.', 'Metrobank', 'Meaningful banking for Filipinos.', 'images/b14.png', 'cor', 12, 1, '2026-05-23 05:34:18', '2026-05-23 05:34:18'),
(16, 'CARD SME Bank', 'Financing your future.', 'CARD SME Bank', 'Helping small dreams grow big.', 'images/b15.png', 'cor', 13, 1, '2026-05-23 05:34:18', '2026-05-23 05:34:18'),
(17, 'Mitsubishi Motors', 'Drive your ambition.', 'Mitsubishi Motors', 'Innovation in motion.', 'images/b17.jpg', 'cor', 14, 1, '2026-05-23 05:34:18', '2026-05-23 05:34:18'),
(18, 'Hyundai', 'Progress for humanity.', 'Hyundai', 'New thinking, new possibilities.', 'images/b18.jpg', 'cor', 15, 1, '2026-05-23 05:34:18', '2026-05-23 05:34:18'),
(19, 'CITIMOTORS INC.', 'Driven by trust.', 'CITIMOTORS INC.', 'Your road to reliability.', 'images/b1nine.jpg', 'cor', 16, 1, '2026-05-23 05:34:18', '2026-05-23 05:34:18'),
(20, 'PrimeWater', 'Clean water, better life.', 'PrimeWater', 'Sustaining communities nationwide.', 'images/b20.png', 'cor', 17, 1, '2026-05-23 05:34:18', '2026-05-23 05:34:18'),
(21, 'IMI', 'Engineering a smarter world.', 'IMI', 'Innovating for the future.', 'images/b21.jpg', 'cor', 18, 1, '2026-05-23 05:34:18', '2026-05-23 05:34:18'),
(22, 'Concentrix', 'Designing better human experiences.', 'Concentrix', 'People. Passion. Performance.', 'images/b23.png', 'cor', 19, 1, '2026-05-23 05:34:18', '2026-05-23 05:34:18'),
(23, 'Convergys', 'Empowering people, powering business.', 'Convergys', 'Delivering customer excellence.', 'images/b24.png', 'cor', 20, 1, '2026-05-23 05:34:18', '2026-05-23 05:34:18'),
(24, 'Jollibee', 'Bida ang saya!', 'Jollibee', 'Bringing joy to every Filipino.', 'images/b25.png', 'foo', 21, 1, '2026-05-23 05:34:18', '2026-05-23 05:34:18'),
(25, 'Days Hotel', 'Stay comfortable, stay inspired.', 'Days Hotel', 'Your home away from home.', 'images/b26.jpg', 'foo', 22, 1, '2026-05-23 05:34:18', '2026-05-23 05:34:18'),
(26, 'Manila Ocean Park', 'Dive into discovery.', 'Manila Ocean Park', 'Where fun meets the ocean.', 'images/b28.jpg', 'foo', 23, 1, '2026-05-23 05:34:18', '2026-05-23 05:34:18'),
(27, 'STI', 'Education for real life.', 'STI', 'Driven by technology and excellence.', 'images/b2nine.jpg', 'edu', 24, 1, '2026-05-23 05:34:18', '2026-05-23 05:34:18'),
(28, 'Department of Agriculture', 'Masaganang ani, mataas na kita.', 'Department of Agriculture', 'Securing food for every Filipino.', 'images/b30.png', 'gov', 25, 1, '2026-05-23 05:34:18', '2026-05-23 05:34:18'),
(29, 'DPWH', 'Building better roads.', 'DPWH', 'Connecting communities nationwide.', 'images/b32.png', 'gov', 26, 1, '2026-05-23 05:34:18', '2026-05-23 05:34:18'),
(30, 'City of Dasmariñas', 'The university city.', 'City of Dasmariñas', 'Home of education and progress.', 'images/b6.jpg', 'gov', 27, 1, '2026-05-23 05:34:18', '2026-05-23 05:34:18'),
(31, 'Lungsod ng Bacoor', 'Building progress together.', 'Lungsod ng Bacoor', 'Empowering local communities.', 'images/b1.png', 'gov', 1, 1, '2026-05-24 04:34:57', '2026-05-24 04:34:57'),
(32, 'Lungsod ng Imus', 'Innovative city, empowered citizens.', 'Lungsod ng Imus', 'Advancing sustainable growth.', 'images/b1.png', 'gov', 2, 1, '2026-05-24 04:34:57', '2026-05-24 04:34:57'),
(33, 'City of Santa Rosa', 'The Lion City of the South.', 'City of Santa Rosa', 'Driving innovation and excellence.', 'images/b3.png', 'gov', 3, 1, '2026-05-24 04:34:57', '2026-05-24 04:34:57'),
(34, 'City of Trece Martires', 'Unity in progress.', 'City of Trece Martires', 'Committed to public service.', 'images/b4.png', 'gov', 4, 1, '2026-05-24 04:34:57', '2026-05-24 04:34:57'),
(35, 'City of General Trias', 'Championing development.', 'City of General Trias', 'Creating opportunities for all.', 'images/b5.png', 'gov', 5, 1, '2026-05-24 04:34:57', '2026-05-24 04:34:57'),
(36, 'City of San Pedro', 'Gateway to Laguna.', 'City of San Pedro', 'People-centered governance.', 'images/b6.png', 'gov', 6, 1, '2026-05-24 04:34:57', '2026-05-24 04:34:57'),
(37, 'Lalawigan ng Laguna', 'Heart of Calabarzon.', 'Lalawigan ng Laguna', 'Promoting inclusive prosperity.', 'images/b8.png', 'gov', 7, 1, '2026-05-24 04:34:57', '2026-05-24 04:34:57'),
(38, 'Lalawigan ng Batangas', 'Heart of Calabarzon.', 'Lalawigan ng Batangas', 'Promoting inclusive prosperity.', 'images/bnine.png', 'gov', 8, 1, '2026-05-24 04:34:57', '2026-05-24 04:34:57'),
(39, 'Globe myBusiness', 'Empowering Filipino entrepreneurs.', 'Globe myBusiness', 'Connecting business, powering success.', 'images/b11.png', 'cor', 9, 1, '2026-05-24 04:34:57', '2026-05-24 04:34:57'),
(40, 'Asia Brewery Inc.', 'Refreshing the nation.', 'Asia Brewery Inc.', 'Committed to quality and innovation.', 'images/b12.jpg', 'cor', 10, 1, '2026-05-24 04:34:57', '2026-05-24 04:34:57'),
(41, 'AFFI Entrepreneurs', 'Fueling business dreams.', 'AFFI Entrepreneurs', 'Together, we grow stronger.', 'images/b13.png', 'cor', 11, 1, '2026-05-24 04:34:57', '2026-05-24 04:34:57'),
(42, 'Metrobank', 'You\'re in good hands.', 'Metrobank', 'Meaningful banking for Filipinos.', 'images/b14.png', 'cor', 12, 1, '2026-05-24 04:34:57', '2026-05-24 04:34:57'),
(43, 'CARD SME Bank', 'Financing your future.', 'CARD SME Bank', 'Helping small dreams grow big.', 'images/b15.png', 'cor', 13, 1, '2026-05-24 04:34:57', '2026-05-24 04:34:57'),
(44, 'Mitsubishi Motors', 'Drive your ambition.', 'Mitsubishi Motors', 'Innovation in motion.', 'images/b17.jpg', 'cor', 14, 1, '2026-05-24 04:34:57', '2026-05-24 04:34:57'),
(45, 'Hyundai', 'Progress for humanity.', 'Hyundai', 'New thinking, new possibilities.', 'images/b18.jpg', 'cor', 15, 1, '2026-05-24 04:34:57', '2026-05-24 04:34:57'),
(46, 'CITIMOTORS INC.', 'Driven by trust.', 'CITIMOTORS INC.', 'Your road to reliability.', 'images/b1nine.jpg', 'cor', 16, 1, '2026-05-24 04:34:57', '2026-05-24 04:34:57'),
(47, 'PrimeWater', 'Clean water, better life.', 'PrimeWater', 'Sustaining communities nationwide.', 'images/b20.png', 'cor', 17, 1, '2026-05-24 04:34:57', '2026-05-24 04:34:57'),
(48, 'IMI', 'Engineering a smarter world.', 'IMI', 'Innovating for the future.', 'images/b21.jpg', 'cor', 18, 1, '2026-05-24 04:34:57', '2026-05-24 04:34:57'),
(49, 'Concentrix', 'Designing better human experiences.', 'Concentrix', 'People. Passion. Performance.', 'images/b23.png', 'cor', 19, 1, '2026-05-24 04:34:57', '2026-05-24 04:34:57'),
(50, 'Convergys', 'Empowering people, powering business.', 'Convergys', 'Delivering customer excellence.', 'images/b24.png', 'cor', 20, 1, '2026-05-24 04:34:57', '2026-05-24 04:34:57'),
(51, 'Jollibee', 'Bida ang saya!', 'Jollibee', 'Bringing joy to every Filipino.', 'images/b25.png', 'foo', 21, 1, '2026-05-24 04:34:57', '2026-05-24 04:34:57'),
(52, 'Days Hotel', 'Stay comfortable, stay inspired.', 'Days Hotel', 'Your home away from home.', 'images/b26.jpg', 'foo', 22, 1, '2026-05-24 04:34:57', '2026-05-24 04:34:57'),
(53, 'Manila Ocean Park', 'Dive into discovery.', 'Manila Ocean Park', 'Where fun meets the ocean.', 'images/b28.jpg', 'foo', 23, 1, '2026-05-24 04:34:57', '2026-05-24 04:34:57'),
(54, 'STI', 'Education for real life.', 'STI', 'Driven by technology and excellence.', 'images/b2nine.jpg', 'edu', 24, 1, '2026-05-24 04:34:57', '2026-05-24 04:34:57'),
(55, 'Department of Agriculture', 'Masaganang ani, mataas na kita.', 'Department of Agriculture', 'Securing food for every Filipino.', 'images/b30.png', 'gov', 25, 1, '2026-05-24 04:34:57', '2026-05-24 04:34:57'),
(56, 'DPWH', 'Building better roads.', 'DPWH', 'Connecting communities nationwide.', 'images/b32.png', 'gov', 26, 1, '2026-05-24 04:34:57', '2026-05-24 04:34:57'),
(57, 'City of Dasmariñas', 'The university city.', 'City of Dasmariñas', 'Home of education and progress.', 'images/b6.jpg', 'gov', 27, 1, '2026-05-24 04:34:57', '2026-05-24 04:34:57');

-- --------------------------------------------------------

--
-- Table structure for table `cms_content`
--

CREATE TABLE `cms_content` (
  `id` int(11) NOT NULL,
  `section_id` int(11) NOT NULL,
  `field_key` varchar(100) NOT NULL,
  `field_value` text DEFAULT NULL,
  `language` varchar(10) DEFAULT 'en',
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `cms_content`
--

INSERT INTO `cms_content` (`id`, `section_id`, `field_key`, `field_value`, `language`, `sort_order`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 'years', '23+', 'en', 0, 1, '2026-04-18 03:06:55', '2026-05-23 05:51:30'),
(2, 1, 'clients', '500+', 'en', 0, 1, '2026-04-18 03:06:55', '2026-04-18 03:06:55'),
(3, 1, 'efficiency', '95%', 'en', 0, 1, '2026-04-18 03:06:55', '2026-04-18 03:06:55'),
(4, 1, 'units', '3K+', 'en', 0, 1, '2026-04-18 03:06:55', '2026-04-18 03:06:55'),
(5, 2, 'title', 'Our Partner Brands', 'en', 0, 1, '2026-04-18 03:06:56', '2026-04-18 03:06:56'),
(6, 3, 'title', 'What Our Clients Say', 'en', 0, 1, '2026-04-18 03:06:56', '2026-04-18 03:06:56'),
(7, 4, 'title', 'Featured Job Openings', 'en', 0, 1, '2026-04-18 03:06:56', '2026-04-18 03:06:56');

-- --------------------------------------------------------

--
-- Table structure for table `cms_hero_slides`
--

CREATE TABLE `cms_hero_slides` (
  `id` int(11) NOT NULL,
  `title` varchar(200) DEFAULT NULL,
  `subtitle` text DEFAULT NULL,
  `button_text` varchar(100) DEFAULT NULL,
  `button_link` varchar(255) DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `cms_hero_slides`
--

INSERT INTO `cms_hero_slides` (`id`, `title`, `subtitle`, `button_text`, `button_link`, `image_path`, `sort_order`, `is_active`, `created_at`) VALUES
(1, 'Integrated Business<br><em>Solutions</em> That Scale', 'Connect with opportunities that match your skills. Partner with the Philippines\' leading managed services corporation.', 'Browse Jobs', 'careers.php', 'images/picture1.png', 0, 1, '2026-04-18 03:06:56'),
(2, 'Global <em>Partnerships</em>', 'Building bridges between talent and opportunity across the Philippines and beyond.', 'Learn More', 'about.php', 'images/picture2.png', 1, 1, '2026-04-18 03:06:56'),
(3, 'Digital <em>Transformation</em>', 'Empowering businesses with cutting-edge managed services and solutions.', 'Our Services', 'services.php', 'images/picture3.png', 2, 1, '2026-04-18 03:06:56');

-- --------------------------------------------------------

--
-- Table structure for table `cms_news`
--

CREATE TABLE `cms_news` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `excerpt` text DEFAULT NULL,
  `content` longtext DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `news_date` date DEFAULT NULL,
  `is_featured` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `views` int(11) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `cms_news`
--

INSERT INTO `cms_news` (`id`, `title`, `category`, `excerpt`, `content`, `image_path`, `news_date`, `is_featured`, `is_active`, `views`, `created_at`, `updated_at`) VALUES
(1, 'MULTIBIZ Announces Strategic Partnership with Tech Giant', 'Partnership', 'We\'re excited to announce our new partnership that will revolutionize business solutions across the region.', 'Full content here...', 'https://placehold.co/400x250/0a1628/d4af55?text=Partnership', '2026-04-18', 1, 1, 0, '2026-04-18 03:06:56', '2026-04-18 03:06:56'),
(2, 'Introducing Our New Managed Print Solutions', 'Product Launch', 'Discover our latest managed print services designed to optimize your business operations and reduce costs.', 'Full content here...', 'https://placehold.co/400x250/1a4fa0/ffffff?text=Product+Launch', '2026-04-18', 1, 1, 0, '2026-04-18 03:06:56', '2026-04-18 03:06:56'),
(3, 'MULTIBIZ Wins Prestigious Industry Innovation Award', 'Award', 'Recognized for excellence in business solutions and customer service for the third consecutive year.', 'Full content here...', 'https://placehold.co/400x250/18151f/b8973a?text=Industry+Award', '2026-04-18', 1, 1, 0, '2026-04-18 03:06:56', '2026-04-18 03:06:56'),
(4, 'sadsa', 'dasdsa', 'dasdsad', 'asdasdas', 'images/cms/events/event_1779515567_6a1140af5ef2c.png', '2026-05-23', 0, 1, 0, '2026-05-23 05:47:35', '2026-05-23 05:52:47');

-- --------------------------------------------------------

--
-- Table structure for table `cms_sections`
--

CREATE TABLE `cms_sections` (
  `id` int(11) NOT NULL,
  `section_key` varchar(100) NOT NULL,
  `section_name` varchar(100) NOT NULL,
  `content_type` enum('text','html','image','gallery','slider') DEFAULT 'text',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `cms_sections`
--

INSERT INTO `cms_sections` (`id`, `section_key`, `section_name`, `content_type`, `created_at`, `updated_at`) VALUES
(1, 'stats', 'Statistics Bar', 'text', '2026-04-18 03:06:55', '2026-04-18 03:06:55'),
(2, 'partners_title', 'Partners Section Title', 'text', '2026-04-18 03:06:55', '2026-04-18 03:06:55'),
(3, 'testimonials_title', 'Testimonials Section Title', 'text', '2026-04-18 03:06:55', '2026-04-18 03:06:55'),
(4, 'featured_jobs_title', 'Featured Jobs Section Title', 'text', '2026-04-18 03:06:55', '2026-04-18 03:06:55'),
(5, 'footer', 'Footer Content', 'text', '2026-04-18 03:06:55', '2026-04-18 03:06:55'),
(6, 'seo', 'SEO Settings', 'text', '2026-04-18 03:06:55', '2026-04-18 03:06:55');

-- --------------------------------------------------------

--
-- Table structure for table `cms_testimonials`
--

CREATE TABLE `cms_testimonials` (
  `id` int(11) NOT NULL,
  `author_name` varchar(100) NOT NULL,
  `author_role` varchar(100) DEFAULT NULL,
  `company` varchar(100) DEFAULT NULL,
  `content` text NOT NULL,
  `rating` int(11) DEFAULT 5,
  `image_path` varchar(255) DEFAULT NULL,
  `sort_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `cms_testimonials`
--

INSERT INTO `cms_testimonials` (`id`, `author_name`, `author_role`, `company`, `content`, `rating`, `image_path`, `sort_order`, `is_active`, `created_at`) VALUES
(1, 'John Smith', 'CEO', 'Tech Solutions Inc.', 'MULTIBIZ INTERNATIONAL CORPORATION transformed our document management system. Their managed print services have saved us time and reduced our costs significantly. The team is professional and always available when we need support.', 5, NULL, 0, 1, '2026-04-18 03:06:56'),
(2, 'Sarah Johnson', 'CTO', 'Global Enterprises', 'The IT solutions provided by MULTIBIZ INTERNATIONAL CORPORATION have been exceptional. They helped us streamline our operations and implement systems that have increased our productivity by 40%. Highly recommended!', 5, NULL, 1, 1, '2026-04-18 03:06:56'),
(3, 'Michael Brown', 'Operations Director', 'Retail Corp', 'We\'ve been working with MULTIBIZ INTERNATIONAL CORPORATION for over 10 years and their service has always been top-notch. Their team understands our business needs and provides solutions that help us grow.', 5, NULL, 2, 1, '2026-04-18 03:06:56');

-- --------------------------------------------------------

--
-- Table structure for table `contact_inquiries`
--

CREATE TABLE `contact_inquiries` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `email` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL DEFAULT 'General Inquiry',
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('new','open','replied','closed') NOT NULL DEFAULT 'new',
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `contact_inquiries`
--

INSERT INTO `contact_inquiries` (`id`, `name`, `email`, `subject`, `message`, `is_read`, `status`, `ip_address`, `created_at`, `updated_at`) VALUES
(10, 'dsadsadas', 'reccapinto8@gmail.com', 'asdsadsadasd', 'sadsdsadasd asdasdasdasdsa', 1, 'open', '122.2.98.196', '2026-05-25 02:00:47', '2026-05-25 02:01:06'),
(13, 'recca', 'reccapinto8@gmail.com', 'asdsadsadsa sadsadsadsa', 'sadasdsadsad asdasdassadsad', 1, 'open', '122.2.98.196', '2026-05-25 02:01:27', '2026-05-25 02:01:42'),
(16, 'recca', 'reccapinto8@gmail.com', 'asdasdsad', 'asasdassa asdasd', 1, 'replied', '122.2.98.196', '2026-05-25 02:59:13', '2026-05-25 02:59:34'),
(17, 'recca', 'reccapinto8@gmail.com', 'asdasdsad', 'asasdassa asdasd', 0, 'new', '122.2.98.196', '2026-05-25 02:59:13', '2026-05-25 02:59:13'),
(18, 'recca', 'reccapinto8@gmail.com', 'asdasdsad', 'asasdassa asdasd', 0, 'new', '122.2.98.196', '2026-05-25 02:59:13', '2026-05-25 02:59:13');

-- --------------------------------------------------------

--
-- Table structure for table `contact_replies`
--

CREATE TABLE `contact_replies` (
  `id` int(11) NOT NULL,
  `inquiry_id` int(11) NOT NULL,
  `admin_id` int(11) DEFAULT NULL,
  `reply_text` text NOT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `email_sent` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `contact_replies`
--

INSERT INTO `contact_replies` (`id`, `inquiry_id`, `admin_id`, `reply_text`, `sent_at`, `email_sent`) VALUES
(10, 16, 1, 'assad', '2026-05-25 02:59:34', 1);

-- --------------------------------------------------------

--
-- Table structure for table `employers`
--

CREATE TABLE `employers` (
  `employer_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `company_name` varchar(255) DEFAULT NULL,
  `company_address` text DEFAULT NULL,
  `company_website` varchar(255) DEFAULT NULL,
  `industry` varchar(100) DEFAULT NULL,
  `company_size` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employers`
--

INSERT INTO `employers` (`employer_id`, `user_id`, `company_name`, `company_address`, `company_website`, `industry`, `company_size`) VALUES
(2, 9, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

CREATE TABLE `feedback` (
  `feedback_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `recommendation_id` int(11) DEFAULT NULL,
  `feedback_type` enum('job_recommendation','candidate_recommendation') NOT NULL,
  `rating` int(11) DEFAULT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `comments` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `interview_schedules`
--

CREATE TABLE `interview_schedules` (
  `id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `employer_id` int(11) NOT NULL,
  `interview_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `interview_type` varchar(50) NOT NULL COMMENT 'in-person, phone, video',
  `location` varchar(255) DEFAULT NULL,
  `meeting_link` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` varchar(20) DEFAULT 'scheduled' COMMENT 'scheduled, completed, cancelled, rescheduled',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `interview_schedules`
--

INSERT INTO `interview_schedules` (`id`, `application_id`, `employer_id`, `interview_date`, `start_time`, `end_time`, `interview_type`, `location`, `meeting_link`, `notes`, `status`, `created_at`, `updated_at`) VALUES
(2, 29, 2, '2026-01-31', '11:11:00', '23:11:00', 'in-person', 'asdsda', '', 'asdasds', 'scheduled', '2026-01-21 02:45:50', '2026-01-21 02:45:50'),
(3, 37, 2, '2026-01-22', '11:28:00', '12:28:00', 'in-person', 'office', '', 'adwd', 'scheduled', '2026-01-21 14:28:48', '2026-01-21 14:28:48'),
(4, 38, 2, '2026-01-30', '04:53:00', '16:54:00', 'in-person', '', '', 'dsfdsf', 'scheduled', '2026-01-23 01:15:34', '2026-01-23 01:15:34'),
(5, 46, 2, '2026-05-26', '11:09:00', '14:09:00', 'in-person', 'saasassad', '', 'asdsasa', 'scheduled', '2026-05-25 03:09:24', '2026-05-25 03:09:24');

-- --------------------------------------------------------

--
-- Table structure for table `job_postings`
--

CREATE TABLE `job_postings` (
  `job_id` int(11) NOT NULL,
  `employer_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `requirements` text DEFAULT NULL,
  `skills_required` text DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `employment_type` enum('full-time','part-time','contract','internship') DEFAULT 'full-time',
  `salary_range` varchar(100) DEFAULT NULL,
  `status` enum('active','closed','draft') DEFAULT 'active',
  `target_qualifications` text DEFAULT NULL COMMENT 'Comma-separated qualification IDs',
  `posted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `job_postings`
--

INSERT INTO `job_postings` (`job_id`, `employer_id`, `title`, `description`, `requirements`, `skills_required`, `location`, `employment_type`, `salary_range`, `status`, `target_qualifications`, `posted_at`, `updated_at`) VALUES
(8, 2, 'Accounting Systems Analyst', 'Analyzes, supports, and improves accounting systems by automating financial processes.', 'Bachelor’s degree in AIS or related\r\n\r\nFamiliar with accounting workflows\r\nKnowledge in SAP/QuickBooks\r\nStrong analytical thinking\r\nBasic SQL knowledge', 'SAP, QuickBooks, SQL, Data Analysis', 'Ortigas, Pasig City', 'full-time', '₱20,000 – ₱30,000', 'active', NULL, '2025-11-21 07:59:03', '2025-11-21 07:59:03'),
(9, 2, 'PR & Communications Specialist', 'Degree in Advertising, PR, or Communications\r\n\r\nStrong writing skills\r\n\r\nKnowledge in PR strategies\r\n\r\nCreative thinker\r\n\r\nSkilled in digital content creation', 'PR & Communications Specialist', 'PR Writing, Strategy, Social Media, Copywriting', 'Quezon City', 'part-time', '₱25,000 – ₱40,000', 'active', NULL, '2025-11-21 08:02:17', '2025-11-21 08:02:17'),
(10, 2, 'Business Operations Assistant', 'Supports daily company operations, assists in administrative tasks, and coordinates with departments.', 'Degree in Business Administration\r\nExcellent organizational skills\r\nProficient in MS Office\r\nGood communication skills\r\nCan multitask', 'Communication, MS Office, Coordination, Time Management', 'Mandaluyong City', 'internship', '₱18,000 – ₱25,000', 'active', NULL, '2025-11-21 08:08:51', '2025-11-21 08:08:51'),
(12, 2, 'Management Trainee', 'Trains under various departments to prepare for leadership and management roles.', 'Degree in Business Management\r\nLeadership potential\r\nStrong analytical skills\r\nExcellent communication\r\nFast learner', 'Leadership, Communication, Problem-Solving', 'BGC, Taguig City', 'part-time', '₱18,000 – ₱28,000', 'active', NULL, '2025-11-21 08:22:09', '2025-11-21 08:22:09'),
(13, 2, 'Software Developer', 'Develops and maintains software applications and collaborates with the development team.', 'Degree in Computer Science\r\nKnowledge of programming languages\r\nFamiliar with MySQL\r\nUnderstanding of SDLC', 'Java, PHP, Python, React, MySQL', 'Cebu City', 'full-time', '₱25,000 – ₱50,000', 'active', NULL, '2025-11-21 08:25:05', '2025-11-21 08:25:05'),
(15, 2, 'Labor Relations Assistant', 'Assists in handling employee relations, labor policies, and workplace documentation.', 'Degree in Industrial Relations or HRM\r\n\r\nKnowledge of labor laws\r\n\r\nGood communication skills\r\n\r\nExcellent documentation skills\r\n\r\nTrustworthy and confidential', 'Documentation, Labor Law Knowledge, Communication', 'Manila City', 'full-time', '₱18,000 – ₱28,000', 'active', NULL, '2025-11-21 08:29:31', '2025-11-22 13:05:31'),
(16, 2, 'b', 'asdasd', 'asdasasdas', 'asdas', 'asdasd', 'full-time', 'asdas', 'draft', NULL, '2025-11-22 12:39:08', '2025-11-26 23:20:25'),
(17, 2, 'B', 'A', 'A', 'A', 'A', 'full-time', '50,000', 'draft', NULL, '2025-11-26 02:57:15', '2025-11-26 23:20:17'),
(18, 2, 'm', 'z', 'z', 'javascript', 'awdwsad', 'full-time', '70,000', 'draft', NULL, '2025-11-26 04:30:09', '2025-11-26 23:20:09'),
(19, 2, 'Junior Web Developer', 'aaa', 'aaaa', 'javascript, react', 'manila', 'full-time', '', 'draft', NULL, '2025-11-26 14:27:19', '2026-01-22 16:12:52'),
(20, 2, 'Web Dev', 'We are looking for full time web developer', '', 'JavaScript, PHP, MySQL', 'Manila', 'full-time', '25,000 - 50,000', 'active', NULL, '2025-11-26 23:56:31', '2025-11-26 23:56:31'),
(21, 2, 'Studio Coordinator', 'Orchestrated all client scheduling and calendar management, efficiently coordinating\r\nappointments, photoshoots, and project timelines to ensure a seamless workflow and optimal resource utilization for the creative team.\r\nActed as the primary liaison between clients and the creative team, facilitating clear communication, managing expectations, and ensuring client\r\nsatisfaction from initial inquiry through to final project delivery', 'resume, nbi clearance', 'Project Management, Layout Design, Social Media Marketing and Advertising, multitasking, time management, leladership, initiative', 'manila', 'full-time', '25,000 - 50,000', 'active', NULL, '2026-01-21 14:17:08', '2026-01-21 14:17:08'),
(22, 2, 'Senior web developer', 'back end', 'qqq', 'javascript, react', 'bulacan', 'full-time', '25,000 - 50,000', 'active', NULL, '2026-04-16 03:11:09', '2026-04-16 03:11:09'),
(23, 2, 'Technical Engineer', 'field printer technician', 'dsad', 'dasdsad', 'dsasa', 'full-time', '₱18,000 – ₱25,000', 'active', NULL, '2026-05-25 03:02:10', '2026-05-25 03:02:10');

-- --------------------------------------------------------

--
-- Table structure for table `job_qualification_mapping`
--

CREATE TABLE `job_qualification_mapping` (
  `mapping_id` int(11) NOT NULL,
  `job_id` int(11) NOT NULL,
  `qualification_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `job_qualification_mapping`
--

INSERT INTO `job_qualification_mapping` (`mapping_id`, `job_id`, `qualification_id`, `created_at`) VALUES
(20, 8, 5, '2025-11-21 07:59:03'),
(21, 8, 6, '2025-11-21 07:59:03'),
(22, 8, 18, '2025-11-21 07:59:03'),
(23, 8, 16, '2025-11-21 07:59:03'),
(24, 8, 7, '2025-11-21 07:59:03'),
(25, 9, 3, '2025-11-21 08:02:17'),
(26, 9, 2, '2025-11-21 08:02:17'),
(27, 9, 1, '2025-11-21 08:02:17'),
(28, 9, 15, '2025-11-21 08:02:17'),
(29, 10, 9, '2025-11-21 08:08:51'),
(30, 10, 10, '2025-11-21 08:08:51'),
(31, 10, 4, '2025-11-21 08:08:51'),
(32, 10, 12, '2025-11-21 08:08:51'),
(33, 10, 14, '2025-11-21 08:08:51'),
(34, 10, 1, '2025-11-21 08:08:51'),
(35, 10, 15, '2025-11-21 08:08:51'),
(44, 12, 9, '2025-11-21 08:22:09'),
(45, 12, 10, '2025-11-21 08:22:09'),
(46, 12, 4, '2025-11-21 08:22:09'),
(47, 12, 12, '2025-11-21 08:22:09'),
(48, 12, 14, '2025-11-21 08:22:09'),
(49, 12, 7, '2025-11-21 08:22:09'),
(50, 12, 1, '2025-11-21 08:22:09'),
(51, 12, 15, '2025-11-21 08:22:09'),
(52, 13, 17, '2025-11-21 08:25:05'),
(53, 13, 19, '2025-11-21 08:25:05'),
(54, 13, 2, '2025-11-21 08:25:05'),
(55, 13, 18, '2025-11-21 08:25:05'),
(56, 13, 16, '2025-11-21 08:25:05'),
(69, 15, 12, '2025-11-22 12:44:53'),
(70, 15, 14, '2025-11-22 12:44:53'),
(71, 15, 15, '2025-11-22 12:44:53'),
(72, 15, 13, '2025-11-22 12:44:53'),
(76, 16, 5, '2025-11-22 13:52:40'),
(79, 17, 5, '2025-11-26 02:58:31'),
(80, 18, 6, '2025-11-26 04:30:09'),
(81, 19, 6, '2025-11-26 14:27:19'),
(82, 20, 17, '2025-11-26 23:56:31'),
(83, 20, 18, '2025-11-26 23:56:31'),
(84, 20, 16, '2025-11-26 23:56:31'),
(85, 21, 18, '2026-01-21 14:17:08'),
(86, 22, 6, '2026-04-16 03:11:09'),
(87, 23, 9, '2026-05-25 03:02:10');

-- --------------------------------------------------------

--
-- Table structure for table `job_recommendations`
--

CREATE TABLE `job_recommendations` (
  `recommendation_id` int(11) NOT NULL,
  `applicant_id` int(11) NOT NULL,
  `job_id` int(11) NOT NULL,
  `recommendation_score` decimal(5,2) NOT NULL,
  `reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `message_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`message_id`, `sender_id`, `receiver_id`, `message`, `is_read`, `created_at`) VALUES
(54, 52, 9, 'hi', 1, '2026-01-21 14:36:28'),
(55, 9, 52, 'hi', 1, '2026-01-21 14:36:53'),
(56, 40, 9, 'hi', 1, '2026-05-22 00:43:55');

-- --------------------------------------------------------

--
-- Table structure for table `ml_application_screening`
--

CREATE TABLE `ml_application_screening` (
  `screening_id` int(11) NOT NULL,
  `application_id` int(11) NOT NULL,
  `ml_score` decimal(5,2) NOT NULL,
  `prediction_class` enum('high','medium','low') NOT NULL,
  `confidence_score` decimal(5,4) NOT NULL,
  `features_used` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`features_used`)),
  `model_version` varchar(50) DEFAULT 'v1.0',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ml_feature_importance`
--

CREATE TABLE `ml_feature_importance` (
  `feature_id` int(11) NOT NULL,
  `feature_name` varchar(100) NOT NULL,
  `importance_score` decimal(5,4) NOT NULL,
  `model_version` varchar(50) DEFAULT 'v1.0',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ml_feature_importance`
--

INSERT INTO `ml_feature_importance` (`feature_id`, `feature_name`, `importance_score`, `model_version`, `created_at`) VALUES
(1, 'skills_match', 0.3000, 'v1.0', '2025-11-21 01:08:41'),
(2, 'experience_match', 0.2500, 'v1.0', '2025-11-21 01:08:41'),
(3, 'qualifications_match', 0.2000, 'v1.0', '2025-11-21 01:08:41'),
(4, 'resume_quality', 0.1500, 'v1.0', '2025-11-21 01:08:41'),
(5, 'education_match', 0.1000, 'v1.0', '2025-11-21 01:08:41'),
(6, 'skills_match', 0.3000, 'v1.0', '2025-11-21 01:09:09'),
(7, 'experience_match', 0.2500, 'v1.0', '2025-11-21 01:09:09'),
(8, 'qualifications_match', 0.2000, 'v1.0', '2025-11-21 01:09:09'),
(9, 'resume_quality', 0.1500, 'v1.0', '2025-11-21 01:09:09'),
(10, 'education_match', 0.1000, 'v1.0', '2025-11-21 01:09:09'),
(11, 'skills_match', 0.3000, 'v1.0', '2025-11-21 01:09:10'),
(12, 'experience_match', 0.2500, 'v1.0', '2025-11-21 01:09:10'),
(13, 'qualifications_match', 0.2000, 'v1.0', '2025-11-21 01:09:10'),
(14, 'resume_quality', 0.1500, 'v1.0', '2025-11-21 01:09:10'),
(15, 'education_match', 0.1000, 'v1.0', '2025-11-21 01:09:10'),
(16, 'skills_match', 0.3000, 'v1.0', '2025-11-21 01:09:19'),
(17, 'experience_match', 0.2500, 'v1.0', '2025-11-21 01:09:19'),
(18, 'qualifications_match', 0.2000, 'v1.0', '2025-11-21 01:09:19'),
(19, 'resume_quality', 0.1500, 'v1.0', '2025-11-21 01:09:19'),
(20, 'education_match', 0.1000, 'v1.0', '2025-11-21 01:09:19'),
(21, 'skills_match', 0.3000, 'v1.0', '2025-11-21 01:09:27'),
(22, 'experience_match', 0.2500, 'v1.0', '2025-11-21 01:09:27'),
(23, 'qualifications_match', 0.2000, 'v1.0', '2025-11-21 01:09:27'),
(24, 'resume_quality', 0.1500, 'v1.0', '2025-11-21 01:09:27'),
(25, 'education_match', 0.1000, 'v1.0', '2025-11-21 01:09:27');

-- --------------------------------------------------------

--
-- Table structure for table `ml_model_performance`
--

CREATE TABLE `ml_model_performance` (
  `performance_id` int(11) NOT NULL,
  `model_version` varchar(50) NOT NULL,
  `accuracy_score` decimal(5,4) NOT NULL,
  `precision_score` decimal(5,4) NOT NULL,
  `recall_score` decimal(5,4) NOT NULL,
  `f1_score` decimal(5,4) NOT NULL,
  `training_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text DEFAULT NULL,
  `type` enum('application','recommendation','system','job_update') DEFAULT 'system',
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notification_id`, `user_id`, `title`, `message`, `type`, `is_read`, `created_at`) VALUES
(13, 9, 'New Application', 'A new application has been submitted for: Cybersecurity Analyst', 'application', 0, '2025-11-21 10:28:38'),
(14, 9, 'New Application', 'A new application has been submitted for: Cybersecurity Analyst', 'application', 0, '2025-11-22 06:18:32'),
(15, 9, 'New Application', 'A new application has been submitted for: Labor Relations Assistant', 'application', 0, '2025-11-22 07:57:20'),
(16, 9, 'New Application', 'A new application has been submitted for: Management Trainee', 'application', 0, '2025-11-22 07:57:34'),
(19, 9, 'New Application', 'A new application has been submitted for: Software Developer', 'application', 0, '2025-11-22 09:08:13'),
(20, 9, 'New Application', 'A new application has been submitted for: Labor Relations Assistant', 'application', 0, '2025-11-22 09:54:05'),
(21, 9, 'New Application', 'A new application has been submitted for: Cybersecurity Analyst', 'application', 0, '2025-11-22 09:54:14'),
(22, 9, 'New Application', 'A new application has been submitted for: Software Developer', 'application', 0, '2025-11-22 09:54:21'),
(23, 9, 'New Application', 'A new application has been submitted for: Accounting Systems Analyst', 'application', 0, '2025-11-22 10:04:57'),
(25, 9, 'New Application', 'A new application has been submitted for: Accounting Systems Analyst', 'application', 0, '2025-11-22 13:43:12'),
(26, 9, 'New Application', 'A new application has been submitted for: Software Developer', 'application', 0, '2025-11-22 13:50:42'),
(28, 9, 'New Application', 'A new application has been submitted for: b', 'application', 0, '2025-11-25 08:32:03'),
(29, 9, 'New Application', 'A new application has been submitted for: Accounting Systems Analyst', 'application', 0, '2025-11-26 03:09:26'),
(31, 9, 'New Application', 'A new application has been submitted for: m', 'application', 0, '2025-11-26 07:45:04'),
(32, 9, 'New Application', 'A new application has been submitted for: Accounting Systems Analyst', 'application', 0, '2025-11-26 14:24:12'),
(35, 9, 'New Application', 'A new application has been submitted for: Junior Web Developer', 'application', 0, '2025-11-26 23:23:06'),
(36, 9, 'New Application', 'A new application has been submitted for: PR & Communications Specialist', 'application', 0, '2025-11-26 23:23:19'),
(37, 9, 'New Application', 'A new application has been submitted for: Management Trainee', 'application', 0, '2025-11-26 23:25:27'),
(38, 9, 'New Application', 'A new application has been submitted for: Junior Web Developer', 'application', 0, '2025-11-26 23:28:29'),
(39, 9, 'New Application', 'A new application has been submitted for: Accounting Systems Analyst', 'application', 0, '2025-11-26 23:28:49'),
(40, 9, 'New Application', 'A new application has been submitted for: Web Dev', 'application', 0, '2025-11-26 23:58:55'),
(41, 9, 'New Application', 'A new application has been submitted for: Web Dev', 'application', 0, '2025-11-27 00:00:30'),
(43, 9, 'New Application', 'A new application has been submitted for: Web Dev', 'application', 0, '2025-11-27 00:19:48'),
(44, 9, 'New Application', 'A new application has been submitted for: Labor Relations Assistant', 'application', 0, '2025-11-27 00:36:55'),
(46, 42, 'Application Update', 'Your application status has been updated to: reviewed', 'application', 0, '2026-01-04 09:59:37'),
(49, 42, 'Application Update', 'Your application status has been updated to: accepted by Employer user', 'application', 0, '2026-01-04 10:00:19'),
(51, 40, 'Application Update', 'Your application status has been updated to: rejected by Employer user', 'application', 0, '2026-01-04 10:00:36'),
(53, 42, 'Application Update', 'Your application status has been updated to: accepted by Employer user - Remarks: You are accepted', 'application', 0, '2026-01-07 12:35:04'),
(54, 42, 'Application Update', 'Your application status has been updated to: accepted by Employer user - Remarks: You are accepted', 'application', 0, '2026-01-07 12:35:04'),
(58, 42, 'Application Update', 'Your application status has been updated to: rejected by Employer user - Remarks: ayawkol', 'application', 0, '2026-01-07 13:19:29'),
(59, 42, 'Application Update', 'Your application status has been updated to: rejected by Employer user - Remarks: adsds', 'application', 0, '2026-01-07 13:19:41'),
(60, 42, 'Application Update', 'Your application status has been updated to: shortlisted - Remarks: adsddasda', 'application', 0, '2026-01-07 13:19:52'),
(62, 9, 'New Application', 'A new application has been submitted for: Web Dev', 'application', 0, '2026-01-12 03:34:08'),
(63, 9, 'New Application', 'A new application has been submitted for: Accounting Systems Analyst', 'application', 0, '2026-01-19 02:11:48'),
(64, 9, 'New Application', 'A new application has been submitted for: Web Dev', 'application', 0, '2026-01-19 02:21:05'),
(65, 52, 'Application Update', 'Your application status has been updated to: accepted by Employer user - Remarks: sdfdfds', 'application', 0, '2026-01-21 02:05:48'),
(67, 9, 'New Application', 'A new application has been submitted for: Accounting Systems Analyst', 'application', 0, '2026-01-21 02:44:50'),
(68, 40, 'Interview Scheduled', 'An interview has been scheduled for your application: January 31, 2026 11:11 AM at asdsda (Type: in-person)', '', 0, '2026-01-21 02:45:50'),
(69, 40, 'Application Update', 'Your application status has been updated to: interviewed - Remarks: dasdsad', 'application', 0, '2026-01-21 02:46:00'),
(70, 9, 'New Application', 'A new application has been submitted for: Studio Coordinator', 'application', 0, '2026-01-21 14:22:10'),
(71, 52, 'Interview Scheduled', 'An interview has been scheduled for your application: January 22, 2026 11:28 AM at office (Type: in-person)', '', 0, '2026-01-21 14:28:48'),
(72, 52, 'Application Update', 'Your application status has been updated to: interviewed - Remarks: sched for interview', 'application', 0, '2026-01-21 14:30:50'),
(73, 52, 'New Feedback on Your Application', 'Employer user has provided feedback on your application for Studio Coordinator. Check your dashboard to view it!', 'application', 0, '2026-01-21 14:31:37'),
(74, 9, 'New Application', 'A new application has been submitted for: Studio Coordinator', 'application', 0, '2026-01-22 05:24:07'),
(75, 55, 'Application Update', 'Your application status has been updated to: scheduled for interview - Remarks: asdasdsa', 'application', 0, '2026-01-22 05:26:39'),
(76, 55, 'Application Update', 'Your application status has been updated to: scheduled for interview - Remarks: asdasdsa', 'application', 0, '2026-01-22 05:55:22'),
(77, 55, 'Application Update', 'Your application status has been updated to: scheduled for interview - Remarks: dfasdf', 'application', 0, '2026-01-22 06:01:15'),
(78, 55, 'Application Update', 'Your application status has been updated to: accepted by Employer user - Remarks: tanggap ka na', 'application', 0, '2026-01-22 06:16:51'),
(79, 52, 'Application Update', 'Your application status has been updated to: scheduled for interview - Remarks: .', 'application', 0, '2026-01-22 07:48:16'),
(80, 9, 'New Application', 'A new application has been submitted for: Studio Coordinator', 'application', 0, '2026-01-22 08:20:18'),
(81, 9, 'New Application', 'A new application has been submitted for: Web Dev', 'application', 0, '2026-01-22 11:37:23'),
(82, 55, 'Application Update', 'Your application status has been updated to: scheduled for interview - Remarks: dsdas', 'application', 0, '2026-01-22 13:45:28'),
(83, 9, 'New Application', 'A new application has been submitted for: Software Developer', 'application', 0, '2026-01-22 21:23:24'),
(84, 9, 'New Application', 'A new application has been submitted for: Labor Relations Assistant', 'application', 0, '2026-01-22 21:24:26'),
(85, 9, 'New Application', 'A new application has been submitted for: Management Trainee', 'application', 0, '2026-01-22 21:25:20'),
(86, 55, 'Interview Scheduled', 'An interview has been scheduled for your application: January 30, 2026 04:53 AM (Type: in-person)', '', 0, '2026-01-23 01:15:34'),
(87, 55, 'Application Update', 'Your application status has been updated to: scheduled for interview - Remarks: dsfsdf', 'application', 0, '2026-01-23 01:15:42'),
(88, 55, 'Application Update', 'Your application status has been updated to: accepted by Employer user - Remarks: sdsad', 'application', 0, '2026-01-23 02:58:12'),
(89, 9, 'New Application', 'A new application has been submitted for: Studio Coordinator', 'application', 0, '2026-01-23 03:10:49'),
(90, 9, 'New Application', 'A new application has been submitted for: Studio Coordinator', 'application', 0, '2026-02-19 14:17:57'),
(91, 9, 'New Application', 'A new application has been submitted for: Technical Engineer', 'application', 0, '2026-05-25 03:06:40'),
(92, 40, 'Interview Scheduled', 'An interview has been scheduled for your application: May 26, 2026 11:09 AM at saasassad (Type: in-person)', '', 0, '2026-05-25 03:09:24');

-- --------------------------------------------------------

--
-- Table structure for table `qualifications`
--

CREATE TABLE `qualifications` (
  `qualification_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `qualifications`
--

INSERT INTO `qualifications` (`qualification_id`, `name`, `description`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Bachelor of Science in Marketing Management', 'Four-year degree covering marketing strategies, consumer behavior, brand management, and market analysis', 'active', '2025-11-20 16:24:20', '2025-11-20 16:24:20'),
(2, 'Bachelor of Science in Digital Marketing', 'Degree focusing on online marketing, social media strategies, SEO, content marketing, and digital advertising', 'active', '2025-11-20 16:24:20', '2025-11-20 16:24:20'),
(3, 'Bachelor of Science in Advertising and Public Relations', 'Degree covering advertising campaigns, public relations, media planning, and brand communication', 'active', '2025-11-20 16:24:20', '2025-11-20 16:24:20'),
(4, 'Bachelor of Science in Entrepreneurship', 'Degree in business creation, innovation, startup management, and entrepreneurial skills', 'active', '2025-11-20 16:24:20', '2025-11-20 16:24:20'),
(5, 'Bachelor of Science in Accountancy', 'Professional degree in accounting principles, financial reporting, auditing, and tax preparation', 'active', '2025-11-20 16:24:20', '2025-11-20 16:24:20'),
(6, 'Bachelor of Science in Accounting Information System', 'Degree combining accounting with information systems, focusing on computerized accounting and financial data management', 'active', '2025-11-20 16:24:20', '2025-11-20 16:24:20'),
(7, 'Bachelor of Science in Management Accounting', 'Degree in managerial accounting, cost analysis, budgeting, and financial decision-making for organizations', 'active', '2025-11-20 16:24:20', '2025-11-20 16:24:20'),
(8, 'Bachelor of Science in Internal Auditing', 'Degree focusing on internal audit processes, risk assessment, compliance, and organizational controls', 'active', '2025-11-20 16:24:20', '2025-11-20 16:24:20'),
(9, 'Bachelor of Science in Business Administration', 'Four-year degree covering management, finance, marketing, and business operations', 'active', '2025-11-20 16:24:20', '2025-11-20 16:24:20'),
(10, 'Bachelor of Science in Business Management', 'Degree in organizational management, leadership, strategic planning, and business operations', 'active', '2025-11-20 16:24:20', '2025-11-20 16:24:20'),
(11, 'Bachelor of Science in Economics', 'Degree in economic theory, market analysis, financial systems, and economic policy', 'active', '2025-11-20 16:24:20', '2025-11-20 16:24:20'),
(12, 'Bachelor of Science in Human Resource Management', 'Degree focusing on HR management, recruitment, employee relations, and organizational development', 'active', '2025-11-20 16:24:20', '2025-11-20 16:24:20'),
(13, 'Bachelor of Science in Psychology (with HR specialization)', 'Psychology degree with specialization in human resources, organizational behavior, and employee psychology', 'active', '2025-11-20 16:24:20', '2025-11-20 16:24:20'),
(14, 'Bachelor of Science in Industrial Relations', 'Degree in labor relations, employee-employer relations, collective bargaining, and workplace policies', 'active', '2025-11-20 16:24:20', '2025-11-20 16:24:20'),
(15, 'Bachelor of Science in Organizational Development', 'Degree in organizational change, development strategies, team building, and workplace improvement', 'active', '2025-11-20 16:24:20', '2025-11-20 16:24:20'),
(16, 'Bachelor of Science in Information Technology', 'Four-year degree in IT covering systems administration, networking, software development, and technology management', 'active', '2025-11-20 16:24:20', '2025-11-20 16:24:20'),
(17, 'Bachelor of Science in Computer Science', 'Degree in computer science covering programming, algorithms, data structures, and software engineering', 'active', '2025-11-20 16:24:20', '2025-11-20 16:24:20'),
(18, 'Bachelor of Science in Information Systems', 'Degree combining business and technology, focusing on information systems design, database management, and business technology solutions', 'active', '2025-11-20 16:24:20', '2025-11-20 16:24:20'),
(19, 'Bachelor of Science in Cybersecurity', 'Degree in cybersecurity, network security, information security, and protection of digital assets', 'active', '2025-11-20 16:24:20', '2025-11-20 16:24:20'),
(20, 'Information Technology', 'Careers in software development, IT support, networking, and technology', 'active', '2025-11-26 14:03:37', '2025-11-26 14:03:37'),
(21, 'Business Administration', 'Management, administration, and business operations careers', 'active', '2025-11-26 14:03:37', '2025-11-26 14:03:37'),
(22, 'Healthcare', 'Medical, nursing, healthcare support and administration roles', 'active', '2025-11-26 14:03:37', '2025-11-26 14:03:37'),
(23, 'Engineering', 'Civil, mechanical, electrical and other engineering disciplines', 'active', '2025-11-26 14:03:37', '2025-11-26 14:03:37'),
(24, 'Education', 'Teaching, training, and educational administration', 'active', '2025-11-26 14:03:37', '2025-11-26 14:03:37'),
(25, 'Sales and Marketing', 'Sales, marketing, advertising and customer service roles', 'active', '2025-11-26 14:03:37', '2025-11-26 14:03:37'),
(26, 'Hospitality', 'Hotel, restaurant, tourism and service industry careers', 'active', '2025-11-26 14:03:37', '2025-11-26 14:03:37'),
(27, 'Skilled Trades', 'Construction, manufacturing, automotive and technical trades', 'active', '2025-11-26 14:03:37', '2025-11-26 14:03:37');

-- --------------------------------------------------------

--
-- Table structure for table `resume_analysis`
--

CREATE TABLE `resume_analysis` (
  `analysis_id` int(11) NOT NULL,
  `applicant_id` int(11) NOT NULL,
  `resume_file` varchar(255) NOT NULL,
  `extracted_text` text DEFAULT NULL,
  `skills_extracted` text DEFAULT NULL,
  `education_extracted` text DEFAULT NULL,
  `experience_extracted` text DEFAULT NULL,
  `qualifications_extracted` text DEFAULT NULL,
  `analysis_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `saved_jobs`
--

CREATE TABLE `saved_jobs` (
  `id` int(11) NOT NULL,
  `applicant_id` int(11) NOT NULL,
  `job_id` int(11) NOT NULL,
  `saved_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `skills`
--

CREATE TABLE `skills` (
  `skill_id` int(11) NOT NULL,
  `skill_name` varchar(255) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('applicant','employer','admin') NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `status` enum('active','inactive','suspended') DEFAULT 'active',
  `created_by_admin` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `email`, `password`, `role`, `first_name`, `last_name`, `phone`, `status`, `created_by_admin`, `created_at`, `updated_at`) VALUES
(1, 'admin@gmail.com', '$2y$10$y0PZTfGsIYkmsT8o7Zj1p.ucHaiQCrTaXrvbwjiN2BfFu73j5HV5y', 'admin', 'Admin', 'User', '', 'active', 0, '2025-11-20 16:23:53', '2025-11-22 15:56:23'),
(9, 'employer@gmail.com', '$2y$10$GsCn78K89OHfpjDNtBLEauakxCrlOLHFAeKsSzEDJTJaHf/CgPAsW', 'employer', 'Employer', 'user', '', 'active', 1, '2025-11-21 07:13:32', '2025-11-21 07:13:44'),
(40, 'recca@gmail.com', '$2y$10$nitelc..zjXNKSFbVFooguXGFgN3VzGYzl.Z/y4d9k8vOqOCT6eQO', 'applicant', 'Recca', 'Pinto', '09231234507', 'active', 0, '2025-11-26 21:06:58', '2025-11-26 21:06:58'),
(42, 'mark@gmail.com', '$2y$10$6YEN3yfA3QFktA87k/.BDe44djO.GWihyjBsXZ4ItY82jQX1Mhu/2', 'applicant', 'Mark', 'Pausta', '09181234502', 'active', 0, '2025-11-26 21:08:10', '2025-11-26 21:08:10'),
(45, 'vedatwindrive@gmail.com', '$2y$10$kJJHeWpwUkrFUEW3anWBJuafeG7pxfwmR0SKMcSS.kcqwMer1fevG', 'applicant', 'Ian', 'Francis', '', 'active', 0, '2025-11-27 00:10:21', '2025-11-27 00:10:21'),
(47, 'rci.maylyn.torino@gmail.com', '$2y$10$iBngYI7m8Ex29btFUkKi3.a5MDHKS5jeiiR7F7UkGHP2V8Up9QD7a', 'applicant', 'Maylyn', 'Torino', '', 'active', 0, '2025-11-27 00:14:46', '2025-11-27 00:14:46'),
(51, 'jobseeker@gmail.com', '$2y$10$7NNws/NgBKoXtkEStv6/Ju1UwvdxEX4cE7vhiWfAG.uVG5jJYQ4gm', 'applicant', 'Job', 'Seeker', '09505821348', 'active', 0, '2026-01-08 03:48:47', '2026-01-08 03:48:47'),
(52, 'izeldlrsa@gmail.com', '$2y$10$6ARRNwhXzJFQrzps03qSlODJzVBixk9u/46ZG1FYff8ElunOvKf5W', 'applicant', 'Izel', 'Dela rosa', '', 'active', 0, '2026-01-10 14:20:36', '2026-01-10 14:20:36'),
(53, 'user8@gmail.com', '$2y$10$uAWpc.cV73.SDfl/vQbpZe5dWIqDVpVnYT8Jb2TqS.AFJzeFBaNOG', 'applicant', 'User', 'User', '', 'active', 0, '2026-01-19 02:17:11', '2026-01-19 02:17:11'),
(54, 'klois1@gmail.com', '$2y$10$KSHN4Md0ir.zijDFnO.E1OYPmM0L5tfcZZF6499xtn2Pak3aGnOci', 'applicant', 'Klois', 'Descallar', '', 'active', 0, '2026-01-21 04:10:39', '2026-01-21 04:10:39'),
(55, 'testing@gmail.com', '$2y$10$8jiGol3epeaMDCmCnzFEwOejJkWxxfvyp1idfBOxF7eMK7hJuaXaK', 'applicant', 'Testing', 'User', '', 'active', 0, '2026-01-22 01:18:18', '2026-01-22 01:18:18'),
(58, 'jobseeker1@gmail.com', '$2y$10$RLfNeX6j7KcPXA83Er0g2ej.l1Gxeba51UGLvJ2bzrfPpSjGyYrSW', 'applicant', 'Job', 'Seeker', '', 'active', 0, '2026-01-22 17:37:06', '2026-01-22 17:37:06'),
(59, 'jobseeker123@gmail.com', '$2y$10$0CILAg1iWS7lfJWg3oXGv.CNdMEYkRTW4oTMiLa/aO9BlMBL7CUTm', 'applicant', 'Job', 'Seeker', '', 'active', 0, '2026-01-22 17:37:38', '2026-01-22 17:37:38'),
(60, 'trisha@gmail.com', '$2y$10$Nd0soFbh/iSSd5MMRpybZeaIHS4u55Df3lpHZEKL.Qmenq9N92G.i', 'applicant', 'Job', 'Seeker', '', 'active', 0, '2026-01-22 17:38:00', '2026-01-22 17:38:00'),
(61, 'gab@gmail.com', '$2y$10$iXD4WXzku.9khTu/H4c3HuslkxqWiCv1WnOr/5W3AuzP1LFjTJkSq', 'applicant', 'Gab', 'Dalmacio', '', 'active', 0, '2026-01-23 03:04:16', '2026-01-23 03:04:16'),
(62, 'reccapinto@gmail.com', '$2y$10$UaAnqYx3MtWSwiJWK3pR4eb4OkZ0RGO7gmkTiBiGTiBQmO4NPPEKu', 'applicant', 'Recca', 'Pinto', '542435252435', 'active', 0, '2026-02-09 07:25:20', '2026-02-09 07:25:20'),
(63, 'klois@gmail.com', '$2y$10$VmeyKSkejmLI4jza49UsiuGAx7eOvpewzOTNPYkhXc79DyV7oVvNG', 'applicant', 'Klois', 'Descallar', '', 'active', 0, '2026-02-11 05:31:38', '2026-02-11 05:31:38'),
(64, 'user@gmail.com', '$2y$10$ZFcrLcfqs1/9qnf6eYbplOgiS8Bf9Ke6mxUVX2FBgf0PuzR0SGFz.', 'applicant', 'Testing', 'User', '09171234501', 'active', 0, '2026-02-19 13:50:05', '2026-02-19 13:50:05'),
(65, 'hahaha@gmail.com', '$2y$10$JNTv4CSGLmvPvBGqb/f7SOAegXl.hsHLqxR9HBLROUubOkm7jE9X2', 'applicant', 'Haha', 'Haha', '09171234501', 'active', 0, '2026-02-19 13:50:34', '2026-02-19 13:50:34'),
(66, 'gojo@gmail.com', '$2y$10$/YNY6Zy4n3631cxihLnPbex3.1HR48Rj54F5BS//fLI/.qrXAktZG', 'applicant', 'Gojo', 'Aasd', '054534543545', 'active', 0, '2026-02-19 13:52:09', '2026-02-19 13:52:09'),
(67, 'rainier@gmail.com', '$2y$10$uMjyF3NsqQuMct5QOi5ij.a80M3CTvdLZakyIAcAMiu/5pLpfsecu', 'applicant', 'Recca', 'Pinto', '09453453453', 'active', 0, '2026-02-20 05:06:30', '2026-02-20 05:06:30'),
(68, 'izeltrisha.dr@gmail.com', '$2y$10$yh4md0d0y0Iq4bSIq4xb9ODI6gHqbgfaJOxAWeqrfKenV0zzwoJze', 'applicant', 'Trisha', 'Dela rosa', '', 'active', 0, '2026-04-16 03:18:20', '2026-04-16 03:18:20'),
(69, 'riyurika08@gmail.com', '$2y$10$7gL9nxFgpkjGcbG6GIyEKOwRA4gORHWyIRYLwP8/4ChAfxHM9Oel6', 'applicant', 'Recca', 'Pinto', '09231234507', 'active', 0, '2026-05-21 05:46:13', '2026-05-21 05:46:13'),
(70, 'reccapinto8@gmail.com', '$2y$10$FhZuCBLtqwParDeTU5s1quV.toImmUmLNMTWz5IJDd73cCu8.GkVW', 'applicant', 'Recca', 'Pinto', '09231234507', 'active', 0, '2026-05-21 05:48:11', '2026-05-21 05:48:11'),
(71, 'rci.bsis.pintorainierrecca@gmail.com', '$2y$10$lN4lwti3u0nh7L3k3Pel2O3zALjSHCZM7WrYlAl13bec72pynJK4y', 'applicant', 'Recca', 'Pinto', '09231234507', 'active', 0, '2026-05-30 04:15:46', '2026-05-30 04:15:46'),
(72, 'johnmarkpausta@gmail.com', '$2y$10$ptFGCu8X/14hAC4SkkjV4OWlr4cAz0hv4HP21hloux6FuijQMibHa', 'applicant', 'Recca', 'Pinto', '09505821348', 'active', 0, '2026-07-29 14:12:19', '2026-07-29 14:12:19');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `applicants`
--
ALTER TABLE `applicants`
  ADD PRIMARY KEY (`applicant_id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD KEY `idx_applicant_user` (`user_id`);

--
-- Indexes for table `applications`
--
ALTER TABLE `applications`
  ADD PRIMARY KEY (`application_id`),
  ADD UNIQUE KEY `unique_application` (`job_id`,`applicant_id`),
  ADD KEY `applicant_id` (`applicant_id`),
  ADD KEY `reviewed_by_employer_id` (`reviewed_by_employer_id`),
  ADD KEY `idx_application_status` (`status`);

--
-- Indexes for table `audit_trail`
--
ALTER TABLE `audit_trail`
  ADD PRIMARY KEY (`audit_id`),
  ADD KEY `idx_admin` (`admin_user_id`),
  ADD KEY `idx_action` (`action_type`),
  ADD KEY `idx_target` (`target_type`,`target_id`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `candidate_feedback`
--
ALTER TABLE `candidate_feedback`
  ADD PRIMARY KEY (`feedback_id`),
  ADD KEY `idx_application` (`application_id`),
  ADD KEY `idx_applicant` (`applicant_id`),
  ADD KEY `idx_employer` (`employer_id`);

--
-- Indexes for table `candidate_ml_features`
--
ALTER TABLE `candidate_ml_features`
  ADD PRIMARY KEY (`feature_id`),
  ADD UNIQUE KEY `unique_application_features` (`application_id`),
  ADD KEY `job_id` (`job_id`),
  ADD KEY `idx_applicant_job` (`applicant_id`,`job_id`),
  ADD KEY `idx_ml_ranking` (`ml_ranking_score`),
  ADD KEY `idx_ranking_category` (`ranking_category`);

--
-- Indexes for table `candidate_recommendations`
--
ALTER TABLE `candidate_recommendations`
  ADD PRIMARY KEY (`recommendation_id`),
  ADD KEY `employer_id` (`employer_id`),
  ADD KEY `job_id` (`job_id`),
  ADD KEY `applicant_id` (`applicant_id`);

--
-- Indexes for table `chatbot_answers`
--
ALTER TABLE `chatbot_answers`
  ADD PRIMARY KEY (`answer_id`),
  ADD KEY `idx_applicant` (`applicant_id`),
  ADD KEY `idx_qualification` (`qualification_id`);

--
-- Indexes for table `chatbot_recommendations`
--
ALTER TABLE `chatbot_recommendations`
  ADD PRIMARY KEY (`recommendation_id`),
  ADD KEY `qualification_id` (`qualification_id`),
  ADD KEY `idx_applicant` (`applicant_id`),
  ADD KEY `idx_job` (`job_id`);

--
-- Indexes for table `cms_brands`
--
ALTER TABLE `cms_brands`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `cms_content`
--
ALTER TABLE `cms_content`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_section_field_lang` (`section_id`,`field_key`,`language`);

--
-- Indexes for table `cms_hero_slides`
--
ALTER TABLE `cms_hero_slides`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `cms_news`
--
ALTER TABLE `cms_news`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `cms_sections`
--
ALTER TABLE `cms_sections`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `section_key` (`section_key`);

--
-- Indexes for table `cms_testimonials`
--
ALTER TABLE `cms_testimonials`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `contact_inquiries`
--
ALTER TABLE `contact_inquiries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_is_read` (`is_read`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_created` (`created_at` DESC);

--
-- Indexes for table `contact_replies`
--
ALTER TABLE `contact_replies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_inquiry` (`inquiry_id`);

--
-- Indexes for table `employers`
--
ALTER TABLE `employers`
  ADD PRIMARY KEY (`employer_id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD KEY `idx_employer_user` (`user_id`);

--
-- Indexes for table `feedback`
--
ALTER TABLE `feedback`
  ADD PRIMARY KEY (`feedback_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `interview_schedules`
--
ALTER TABLE `interview_schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `application_id` (`application_id`),
  ADD KEY `employer_id` (`employer_id`);

--
-- Indexes for table `job_postings`
--
ALTER TABLE `job_postings`
  ADD PRIMARY KEY (`job_id`),
  ADD KEY `employer_id` (`employer_id`),
  ADD KEY `idx_job_status` (`status`);

--
-- Indexes for table `job_qualification_mapping`
--
ALTER TABLE `job_qualification_mapping`
  ADD PRIMARY KEY (`mapping_id`),
  ADD UNIQUE KEY `unique_mapping` (`job_id`,`qualification_id`),
  ADD KEY `idx_job` (`job_id`),
  ADD KEY `idx_qualification` (`qualification_id`);

--
-- Indexes for table `job_recommendations`
--
ALTER TABLE `job_recommendations`
  ADD PRIMARY KEY (`recommendation_id`),
  ADD KEY `applicant_id` (`applicant_id`),
  ADD KEY `job_id` (`job_id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`message_id`),
  ADD KEY `idx_sender` (`sender_id`),
  ADD KEY `idx_receiver` (`receiver_id`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_is_read` (`is_read`),
  ADD KEY `idx_conversation` (`sender_id`,`receiver_id`,`created_at`);

--
-- Indexes for table `ml_application_screening`
--
ALTER TABLE `ml_application_screening`
  ADD PRIMARY KEY (`screening_id`),
  ADD KEY `idx_application` (`application_id`),
  ADD KEY `idx_prediction_class` (`prediction_class`),
  ADD KEY `idx_ml_score` (`ml_score`);

--
-- Indexes for table `ml_feature_importance`
--
ALTER TABLE `ml_feature_importance`
  ADD PRIMARY KEY (`feature_id`),
  ADD KEY `idx_feature_name` (`feature_name`),
  ADD KEY `idx_importance` (`importance_score`);

--
-- Indexes for table `ml_model_performance`
--
ALTER TABLE `ml_model_performance`
  ADD PRIMARY KEY (`performance_id`),
  ADD KEY `idx_model_version` (`model_version`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `qualifications`
--
ALTER TABLE `qualifications`
  ADD PRIMARY KEY (`qualification_id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `resume_analysis`
--
ALTER TABLE `resume_analysis`
  ADD PRIMARY KEY (`analysis_id`),
  ADD KEY `idx_applicant` (`applicant_id`);

--
-- Indexes for table `saved_jobs`
--
ALTER TABLE `saved_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_saved_job` (`applicant_id`,`job_id`),
  ADD KEY `job_id` (`job_id`);

--
-- Indexes for table `skills`
--
ALTER TABLE `skills`
  ADD PRIMARY KEY (`skill_id`),
  ADD UNIQUE KEY `skill_name` (`skill_name`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_user_email` (`email`),
  ADD KEY `idx_user_role` (`role`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `applicants`
--
ALTER TABLE `applicants`
  MODIFY `applicant_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=70;

--
-- AUTO_INCREMENT for table `applications`
--
ALTER TABLE `applications`
  MODIFY `application_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT for table `audit_trail`
--
ALTER TABLE `audit_trail`
  MODIFY `audit_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `candidate_feedback`
--
ALTER TABLE `candidate_feedback`
  MODIFY `feedback_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `candidate_ml_features`
--
ALTER TABLE `candidate_ml_features`
  MODIFY `feature_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `candidate_recommendations`
--
ALTER TABLE `candidate_recommendations`
  MODIFY `recommendation_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `chatbot_answers`
--
ALTER TABLE `chatbot_answers`
  MODIFY `answer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=580;

--
-- AUTO_INCREMENT for table `chatbot_recommendations`
--
ALTER TABLE `chatbot_recommendations`
  MODIFY `recommendation_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `cms_brands`
--
ALTER TABLE `cms_brands`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=58;

--
-- AUTO_INCREMENT for table `cms_content`
--
ALTER TABLE `cms_content`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `cms_hero_slides`
--
ALTER TABLE `cms_hero_slides`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `cms_news`
--
ALTER TABLE `cms_news`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `cms_sections`
--
ALTER TABLE `cms_sections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `cms_testimonials`
--
ALTER TABLE `cms_testimonials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `contact_inquiries`
--
ALTER TABLE `contact_inquiries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `contact_replies`
--
ALTER TABLE `contact_replies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `employers`
--
ALTER TABLE `employers`
  MODIFY `employer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `feedback`
--
ALTER TABLE `feedback`
  MODIFY `feedback_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `interview_schedules`
--
ALTER TABLE `interview_schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `job_postings`
--
ALTER TABLE `job_postings`
  MODIFY `job_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `job_qualification_mapping`
--
ALTER TABLE `job_qualification_mapping`
  MODIFY `mapping_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=88;

--
-- AUTO_INCREMENT for table `job_recommendations`
--
ALTER TABLE `job_recommendations`
  MODIFY `recommendation_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `message_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=57;

--
-- AUTO_INCREMENT for table `ml_application_screening`
--
ALTER TABLE `ml_application_screening`
  MODIFY `screening_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `ml_feature_importance`
--
ALTER TABLE `ml_feature_importance`
  MODIFY `feature_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `ml_model_performance`
--
ALTER TABLE `ml_model_performance`
  MODIFY `performance_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=93;

--
-- AUTO_INCREMENT for table `qualifications`
--
ALTER TABLE `qualifications`
  MODIFY `qualification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `resume_analysis`
--
ALTER TABLE `resume_analysis`
  MODIFY `analysis_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `saved_jobs`
--
ALTER TABLE `saved_jobs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `skills`
--
ALTER TABLE `skills`
  MODIFY `skill_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=73;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `applicants`
--
ALTER TABLE `applicants`
  ADD CONSTRAINT `applicants_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `applications`
--
ALTER TABLE `applications`
  ADD CONSTRAINT `applications_ibfk_1` FOREIGN KEY (`job_id`) REFERENCES `job_postings` (`job_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `applications_ibfk_2` FOREIGN KEY (`applicant_id`) REFERENCES `applicants` (`applicant_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `applications_ibfk_3` FOREIGN KEY (`reviewed_by_employer_id`) REFERENCES `employers` (`employer_id`) ON DELETE SET NULL;

--
-- Constraints for table `audit_trail`
--
ALTER TABLE `audit_trail`
  ADD CONSTRAINT `audit_trail_ibfk_1` FOREIGN KEY (`admin_user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `candidate_feedback`
--
ALTER TABLE `candidate_feedback`
  ADD CONSTRAINT `candidate_feedback_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`application_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `candidate_feedback_ibfk_2` FOREIGN KEY (`applicant_id`) REFERENCES `applicants` (`applicant_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `candidate_feedback_ibfk_3` FOREIGN KEY (`employer_id`) REFERENCES `employers` (`employer_id`) ON DELETE CASCADE;

--
-- Constraints for table `candidate_ml_features`
--
ALTER TABLE `candidate_ml_features`
  ADD CONSTRAINT `candidate_ml_features_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`application_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `candidate_ml_features_ibfk_2` FOREIGN KEY (`applicant_id`) REFERENCES `applicants` (`applicant_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `candidate_ml_features_ibfk_3` FOREIGN KEY (`job_id`) REFERENCES `job_postings` (`job_id`) ON DELETE CASCADE;

--
-- Constraints for table `candidate_recommendations`
--
ALTER TABLE `candidate_recommendations`
  ADD CONSTRAINT `candidate_recommendations_ibfk_1` FOREIGN KEY (`employer_id`) REFERENCES `employers` (`employer_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `candidate_recommendations_ibfk_2` FOREIGN KEY (`job_id`) REFERENCES `job_postings` (`job_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `candidate_recommendations_ibfk_3` FOREIGN KEY (`applicant_id`) REFERENCES `applicants` (`applicant_id`) ON DELETE CASCADE;

--
-- Constraints for table `chatbot_answers`
--
ALTER TABLE `chatbot_answers`
  ADD CONSTRAINT `chatbot_answers_ibfk_1` FOREIGN KEY (`applicant_id`) REFERENCES `applicants` (`applicant_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `chatbot_answers_ibfk_2` FOREIGN KEY (`qualification_id`) REFERENCES `qualifications` (`qualification_id`) ON DELETE CASCADE;

--
-- Constraints for table `chatbot_recommendations`
--
ALTER TABLE `chatbot_recommendations`
  ADD CONSTRAINT `chatbot_recommendations_ibfk_1` FOREIGN KEY (`applicant_id`) REFERENCES `applicants` (`applicant_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `chatbot_recommendations_ibfk_2` FOREIGN KEY (`job_id`) REFERENCES `job_postings` (`job_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `chatbot_recommendations_ibfk_3` FOREIGN KEY (`qualification_id`) REFERENCES `qualifications` (`qualification_id`) ON DELETE CASCADE;

--
-- Constraints for table `cms_content`
--
ALTER TABLE `cms_content`
  ADD CONSTRAINT `cms_content_ibfk_1` FOREIGN KEY (`section_id`) REFERENCES `cms_sections` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `contact_replies`
--
ALTER TABLE `contact_replies`
  ADD CONSTRAINT `contact_replies_ibfk_1` FOREIGN KEY (`inquiry_id`) REFERENCES `contact_inquiries` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `employers`
--
ALTER TABLE `employers`
  ADD CONSTRAINT `employers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `feedback`
--
ALTER TABLE `feedback`
  ADD CONSTRAINT `feedback_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `interview_schedules`
--
ALTER TABLE `interview_schedules`
  ADD CONSTRAINT `interview_schedules_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`application_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `interview_schedules_ibfk_2` FOREIGN KEY (`employer_id`) REFERENCES `employers` (`employer_id`) ON DELETE CASCADE;

--
-- Constraints for table `job_postings`
--
ALTER TABLE `job_postings`
  ADD CONSTRAINT `job_postings_ibfk_1` FOREIGN KEY (`employer_id`) REFERENCES `employers` (`employer_id`) ON DELETE CASCADE;

--
-- Constraints for table `job_qualification_mapping`
--
ALTER TABLE `job_qualification_mapping`
  ADD CONSTRAINT `job_qualification_mapping_ibfk_1` FOREIGN KEY (`job_id`) REFERENCES `job_postings` (`job_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `job_qualification_mapping_ibfk_2` FOREIGN KEY (`qualification_id`) REFERENCES `qualifications` (`qualification_id`) ON DELETE CASCADE;

--
-- Constraints for table `job_recommendations`
--
ALTER TABLE `job_recommendations`
  ADD CONSTRAINT `job_recommendations_ibfk_1` FOREIGN KEY (`applicant_id`) REFERENCES `applicants` (`applicant_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `job_recommendations_ibfk_2` FOREIGN KEY (`job_id`) REFERENCES `job_postings` (`job_id`) ON DELETE CASCADE;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `ml_application_screening`
--
ALTER TABLE `ml_application_screening`
  ADD CONSTRAINT `ml_application_screening_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `applications` (`application_id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `resume_analysis`
--
ALTER TABLE `resume_analysis`
  ADD CONSTRAINT `resume_analysis_ibfk_1` FOREIGN KEY (`applicant_id`) REFERENCES `applicants` (`applicant_id`) ON DELETE CASCADE;

--
-- Constraints for table `saved_jobs`
--
ALTER TABLE `saved_jobs`
  ADD CONSTRAINT `saved_jobs_ibfk_1` FOREIGN KEY (`applicant_id`) REFERENCES `applicants` (`applicant_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `saved_jobs_ibfk_2` FOREIGN KEY (`job_id`) REFERENCES `job_postings` (`job_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
