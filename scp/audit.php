<?php
/*********************************************************************
    audit.php

    Dashboard > Audit: unified log of ticket events, messages and notes,
    workflow stage changes, logins, configuration changes, cron actions
    and the system log. Administrators only.
**********************************************************************/
require('staff.inc.php');
require_once INCLUDE_DIR.'class.workflow.php';
require_once INCLUDE_DIR.'class.audit.php';

if (!$thisstaff->isAdmin())
    Http::response(403, __('Access Denied'));

$isDate = function($d) {
    return $d && ($dt = DateTime::createFromFormat('!Y-m-d', $d)) && $dt->format('Y-m-d') === $d;
};
$filters = array(
    'from' => $isDate($_GET['from'] ?? '') ? $_GET['from'] : date('Y-m-d', strtotime('-7 days')),
    'to' => $isDate($_GET['to'] ?? '') ? $_GET['to'] : '',
    'category' => array_key_exists($_GET['category'] ?? '', AuditLog::getCategories()) ? $_GET['category'] : '',
    'staff_id' => (int) ($_GET['staff_id'] ?? 0),
    'ticket' => trim($_GET['ticket'] ?? ''),
    'q' => trim($_GET['q'] ?? ''),
);

// CSV export of the filtered events (up to 10,000)
if (($_GET['export'] ?? '') == 'csv') {
    list($rows) = AuditLog::search($filters, 0, 10000);
    $out = fopen('php://temp', 'w+');
    fputcsv($out, array(__('Date'), __('Category'), __('Action'), __('Actor'),
        __('Reference'), __('Detail'), __('IP Address')));
    $cats = AuditLog::getCategories();
    foreach ($rows as $r)
        fputcsv($out, array(Format::datetime($r['created']), $cats[$r['category']] ?? $r['category'],
            $r['action'], $r['actor'], $r['ref'], AuditLog::describe($r), $r['ip']));
    rewind($out);
    Http::download('audit-'.date('Ymd-His').'.csv', 'text/csv', stream_get_contents($out));
    exit;
}

$page = max(1, (int) ($_GET['p'] ?? 1));
$limit = 50;
list($events, $total) = AuditLog::search($filters, ($page - 1) * $limit, $limit);
$pageNav = new Pagenate($total, $page, $limit);
$pageNav->setURL('audit.php', array_filter($filters));

$nav->setTabActive('dashboard');
$ost->setPageTitle(__('Audit'));
require(STAFFINC_DIR.'header.inc.php');
require(STAFFINC_DIR.'audit.inc.php');
include(STAFFINC_DIR.'footer.inc.php');
