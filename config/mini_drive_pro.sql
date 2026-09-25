-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 03 Agu 2026 pada 06.48
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
(193, 7, NULL, '_budgetrip Jogja Solo 2026.pdf', '\\\\10.201.6.43\\agg\\MROS\\SERVERKU\\uploads\\_budgetrip Jogja Solo 2026_20260803_053350_4fdca41d56.pdf', 17564875, 'fb541a22da001064cefdb2d9a32620d00c6416e43ead5ea17e9b5f891f3b13eb', '2026-08-03 03:33:51', 17564875),
(195, 7, NULL, 'WhatsApp Image 2026-06-04 at 09.21.06 (1).png', '\\\\10.201.6.43\\agg\\MROS\\SERVERKU\\uploads\\WhatsApp Image 2026-06-04 at 09_21_06 _1__20260803_053412_371ba0d809.png', 788418, '776f960d4d4a0be4d35ad590549ef2dcaf43b19061fb54c88df55c454c2bb8ae', '2026-08-03 03:34:13', 788418),
(196, 7, NULL, 'PAM.jpg', '\\\\10.201.6.43\\agg\\MROS\\SERVERKU\\uploads\\PAM_20260803_053413_ffcef58b75.jpg', 1045194, 'c228ed836a492e737b0d3b196e69facbb00b45c9baa49f121bf3bbd5721f8b40', '2026-08-03 03:34:13', 1045194),
(197, 7, NULL, 'WhatsApp Video 2026-03-13 at 10.16.17.mp4', '\\\\10.201.6.43\\agg\\MROS\\SERVERKU\\uploads\\WhatsApp Video 2026-03-13 at 10_16_17_20260803_053536_8f23c5c80b.mp4', 2970850, '50f8d694b65136f6432fb8ee96b83f0d622104f0492702bafd0aaa7cd93e000b', '2026-08-03 03:35:36', 2970850);

-- --------------------------------------------------------

--
-- Struktur dari tabel `folders`
--

CREATE TABLE `folders` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `folder_name` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `users`
--

INSERT INTO `users` (`id`, `username`, `role`, `status`, `password`, `storage_used`, `storage_quota`, `last_login`, `last_logout`, `created_at`) VALUES
(7, 'galihos', 'user', 'active', '$2y$10$NAxka9kwpgBhQJTibujs1uJFTcnOHiPSQ.njz97UUf5ES9GQRtl7W', 0, 0, '2026-08-03 06:16:02', '2026-08-03 06:16:07', '2026-08-03 01:49:29'),
(8, 'admin', 'admin', 'active', '$2y$10$PsnFEKQgB4nQMsV.ofbjPOivZMEke55RZ.P2xzktMpPEBG9q2p1mi', 0, 0, '2026-08-03 06:44:16', '2026-08-03 06:47:27', '2026-08-03 03:59:38');

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `files`
--
ALTER TABLE `files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `folder_id` (`folder_id`);

--
-- Indeks untuk tabel `folders`
--
ALTER TABLE `folders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

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
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `files`
--
ALTER TABLE `files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=198;

--
-- AUTO_INCREMENT untuk tabel `folders`
--
ALTER TABLE `folders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `upload_sessions`
--
ALTER TABLE `upload_sessions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `files`
--
ALTER TABLE `files`
  ADD CONSTRAINT `files_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `files_ibfk_2` FOREIGN KEY (`folder_id`) REFERENCES `folders` (`id`) ON DELETE SET NULL;

--
-- Ketidakleluasaan untuk tabel `folders`
--
ALTER TABLE `folders`
  ADD CONSTRAINT `folders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
