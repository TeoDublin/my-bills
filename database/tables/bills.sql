CREATE TABLE IF NOT EXISTS `bills` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `id_montly_bill` int(11) DEFAULT NULL,
    `id_group` int(11) NOT NULL,
    `name` varchar(255) NOT NULL,
    `value` decimal(12,2) NOT NULL DEFAULT 0.00,
    `date` date NOT NULL,
    `reference_start` date DEFAULT NULL,
    `reference_end` date DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_bills_id_group` (`id_group`),
    CONSTRAINT `fk_bills_group_id` FOREIGN KEY (`id_group`) REFERENCES `bills_groups` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `bills`
    ADD COLUMN IF NOT EXISTS `id_montly_bill` int(11) DEFAULT NULL AFTER `id`,
    ADD COLUMN IF NOT EXISTS `reference_start` date DEFAULT NULL AFTER `date`,
    ADD COLUMN IF NOT EXISTS `reference_end` date DEFAULT NULL AFTER `reference_start`;

INSERT INTO `bills` (`id`, `id_group`, `name`, `value`, `date`)
VALUES
    (1, 1, 'Electricity March', 124.90, '2026-03-15'),
    (2, 2, 'Streaming Annual Plan', 89.99, '2026-02-01'),
    (3, 3, 'Car Insurance', 312.50, '2026-01-20')
ON DUPLICATE KEY UPDATE
    `id_group` = VALUES(`id_group`),
    `name` = VALUES(`name`),
    `value` = VALUES(`value`),
    `date` = VALUES(`date`);
