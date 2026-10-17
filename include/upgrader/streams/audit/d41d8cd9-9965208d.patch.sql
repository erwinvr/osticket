/**
 * @version v1.18.4-audit
 * @title Audit log
 *
 * Creates the audit log table on installations that predate the "audit"
 * stream (d41d8cd9 = no signature recorded yet).
 */

CREATE TABLE IF NOT EXISTS `%TABLE_PREFIX%audit_log` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `category` varchar(16) NOT NULL DEFAULT '',
  `action` varchar(32) NOT NULL DEFAULT '',
  `object_type` varchar(32) NOT NULL DEFAULT '',
  `object_id` int(11) unsigned NOT NULL DEFAULT '0',
  `object_name` varchar(255) NOT NULL DEFAULT '',
  `staff_id` int(11) unsigned NOT NULL DEFAULT '0',
  `user_id` int(11) unsigned NOT NULL DEFAULT '0',
  `username` varchar(128) NOT NULL DEFAULT '',
  `description` text,
  `ip` varchar(64) NOT NULL DEFAULT '',
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `created` (`created`),
  KEY `category` (`category`)
) DEFAULT CHARSET=utf8;

-- Finished with patch
INSERT INTO `%TABLE_PREFIX%config` (`namespace`, `key`, `value`)
    VALUES ('audit', 'schema_signature', '9965208d8bc515df98f9111eb885548c')
    ON DUPLICATE KEY UPDATE `value` = '9965208d8bc515df98f9111eb885548c';
