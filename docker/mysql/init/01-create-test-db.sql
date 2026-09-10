-- Creates the schema PHPUnit runs against, so tests never touch the database a
-- reviewer has seeded. Note: MySQL only runs the scripts in
-- docker-entrypoint-initdb.d on first initialisation of an empty data volume.
-- If this file is added after the stack has already been started, run
-- `docker compose down -v` for it to take effect.

CREATE DATABASE IF NOT EXISTS `user_todo_api_testing`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

GRANT ALL PRIVILEGES ON `user_todo_api_testing`.* TO 'utapi'@'%';

FLUSH PRIVILEGES;
