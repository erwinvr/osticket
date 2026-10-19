/**
 * @version v1.18.4-automation
 * @title Ticket filter automations by help topic and form fields
 *
 * Creates the automation tables on installations that predate the
 * "automation" stream (d41d8cd9 = no signature recorded yet).
 */

CREATE TABLE IF NOT EXISTS `%TABLE_PREFIX%filter_topic` (
  `filter_id` int(11) unsigned NOT NULL,
  `topic_id` int(11) unsigned NOT NULL,
  PRIMARY KEY (`filter_id`),
  KEY `topic_id` (`topic_id`)
) DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `%TABLE_PREFIX%filter_condition` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `filter_id` int(11) unsigned NOT NULL,
  `field_id` int(11) unsigned NOT NULL,
  `op` varchar(16) NOT NULL DEFAULT '',
  `value` text,
  `sort` int(11) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `filter_id` (`filter_id`)
) DEFAULT CHARSET=utf8;

-- Finished with patch
INSERT INTO `%TABLE_PREFIX%config` (`namespace`, `key`, `value`)
    VALUES ('automation', 'schema_signature', 'c23c7f14cb412c2f64b45ad444fe4a38')
    ON DUPLICATE KEY UPDATE `value` = 'c23c7f14cb412c2f64b45ad444fe4a38';
