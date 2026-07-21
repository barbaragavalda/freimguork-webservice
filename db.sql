-- Schema for the tables this package's own code reads/writes directly.
-- Despite the `appacman_` prefix (inherited from Appacman's naming
-- convention), these are queried by Webservice\Model\App and
-- Webservice\Model\Push, not by freimguork-appacman itself - a project
-- using Webservice needs this file concatenated after Appacman's own
-- db.sql, regardless of whether it also uses Appacman's admin push UI.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for appacman_app_config
-- Queried unconditionally by Webservice\Model\App::onMaintenance() on every
-- api request; missing it doesn't just log a warning, it triggers dev-mode
-- debug output that runs before headers are sent and silently drops the
-- rest of the response's headers (Access-Control-Allow-Origin included).
-- ----------------------------
DROP TABLE IF EXISTS `appacman_app_config`;
CREATE TABLE `appacman_app_config` (
  `id_appacman_app_config` tinyint(3) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `platform` varchar(255) DEFAULT NULL,
  `value` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id_appacman_app_config`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8;

BEGIN;
INSERT INTO `appacman_app_config` VALUES (1, 'VERSION', 'ios', '1.0.0');
INSERT INTO `appacman_app_config` VALUES (2, 'VERSION', 'android', '1.0.0');
INSERT INTO `appacman_app_config` VALUES (3, 'MAINTENANCE', NULL, '');
COMMIT;

-- ----------------------------
-- Table structure for appacman_push_device
-- Device token registration, written by Webservice\Model\Push and read by
-- Core\Model\Push\* when actually sending notifications.
-- ----------------------------
DROP TABLE IF EXISTS `appacman_push_device`;
CREATE TABLE `appacman_push_device` (
  `uuid` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `platform` varchar(255) NOT NULL,
  `model` varchar(255) NOT NULL,
  `os_version` varchar(255) NOT NULL,
  `app_version` varchar(255) NOT NULL,
  `id_user` int(11) DEFAULT NULL,
  `language` tinyint(4) DEFAULT NULL,
  `last_connection` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci ROW_FORMAT=COMPACT;

-- ----------------------------
-- Table structure for appacman_push
-- Appacman admin push-composition UI (PushCronJob/Notifier); no seed data,
-- each project schedules its own.
-- ----------------------------
DROP TABLE IF EXISTS `appacman_push`;
CREATE TABLE `appacman_push` (
  `id_appacman_push` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `platform` varchar(255) DEFAULT NULL,
  `model` varchar(255) DEFAULT NULL,
  `os_version` varchar(255) DEFAULT NULL,
  `app_version` varchar(255) DEFAULT NULL,
  `last_connection` date DEFAULT NULL,
  `deeplink` varchar(255) DEFAULT NULL,
  `send` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_sent` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id_appacman_push`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci ROW_FORMAT=COMPACT;

-- ----------------------------
-- Table structure for appacman_push_lang
-- ----------------------------
DROP TABLE IF EXISTS `appacman_push_lang`;
CREATE TABLE `appacman_push_lang` (
  `id_appacman_push_lang` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_appacman_push` int(11) NOT NULL,
  `id_appacman_lang` tinyint(4) NOT NULL,
  `name` varchar(255) NOT NULL,
  PRIMARY KEY (`id_appacman_push_lang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci ROW_FORMAT=COMPACT;

-- ----------------------------
-- Table structure for appacman_push_deeplink
-- Each project defines its own deeplinks (screen/table names), so no seed
-- data here.
-- ----------------------------
DROP TABLE IF EXISTS `appacman_push_deeplink`;
CREATE TABLE `appacman_push_deeplink` (
  `id_appacman_push_deeplink` tinyint(3) unsigned NOT NULL AUTO_INCREMENT,
  `table_name` varchar(255) NOT NULL DEFAULT '',
  `format` varchar(255) NOT NULL,
  PRIMARY KEY (`id_appacman_push_deeplink`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci ROW_FORMAT=COMPACT;

-- ----------------------------
-- Table structure for appacman_push_deeplink_lang
-- ----------------------------
DROP TABLE IF EXISTS `appacman_push_deeplink_lang`;
CREATE TABLE `appacman_push_deeplink_lang` (
  `id_appacman_push_deeplink_lang` tinyint(3) unsigned NOT NULL AUTO_INCREMENT,
  `id_appacman_push_deeplink` tinyint(4) NOT NULL,
  `id_appacman_lang` tinyint(4) NOT NULL,
  `name` varchar(255) NOT NULL,
  PRIMARY KEY (`id_appacman_push_deeplink_lang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci ROW_FORMAT=COMPACT;

SET FOREIGN_KEY_CHECKS = 1;
