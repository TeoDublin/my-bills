CREATE TABLE IF NOT EXISTS `bills_groups` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `name` varchar(120) NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_bills_groups_user_name` (`user_id`, `name`),
    KEY `idx_bills_groups_user_id` (`user_id`),
    CONSTRAINT `fk_bills_groups_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `bills_groups`
    ADD COLUMN IF NOT EXISTS `user_id` int(11) DEFAULT NULL AFTER `id`;

UPDATE `bills_groups`
SET `user_id` = 10
WHERE `user_id` IS NULL;

ALTER TABLE `bills_groups`
    MODIFY COLUMN `user_id` int(11) NOT NULL;

INSERT INTO `bills_groups` (`id`, `user_id`, `name`)
VALUES
    (1, 10, 'Utilities'),
    (2, 10, 'Subscriptions'),
    (3, 10, 'Insurance')
ON DUPLICATE KEY UPDATE
    `user_id` = VALUES(`user_id`),
    `name` = VALUES(`name`);
