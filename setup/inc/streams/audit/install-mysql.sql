/**
 * @version v1.18.4-audit
 * @title Audit log
 *
 * Events not recorded elsewhere by osTicket: logins, changes to
 * configuration objects (agents, teams, workflows, settings...) and the
 * actions taken by the cron.
 */

DROP TABLE IF EXISTS `%TABLE_PREFIX%audit_log`;
CREATE TABLE `%TABLE_PREFIX%audit_log` (
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
