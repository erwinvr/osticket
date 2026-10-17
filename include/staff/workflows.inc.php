<?php
if (!defined('OSTADMININC') || !$thisstaff || !$thisstaff->isAdmin()) die('Access Denied');

$workflows = Workflow::objects()->order_by('name');
?>
<div id="basic_search">
    <div class="pull-right">
        <a href="workflows.php?a=add" class="green button action-button"><i class="icon-plus-sign"></i> <?php echo __('Add New Workflow'); ?></a>
    </div>
    <h2><?php echo __('Ticket Workflows'); ?></h2>
</div>
<div class="clear"></div>
<p class="faded"><?php echo __(
    'A workflow is chosen by the help topic of a new ticket. The ticket goes through stages, each with a department and a team, and can only be derived to the stages allowed from its current one. A final stage closes the ticket.'
); ?></p>
<table class="list" border="0" cellspacing="1" cellpadding="0" width="940">
    <thead>
        <tr>
            <th width="28%"><?php echo __('Name'); ?></th>
            <th width="10%"><?php echo __('Status'); ?></th>
            <th width="12%"><?php echo __('Transfers'); ?></th>
            <th width="8%"><?php echo __('Stages'); ?></th>
            <th width="30%"><?php echo __('Help Topics'); ?></th>
            <th width="12%"><?php echo __('Tickets'); ?></th>
        </tr>
    </thead>
    <tbody>
<?php
$found = false;
foreach ($workflows as $w) {
    $found = true;
    $topics = array();
    foreach ($w->getTopicIds() as $tid)
        $topics[] = Topic::getTopicName($tid);
    $open = TicketWorkflow::objects()->filter(array(
        'workflow_id' => $w->getId(),
    ))->count(); ?>
        <tr>
            <td><a href="workflows.php?id=<?php echo $w->getId(); ?>"><?php
                echo Format::htmlchars($w->getName()); ?></a></td>
            <td><?php echo $w->isActive() ? __('Active') : '<b>'.__('Disabled').'</b>'; ?></td>
            <td><?php echo $w->isStrict() ? __('Fixed') : __('Free'); ?></td>
            <td><?php echo count($w->getStages()); ?></td>
            <td><?php echo $topics ? Format::htmlchars(implode(', ', $topics))
                : '<em class="faded">'.__('None').'</em>'; ?></td>
            <td><?php echo $open; ?></td>
        </tr>
<?php
}
if (!$found) { ?>
        <tr><td colspan="6"><?php echo __('No workflows yet. Add one to get started.'); ?></td></tr>
<?php
} ?>
    </tbody>
</table>
