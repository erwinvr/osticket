/**
 * @version v1.18.4-workflow
 * @title Ticket workflows (stages and allowed derivations)
 *
 * Creates the workflow tables on installations that predate the
 * "workflow" stream (d41d8cd9 = no signature recorded yet).
 */

CREATE TABLE IF NOT EXISTS `%TABLE_PREFIX%workflow` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `name` varchar(128) NOT NULL DEFAULT '',
  `flags` int(10) unsigned NOT NULL DEFAULT '1',
  `notes` text,
  `created` datetime NOT NULL,
  `updated` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `%TABLE_PREFIX%workflow_topic` (
  `topic_id` int(11) unsigned NOT NULL,
  `workflow_id` int(11) unsigned NOT NULL,
  PRIMARY KEY (`topic_id`),
  KEY `workflow_id` (`workflow_id`)
) DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `%TABLE_PREFIX%workflow_stage` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `workflow_id` int(11) unsigned NOT NULL,
  `name` varchar(128) NOT NULL DEFAULT '',
  `dept_id` int(11) unsigned NOT NULL DEFAULT '0',
  `team_id` int(11) unsigned NOT NULL DEFAULT '0',
  `flags` int(10) unsigned NOT NULL DEFAULT '0',
  `sort` int(11) unsigned NOT NULL DEFAULT '0',
  `created` datetime NOT NULL,
  `updated` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `workflow_id` (`workflow_id`)
) DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `%TABLE_PREFIX%workflow_transition` (
  `from_id` int(11) unsigned NOT NULL,
  `to_id` int(11) unsigned NOT NULL,
  PRIMARY KEY (`from_id`, `to_id`)
) DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `%TABLE_PREFIX%ticket_workflow` (
  `ticket_id` int(11) unsigned NOT NULL,
  `workflow_id` int(11) unsigned NOT NULL,
  `stage_id` int(11) unsigned NOT NULL,
  `updated` datetime NOT NULL,
  PRIMARY KEY (`ticket_id`),
  KEY `stage_id` (`stage_id`)
) DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `%TABLE_PREFIX%workflow_history` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `ticket_id` int(11) unsigned NOT NULL,
  `from_stage_id` int(11) unsigned NOT NULL DEFAULT '0',
  `from_name` varchar(128) NOT NULL DEFAULT '',
  `to_stage_id` int(11) unsigned NOT NULL,
  `to_name` varchar(128) NOT NULL DEFAULT '',
  `staff_id` int(11) unsigned NOT NULL DEFAULT '0',
  `comments` text,
  `created` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ticket_id` (`ticket_id`)
) DEFAULT CHARSET=utf8;

-- Finished with patch
INSERT INTO `%TABLE_PREFIX%config` (`namespace`, `key`, `value`)
    VALUES ('workflow', 'schema_signature', '5ec8959efa89013d83259398418e7014')
    ON DUPLICATE KEY UPDATE `value` = '5ec8959efa89013d83259398418e7014';
