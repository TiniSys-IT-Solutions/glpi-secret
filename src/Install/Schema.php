<?php

declare(strict_types=1);

namespace GlpiPlugin\Secret\Install;

final class Schema
{
    public static function tables(): array
    {
        return [
            'glpi_plugin_secret_configs' => "CREATE TABLE `glpi_plugin_secret_configs` (
                `id` int unsigned NOT NULL AUTO_INCREMENT,
                `name` varchar(255) NOT NULL,
                `value` text DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq_name` (`name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'glpi_plugin_secret_secrets' => "CREATE TABLE `glpi_plugin_secret_secrets` (
                `id` int unsigned NOT NULL AUTO_INCREMENT,
                `name` varchar(255) NOT NULL,
                `type` varchar(32) NOT NULL,
                `username` varchar(255) DEFAULT NULL,
                `encrypted_value` longtext NOT NULL,
                `visibility` varchar(48) NOT NULL,
                `groups_id` int unsigned NOT NULL DEFAULT 0,
                `users_id_creator` int unsigned NOT NULL,
                `entities_id` int unsigned NOT NULL DEFAULT 0,
                `is_recursive` tinyint NOT NULL DEFAULT 0,
                `expiration_policy` varchar(32) NOT NULL DEFAULT 'never',
                `expiration` timestamp NULL DEFAULT NULL,
                `date_creation` timestamp NULL DEFAULT NULL,
                `date_mod` timestamp NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `entity_visibility` (`entities_id`, `is_recursive`, `visibility`),
                KEY `creator` (`users_id_creator`),
                KEY `group_visibility` (`groups_id`, `visibility`),
                KEY `expiration` (`expiration`),
                KEY `closure_expiration` (`expiration_policy`, `expiration`, `id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'glpi_plugin_secret_secretitems' => "CREATE TABLE `glpi_plugin_secret_secretitems` (
                `id` int unsigned NOT NULL AUTO_INCREMENT,
                `plugin_secret_secrets_id` int unsigned NOT NULL,
                `itemtype` varchar(255) NOT NULL,
                `items_id` int unsigned NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `secret_item` (`plugin_secret_secrets_id`, `itemtype`, `items_id`),
                KEY `item` (`itemtype`, `items_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            'glpi_plugin_secret_secretlogs' => "CREATE TABLE `glpi_plugin_secret_secretlogs` (
                `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                `plugin_secret_secrets_id` int unsigned NOT NULL,
                `users_id` int unsigned NOT NULL,
                `action` varchar(16) NOT NULL,
                `context` json DEFAULT NULL,
                `date_creation` timestamp NOT NULL,
                PRIMARY KEY (`id`),
                KEY `secret_date` (`plugin_secret_secrets_id`, `date_creation`),
                KEY `user_date` (`users_id`, `date_creation`),
                KEY `action` (`action`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ];
    }
}
