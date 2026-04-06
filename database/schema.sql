CREATE DATABASE IF NOT EXISTS `my-bills`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `my-bills`;

SOURCE /var/www/html/my-bills/database/tables/users.sql;
SOURCE /var/www/html/my-bills/database/tables/user_preferences.sql;
SOURCE /var/www/html/my-bills/database/tables/password_reset_tokens.sql;
SOURCE /var/www/html/my-bills/database/tables/async_jobs.sql;
SOURCE /var/www/html/my-bills/database/tables/bills_groups.sql;
SOURCE /var/www/html/my-bills/database/tables/montly_bills.sql;
SOURCE /var/www/html/my-bills/database/tables/incoming.sql;
SOURCE /var/www/html/my-bills/database/tables/bills.sql;
SOURCE /var/www/html/my-bills/database/views/view_montly_bills.sql;
SOURCE /var/www/html/my-bills/database/views/view_bills.sql;
