CREATE TABLE IF NOT EXISTS `users` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `username` varchar(60) NOT NULL,
    `password_hash` longtext NOT NULL,
    `full_name` varchar(60) NOT NULL,
    `email` varchar(100) NOT NULL,
    `token` varchar(64) DEFAULT NULL,
    `token_expires_at` datetime DEFAULT NULL,
    `created_at` datetime NOT NULL DEFAULT current_timestamp(),
    `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_users_username` (`username`),
    UNIQUE KEY `uniq_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `users` (`id`, `username`, `password_hash`, `full_name`, `email`, `token`, `token_expires_at`)
VALUES
    (10, 'teo', '$2y$10$ga5hSdkGezQmiYPCohMDke5cWfZfef/U8O2vY3ANh9qVGuZf0xMAW', 'Teo', 'teodublin@gmail.com', NULL, NULL)
ON DUPLICATE KEY UPDATE
    `username` = VALUES(`username`),
    `password_hash` = VALUES(`password_hash`),
    `full_name` = VALUES(`full_name`),
    `email` = VALUES(`email`);
