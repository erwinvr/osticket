/**
 * @version v1.18.4-automation
 * @title Ticket filter automations by help topic and form fields
 *
 * Extends the ticket filters (Admin Panel > Manage > Filters): the help
 * topic an automation applies to, and conditions on form fields by field
 * type (lists, numbers, dates, checkboxes, text).
 */

DROP TABLE IF EXISTS `%TABLE_PREFIX%filter_topic`;
CREATE TABLE `%TABLE_PREFIX%filter_topic` (
  `filter_id` int(11) unsigned NOT NULL,
  `topic_id` int(11) unsigned NOT NULL,
  PRIMARY KEY (`filter_id`),
  KEY `topic_id` (`topic_id`)
) DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `%TABLE_PREFIX%filter_condition`;
CREATE TABLE `%TABLE_PREFIX%filter_condition` (
  `id` int(11) unsigned NOT NULL auto_increment,
  `filter_id` int(11) unsigned NOT NULL,
  `field_id` int(11) unsigned NOT NULL,
  `op` varchar(16) NOT NULL DEFAULT '',
  `value` text,
  `sort` int(11) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `filter_id` (`filter_id`)
) DEFAULT CHARSET=utf8;
