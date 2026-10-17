<?php
if (!defined('OSTSCPINC') || !$thisstaff || !$thisstaff->isAdmin()) die('Access Denied');

$cats = AuditLog::getCategories();
$colors = array('ticket' => '#2a7ae2', 'comment' => '#7f8c8d', 'stage' => '#8e44ad',
    'login' => '#16a085', 'config' => '#d35400', 'cron' => '#2c3e50', 'system' => '#c0392b');
?>
<h2><?php echo __('Audit'); ?></h2>
<p class="faded"><?php echo __('Everything that happened in the helpdesk: ticket events, messages and notes, stage changes, logins, configuration changes, cron actions and the system log.'); ?></p>

<form method="get" action="audit.php" style="margin:10px 0; padding:8px; background:#f7f7f7; border:1px solid #ddd">
    <label><?php echo __('From'); ?>: <input type="date" name="from" value="<?php echo Format::htmlchars($filters['from']); ?>"></label>
    <label><?php echo __('To'); ?>: <input type="date" name="to" value="<?php echo Format::htmlchars($filters['to']); ?>"></label>
    <select name="category">
        <option value=""><?php echo __('All categories'); ?></option>
<?php foreach ($cats as $k => $label) { ?>
        <option value="<?php echo $k; ?>" <?php echo $filters['category'] == $k ? 'selected="selected"' : ''; ?>><?php echo $label; ?></option>
<?php } ?>
    </select>
    <select name="staff_id">
        <option value="0"><?php echo __('All agents'); ?></option>
<?php foreach (Staff::getStaffMembers() as $id => $name) { ?>
        <option value="<?php echo $id; ?>" <?php echo $filters['staff_id'] == $id ? 'selected="selected"' : ''; ?>><?php echo Format::htmlchars((string) $name); ?></option>
<?php } ?>
    </select>
    <input type="text" name="ticket" size="10" placeholder="<?php echo __('Ticket #'); ?>" value="<?php echo Format::htmlchars($filters['ticket']); ?>">
    <input type="text" name="q" size="18" placeholder="<?php echo __('Search text'); ?>" value="<?php echo Format::htmlchars($filters['q']); ?>">
    <button type="submit" class="action-button"><i class="icon-search"></i> <?php echo __('Filter'); ?></button>
    <a href="audit.php" class="button"><?php echo __('Reset'); ?></a>
    <a href="audit.php?<?php echo Http::build_query(array_filter($filters) + array('export' => 'csv')); ?>" class="button no-pjax pull-right"><i class="icon-download"></i> <?php echo __('Export CSV'); ?></a>
</form>

<div><?php echo $total ? $pageNav->showing().' '.__('events') : __('No events match these filters.'); ?></div>
<table class="list" border="0" cellspacing="1" cellpadding="2" width="100%" id="audit-log">
    <thead>
        <tr>
            <th width="13%"><?php echo __('Date'); ?></th>
            <th width="12%"><?php echo __('Category'); ?></th>
            <th width="9%"><?php echo __('Action'); ?></th>
            <th width="13%"><?php echo __('Actor'); ?></th>
            <th width="10%"><?php echo __('Reference'); ?></th>
            <th><?php echo __('Detail'); ?></th>
            <th width="8%"><?php echo __('IP Address'); ?></th>
        </tr>
    </thead>
    <tbody>
<?php foreach ($events as $e) { ?>
        <tr class="audit-<?php echo $e['category']; ?>">
            <td nowrap><?php echo Format::datetime($e['created']); ?></td>
            <td><span style="color:<?php echo $colors[$e['category']] ?? '#333'; ?>; font-weight:bold"><?php
                echo $cats[$e['category']] ?? Format::htmlchars($e['category']); ?></span></td>
            <td><?php echo Format::htmlchars($e['action']); ?></td>
            <td><?php echo Format::htmlchars($e['actor']); ?></td>
            <td><?php
                if ($e['ticket_id'])
                    echo sprintf('<a href="tickets.php?id=%d">#%s</a>', $e['ticket_id'], Format::htmlchars($e['ref']));
                else
                    echo Format::htmlchars($e['ref']); ?></td>
            <td><?php echo Format::htmlchars(AuditLog::describe($e)); ?></td>
            <td><?php echo Format::htmlchars($e['ip']); ?></td>
        </tr>
<?php } ?>
    </tbody>
</table>
<?php if ($total > $limit) { ?>
<div class="pull-left" style="margin-top:8px"><?php echo __('Page').': '.$pageNav->getPageLinks(); ?></div>
<?php } ?>
