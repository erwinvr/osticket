<?php
/*********************************************************************
    workflows.php

    Ticket workflows: stages (department + team) a ticket goes through,
    chosen by help topic, with the derivations allowed between stages.
**********************************************************************/
require('admin.inc.php');
require_once(INCLUDE_DIR.'class.workflow.php');

$workflow = null;
if ($_REQUEST['id'] && !($workflow = Workflow::lookup($_REQUEST['id'])))
    $errors['err'] = sprintf(__('%s: Unknown or invalid'), __('workflow'));

if ($_POST) {
    switch (strtolower($_POST['do'])) {
    case 'update':
        if (!$workflow) {
            $errors['err'] = sprintf(__('%s: Unknown or invalid'), __('workflow'));
        } elseif ($workflow->update($_POST, $errors)) {
            $msg = sprintf(__('Successfully updated %s.'), __('this workflow'));
        } elseif (!$errors['err']) {
            $errors['err'] = sprintf('%s %s',
                sprintf(__('Unable to update %s.'), __('this workflow')),
                __('Correct any errors below and try again.'));
        }
        break;
    case 'create':
        $workflow = Workflow::create();
        if ($workflow->update($_POST, $errors)) {
            $msg = sprintf(__('Successfully added %s.'),
                Format::htmlchars($workflow->getName()));
            $_REQUEST['a'] = null;
        } else {
            if (!$errors['err'])
                $errors['err'] = sprintf('%s %s',
                    sprintf(__('Unable to add %s.'), __('this workflow')),
                    __('Correct any errors below and try again.'));
            $workflow = null;
            $_REQUEST['a'] = 'add';
        }
        break;
    case 'delete':
        if (!$workflow) {
            $errors['err'] = sprintf(__('%s: Unknown or invalid'), __('workflow'));
        } elseif ($workflow->getNumTickets()) {
            $errors['err'] = __('This workflow has tickets and cannot be deleted. Deactivate it instead.');
        } elseif ($workflow->delete()) {
            $msg = sprintf(__('Successfully deleted %s.'), __('this workflow'));
            $workflow = null;
        } else {
            $errors['err'] = sprintf(__('Unable to delete %s.'), __('this workflow'));
        }
        break;
    default:
        $errors['err'] = __('Unknown action');
    }
}

$page = 'workflows.inc.php';
if ($workflow || ($_REQUEST['a'] && !strcasecmp($_REQUEST['a'], 'add')))
    $page = 'workflow.inc.php';

$nav->setTabActive('manage');
require(STAFFINC_DIR.'header.inc.php');
require(STAFFINC_DIR.$page);
include(STAFFINC_DIR.'footer.inc.php');
