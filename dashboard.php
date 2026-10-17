<?php
/*********************************************************************
    dashboard.php

    Public dashboard of the support center: tickets by help topic and
    workflow stage, and SLA breaches by stage, with optional automatic
    refresh. Aggregated counts only.
**********************************************************************/
require('client.inc.php');
require_once INCLUDE_DIR.'class.workflow.php';

$dashboard = WorkflowDashboard::getData();

// Auto refresh asks only for the figures
if (isset($_GET['partial'])) {
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-store');
    include CLIENTINC_DIR.'dashboard-data.inc.php';
    exit;
}

$section = 'dashboard';
$nav->setActiveNav('dashboard');
require(CLIENTINC_DIR.'header.inc.php');
require(CLIENTINC_DIR.'dashboard.inc.php');
require(CLIENTINC_DIR.'footer.inc.php');
