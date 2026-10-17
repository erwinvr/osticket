/**
 * @version v1.18.4-workflow2
 * @title Stage SLA and diagram position
 *
 * Each stage can have its own SLA plan (measured from when the ticket
 * enters the stage) and a position in the graphical workflow editor.
 */

ALTER TABLE `%TABLE_PREFIX%workflow_stage`
    ADD `sla_id` int(11) unsigned NOT NULL DEFAULT '0' AFTER `sort`,
    ADD `pos_x` int(11) NOT NULL DEFAULT '-1' AFTER `sla_id`,
    ADD `pos_y` int(11) NOT NULL DEFAULT '-1' AFTER `pos_x`;

-- Finished with patch
UPDATE `%TABLE_PREFIX%config`
    SET `value` = '9ecaaa6551e64f739f9d94f2f6dc05fa'
    WHERE `namespace` = 'workflow' AND `key` = 'schema_signature';
