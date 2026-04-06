CREATE TABLE IF NOT EXISTS `bills_groups` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `name` varchar(120) NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_bills_groups_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `bills_groups` (`id`, `name`)
VALUES
    (1, 'Utilities'),
    (2, 'Subscriptions'),
    (3, 'Insurance')
ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`);
