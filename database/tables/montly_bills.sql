CREATE TABLE IF NOT EXISTS `montly_bills` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `id_group` int(11) NOT NULL,
    `name` varchar(255) NOT NULL,
    `value` decimal(12,2) NOT NULL DEFAULT 0.00,
    `day` tinyint(2) unsigned NOT NULL,
    `first_date` date DEFAULT NULL,
    `last_date` date DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_montly_bills_id_group` (`id_group`),
    KEY `idx_montly_bills_day` (`day`),
    CONSTRAINT `fk_montly_bills_group_id` FOREIGN KEY (`id_group`) REFERENCES `bills_groups` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `chk_montly_bills_day` CHECK (`day` BETWEEN 1 AND 31)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
