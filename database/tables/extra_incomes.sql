CREATE TABLE IF NOT EXISTS `extra_incomes` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `name` varchar(255) NOT NULL,
    `value` decimal(12,2) NOT NULL DEFAULT 0.00,
    `date` date NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_extra_incomes_user_id` (`user_id`),
    KEY `idx_extra_incomes_date` (`date`),
    CONSTRAINT `fk_extra_incomes_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
