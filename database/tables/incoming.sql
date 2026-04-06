CREATE TABLE IF NOT EXISTS `incoming` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `value` decimal(12,2) NOT NULL DEFAULT 0.00,
    `day` tinyint(2) unsigned NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_incoming_day` (`day`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
