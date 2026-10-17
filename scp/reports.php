<?php
/*********************************************************************
    reports.php

    Reports: time analysis by agent, help topic and workflow stage, a
    summary and a ticket listing, with CSV export.
**********************************************************************/
require('staff.inc.php');
require_once INCLUDE_DIR.'class.reports.php';

$reports = TicketReports::getReports();
$report = array_key_exists($_GET['r'] ?? '', $reports) ? $_GET['r'] : 'summary';
$filters = TicketReports::getFilters($_GET, $errors);

$data = $matrix = null;
if (!$errors) {
    $data = TicketReports::run($report, $filters, $thisstaff);
    if ($report == 'stages')
        $matrix = TicketReports::topicStageMatrix($filters, $thisstaff);

    if (($_GET['export'] ?? '') == 'csv') {
        Http::download(sprintf('report-%s-%s.csv', $report, date('Ymd-His')),
            'text/csv; charset=UTF-8', TicketReports::csv($report, $data));
        exit;
    }
}

$nav->setTabActive('reports', 'reports.php?r='.$report);
$ost->setPageTitle(__('Reports').' :: '.$reports[$report]);
require(STAFFINC_DIR.'header.inc.php');
require(STAFFINC_DIR.'reports.inc.php');
include(STAFFINC_DIR.'footer.inc.php');
