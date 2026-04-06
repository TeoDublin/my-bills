CREATE TABLE IF NOT EXISTS `user_preferences` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `scope` varchar(100) NOT NULL DEFAULT 'global',
    `preference_key` varchar(60) NOT NULL,
    `preference_value` longtext NOT NULL,
    `created_at` datetime NOT NULL DEFAULT current_timestamp(),
    `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_user_scope_key` (`user_id`, `scope`, `preference_key`),
    KEY `idx_user_preferences_user_id` (`user_id`),
    CONSTRAINT `fk_user_preferences_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `user_preferences` (`user_id`, `scope`, `preference_key`, `preference_value`, `created_at`, `updated_at`)
VALUES
    (10, 'global', 'theme', 'light', NOW(), NOW()),
    (10, 'global', 'planning_colors', ':root{--base-bg-colloquio:#aad5d8;--base-bg-seduta:#56ebf7;--base-bg-corso:#32ffbb;--base-bg-sbarra:#5d7fff;}', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    `preference_value` = VALUES(`preference_value`),
    `updated_at` = NOW();
