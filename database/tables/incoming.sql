CREATE TABLE IF NOT EXISTS `incoming` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `value` decimal(12,2) NOT NULL DEFAULT 0.00,
    `day` tinyint(2) unsigned NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_incoming_user_id` (`user_id`),
    KEY `idx_incoming_day` (`day`),
    CONSTRAINT `fk_incoming_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `incoming`
    ADD COLUMN IF NOT EXISTS `user_id` int(11) DEFAULT NULL AFTER `id`;

UPDATE `incoming`
SET `user_id` = 10
WHERE `user_id` IS NULL;

ALTER TABLE `incoming`
    MODIFY COLUMN `user_id` int(11) NOT NULL;
