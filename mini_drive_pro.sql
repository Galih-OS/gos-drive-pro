-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 26 Okt 2026 pada 05.06
-- Versi server: 10.4.32-MariaDB
-- Versi PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `mini_drive_pro`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `files`
--

CREATE TABLE `files` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `folder_id` int(11) DEFAULT NULL,
  `filename` varchar(255) DEFAULT NULL,
  `filepath` varchar(255) DEFAULT NULL,
  `size` bigint(20) DEFAULT NULL,
  `share_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `file_size` bigint(20) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `files`
--

INSERT INTO `files` (`id`, `user_id`, `folder_id`, `filename`, `filepath`, `size`, `share_token`, `created_at`, `file_size`) VALUES
(249, 7, 14, '1453_VII_01_0426_DS_Undangan_Rekonsiliasi_dan_Pemutakhiran_Data.pdf', '\\\\10.201.6.43\\agg\\MROS\\SERVERKU\\uploads\\1453_VII_01_0426_DS_Undangan_Rekonsiliasi_dan_Pemutakhiran_Data_20260928_030115_52220ab52f.pdf', 691797, 'f365abbbd80fea23798e9aa15037da15232f3b52d7a38644bbc8d6a0b981dbd7', '2026-09-28 01:01:16', 691797),
(250, 7, 14, 'BPJS.xlsx', '\\\\10.201.6.43\\agg\\MROS\\SERVERKU\\uploads\\BPJS_20260928_030116_2e8e3a96fa.xlsx', 18131, '1bd08ae735c6fda80cde567b947cdda715db0613774877d9de6899523e44e546', '2026-09-28 01:01:16', 18131),
(252, 7, 14, 'Gemini_Generated_Image_ue8ka4ue8ka4ue8k.png', '\\\\10.201.6.43\\agg\\MROS\\SERVERKU\\uploads\\Gemini_Generated_Image_ue8ka4ue8ka4ue8k_20260928_030118_1ef0c46ee1.png', 2608685, '6890271d6b562e2d9d74b75538117f4769cf0ec75c3f3c8d135f4c18d7094d60', '2026-09-28 01:01:18', 2608685);

-- --------------------------------------------------------

--
-- Struktur dari tabel `folders`
--

CREATE TABLE `folders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `folder_name` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `share_token` varchar(64) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `folders`
--

INSERT INTO `folders` (`id`, `user_id`, `parent_id`, `folder_name`, `created_at`, `share_token`) VALUES
(14, 7, NULL, '12', '2026-09-28 00:48:04', '55875437a17c991c74527e6394c18d7a0c2bdc5eb82927986b5b8c143f025e49');

-- --------------------------------------------------------

--
-- Struktur dari tabel `upload_sessions`
--

CREATE TABLE `upload_sessions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `upload_id` varchar(64) NOT NULL,
  `user_id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `total_size` bigint(20) UNSIGNED NOT NULL,
  `chunk_size` int(10) UNSIGNED NOT NULL,
  `total_chunks` int(10) UNSIGNED NOT NULL,
  `uploaded_chunks` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `uploaded_size` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `folder_id` int(11) DEFAULT NULL,
  `status` enum('uploading','merging','completed','failed','cancelled') NOT NULL DEFAULT 'uploading',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `role` enum('admin','user') NOT NULL DEFAULT 'user',
  `status` enum('active','suspended') NOT NULL DEFAULT 'active',
  `password` varchar(255) NOT NULL,
  `storage_used` bigint(20) DEFAULT 0,
  `storage_quota` bigint(20) DEFAULT 104857600,
  `last_login` datetime DEFAULT NULL,
  `last_logout` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `users`
--

INSERT INTO `users` (`id`, `username`, `role`, `status`, `password`, `storage_used`, `storage_quota`, `last_login`, `last_logout`, `created_at`) VALUES
(7, 'galihos', 'user', 'active', '$2y$10$vVBl7cnDoz0GjCaf2NJ4w.H1NvxiQq3gkHdP1zAyVCgIfrg0xM7My', 0, 0, '2026-10-26 05:06:07', '2026-10-20 07:06:02', '2026-08-03 01:49:29'),
(8, 'galihos', 'admin', 'active', '$2y$10$j/9oAyjRNU90BjXbkGq0xun0naO3HadbFpNUjm56l/HGPdYRYnpfe', 0, 0, '2026-10-20 07:03:50', '2026-10-14 11:19:37', '0000-00-00 00:00:00');

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `files`
--
ALTER TABLE `files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_files_folder_id` (`folder_id`);

--
-- Indeks untuk tabel `folders`
--
ALTER TABLE `folders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `share_token` (`share_token`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_parent_id` (`parent_id`);

--
-- Indeks untuk tabel `upload_sessions`
--
ALTER TABLE `upload_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_upload_id` (`upload_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indeks untuk tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `files`
--
ALTER TABLE `files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=253;

--
-- AUTO_INCREMENT untuk tabel `folders`
--
ALTER TABLE `folders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT untuk tabel `upload_sessions`
--
ALTER TABLE `upload_sessions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `files`
--
ALTER TABLE `files`
  ADD CONSTRAINT `files_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `files_ibfk_2` FOREIGN KEY (`folder_id`) REFERENCES `folders` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_files_folder` FOREIGN KEY (`folder_id`) REFERENCES `folders` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Ketidakleluasaan untuk tabel `folders`
--
ALTER TABLE `folders`
  ADD CONSTRAINT `fk_folder_parent` FOREIGN KEY (`parent_id`) REFERENCES `folders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `folders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
