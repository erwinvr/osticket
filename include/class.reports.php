<?php
/*********************************************************************
    class.reports.php

    Agent panel > Reports: time analysis of tickets by agent, help topic
    and workflow stage, a summary and a ticket-level listing. Every report
    can be exported to CSV.

    Times are calendar hours:
      - resolution: from creation to close (closed tickets)
      - first response: from creation to the first agent response
      - time in stage: from entering a stage until the next derivation,
        the close, or now (tickets still in the stage)
    A ticket breached its SLA when it was closed after its due date, or is
    still open past it.
**********************************************************************/
require_once INCLUDE_DIR.'class.workflow.php';

class TicketReports {

    static function getReports() {
        return array(
            'summary' => __('Summary'),
            'agents'  => __('Agents'),
            'topics'  => __('Help Topics'),
            'stages'  => __('Stages'),
            'tickets' => __('Tickets'),
        );
    }

    /* ---------- Filters ---------- */

    static function isDate($d) {
        $dt = DateTime::createFromFormat('!Y-m-d', $d);
        return $dt && $dt->format('Y-m-d') === $d;
    }

    static function getFilters($source, &$errors=array()) {
        $f = array(
            'from' => trim($source['from'] ?? ''),
            'to' => trim($source['to'] ?? ''),
            'dept_id' => (int) ($source['dept_id'] ?? 0),
            'topic_id' => (int) ($source['topic_id'] ?? 0),
        );
        if ($f['from'] === '' && $f['to'] === '')
            $f['from'] = date('Y-m-d', strtotime('-30 days'));
        if ($f['from'] !== '' && !static::isDate($f['from']))
            $errors['err'] = __('Invalid "from" date');
        elseif ($f['to'] !== '' && !static::isDate($f['to']))
            $errors['err'] = __('Invalid "to" date');
        elseif ($f['from'] && $f['to'] && $f['from'] > $f['to'])
            $errors['err'] = __('The "from" date must be on or before the "to" date');
        return $f;
    }

    // WHERE clause for the tickets in the report (alias t)
    static function where($f, $staff) {
        $w = array('t.ticket_pid IS NULL');
        if ($f['from'])
            $w[] = 't.created >= '.db_input($f['from'].' 00:00:00');
        if ($f['to'])
            $w[] = 't.created <= '.db_input($f['to'].' 23:59:59');
        if ($f['dept_id'])
            $w[] = 't.dept_id = '.(int) $f['dept_id'];
        if ($f['topic_id'])
            $w[] = 't.topic_id = '.(int) $f['topic_id'];
        // Agents see the departments they have access to; admins see all
        if ($staff && !$staff->isAdmin()) {
            $depts = array_map('intval', $staff->getDepts() ?: array(0));
            $w[] = '(t.dept_id IN ('.implode(',', $depts).') OR t.staff_id = '.(int) $staff->getId().')';
        }
        return ' WHERE '.implode(' AND ', $w);
    }

    // Per-ticket figures shared by all the reports
    static function baseSql($f, $staff) {
        return 'SELECT t.ticket_id, t.number, t.created, t.closed, t.staff_id, t.team_id,
                t.dept_id, t.topic_id, st.state, st.name AS status,
                IF(st.state = \'closed\', TIMESTAMPDIFF(SECOND, t.created, t.closed) / 3600, NULL) AS resolution_h,
                TIMESTAMPDIFF(SECOND, t.created, fr.first_response) / 3600 AS first_response_h,
                IF(st.state = \'open\', TIMESTAMPDIFF(SECOND, t.created, NOW()) / 3600, NULL) AS age_h,
                (COALESCE(t.duedate, t.est_duedate) IS NOT NULL AND (
                    (st.state = \'closed\' AND t.closed > COALESCE(t.duedate, t.est_duedate))
                    OR (st.state = \'open\' AND (t.isoverdue = 1 OR NOW() > COALESCE(t.duedate, t.est_duedate)))
                )) AS sla_breached
            FROM '.TICKET_TABLE.' t
            JOIN '.TICKET_STATUS_TABLE.' st ON st.id = t.status_id
            LEFT JOIN (SELECT th.object_id AS ticket_id, MIN(e.created) AS first_response
                FROM '.THREAD_TABLE.' th
                JOIN '.THREAD_ENTRY_TABLE.' e ON e.thread_id = th.id AND e.type = \'R\'
                WHERE th.object_type = \'T\' GROUP BY th.object_id) fr ON fr.ticket_id = t.ticket_id'
            .static::where($f, $staff)
            .' AND st.state IN (\'open\', \'closed\')';
    }

    static function fetchAll($sql) {
        $rows = array();
        $res = db_query($sql);
        while ($res && ($r = db_fetch_array($res)))
            $rows[] = $r;
        return $rows;
    }

    /* ---------- Reports ----------
     * Each returns array(columns => [key => [label, type]], rows => [...],
     *   chart => column to draw as bars, note => text)
     * Types: text, int, hours, pct
     */

    static function summary($f, $staff) {
        $r = static::fetchAll('SELECT COUNT(*) AS created,
                SUM(b.state = \'closed\') AS closed, SUM(b.state = \'open\') AS open,
                AVG(b.resolution_h) AS avg_resolution_h, AVG(b.first_response_h) AS avg_first_response_h,
                AVG(b.age_h) AS avg_age_h,
                SUM(b.sla_breached) AS sla_breached,
                100 * SUM(b.sla_breached) / NULLIF(COUNT(*), 0) AS sla_breached_pct
            FROM ('.static::baseSql($f, $staff).') b');
        $s = $r[0];
        $rows = array(
            array('metric' => __('Tickets created'), 'value' => (int) $s['created'], 'type' => 'int'),
            array('metric' => __('Closed'), 'value' => (int) $s['closed'], 'type' => 'int'),
            array('metric' => __('Still open'), 'value' => (int) $s['open'], 'type' => 'int'),
            array('metric' => __('Average resolution time'), 'value' => $s['avg_resolution_h'], 'type' => 'hours'),
            array('metric' => __('Average first response time'), 'value' => $s['avg_first_response_h'], 'type' => 'hours'),
            array('metric' => __('Average age of open tickets'), 'value' => $s['avg_age_h'], 'type' => 'hours'),
            array('metric' => __('Tickets past SLA'), 'value' => (int) $s['sla_breached'], 'type' => 'int'),
            array('metric' => __('% past SLA'), 'value' => $s['sla_breached_pct'], 'type' => 'pct'),
        );
        return array(
            'columns' => array('metric' => array(__('Metric'), 'text'), 'value' => array(__('Value'), 'mixed')),
            'rows' => $rows,
        );
    }

    // Agent or help topic: same figures grouped by a column
    static function grouped($f, $staff, $by) {
        $group = $by == 'agents' ? 'b.staff_id' : 'b.topic_id';
        $rows = static::fetchAll('SELECT '.$group.' AS gid, COUNT(*) AS tickets,
                SUM(b.state = \'closed\') AS closed, SUM(b.state = \'open\') AS open,
                AVG(b.resolution_h) AS avg_resolution_h, MAX(b.resolution_h) AS max_resolution_h,
                AVG(b.first_response_h) AS avg_first_response_h, AVG(b.age_h) AS avg_age_h,
                SUM(b.sla_breached) AS sla_breached,
                100 * SUM(b.sla_breached) / NULLIF(COUNT(*), 0) AS sla_breached_pct
            FROM ('.static::baseSql($f, $staff).') b'
            .($by == 'agents' ? ' WHERE b.staff_id > 0' : '')
            .' GROUP BY '.$group);
        foreach ($rows as &$r) {
            if ($by == 'agents')
                $r['name'] = ($s = Staff::lookup($r['gid'])) ? (string) $s->getName() : '#'.$r['gid'];
            else
                $r['name'] = $r['gid'] ? (Topic::getTopicName($r['gid']) ?: '#'.$r['gid']) : __('No help topic');
        }
        // Slowest first; ties (or no closed tickets) by open-ticket age
        usort($rows, function($a, $b) {
            return ($b['avg_resolution_h'] ?? -1) <=> ($a['avg_resolution_h'] ?? -1)
                ?: ($b['avg_age_h'] ?? -1) <=> ($a['avg_age_h'] ?? -1);
        });
        return array(
            'columns' => array(
                'name' => array($by == 'agents' ? __('Agent') : __('Help Topic'), 'text'),
                'tickets' => array(__('Tickets'), 'int'),
                'closed' => array(__('Closed'), 'int'),
                'open' => array(__('Open'), 'int'),
                'avg_resolution_h' => array(__('Avg. resolution'), 'hours'),
                'max_resolution_h' => array(__('Max. resolution'), 'hours'),
                'avg_first_response_h' => array(__('Avg. first response'), 'hours'),
                'avg_age_h' => array(__('Avg. age of open'), 'hours'),
                'sla_breached' => array(__('Past SLA'), 'int'),
                'sla_breached_pct' => array(__('% past SLA'), 'pct'),
            ),
            'rows' => $rows,
            'chart' => 'avg_resolution_h',
            'note' => $by == 'agents'
                ? __('Tickets are counted for the agent assigned now (for closed tickets, who closed them). Sorted from the slowest average resolution.')
                : __('Sorted from the slowest average resolution.'),
        );
    }

    // Time spent in each stage: one row per visit to a stage
    static function stageVisitsSql($f, $staff) {
        return 'SELECT v.*, TIMESTAMPDIFF(SECOND, v.entered,
                    COALESCE(v.left_at, IF(v.state = \'closed\', v.closed, NOW()))) / 3600 AS hours
            FROM (SELECT h.ticket_id, h.to_stage_id AS stage_id, h.created AS entered,
                    LEAD(h.created) OVER (PARTITION BY h.ticket_id ORDER BY h.id) AS left_at,
                    b.topic_id, b.state, b.closed
                FROM '.WORKFLOW_HISTORY_TABLE.' h
                JOIN ('.static::baseSql($f, $staff).') b ON b.ticket_id = h.ticket_id) v';
    }

    static function stages($f, $staff) {
        $rows = static::fetchAll('SELECT s.stage_id, COUNT(*) AS visits,
                COUNT(DISTINCT s.ticket_id) AS tickets,
                AVG(s.hours) AS avg_h, MAX(s.hours) AS max_h,
                SUM(s.left_at IS NULL AND s.state = \'open\') AS now_in_stage
            FROM ('.static::stageVisitsSql($f, $staff).') s GROUP BY s.stage_id');
        $out = array();
        foreach ($rows as $r) {
            if (!($stage = WorkflowStage::lookup($r['stage_id'])))
                continue;
            $w = $stage->getWorkflow();
            $r['workflow'] = $w ? $w->getName() : '';
            $r['stage'] = $stage->getName();
            $r['sort'] = sprintf('%s/%05d', $r['workflow'], $stage->sort);
            $out[] = $r;
        }
        usort($out, function($a, $b) { return strcmp($a['sort'], $b['sort']); });
        return array(
            'columns' => array(
                'workflow' => array(__('Workflow'), 'text'),
                'stage' => array(__('Stage'), 'text'),
                'tickets' => array(__('Tickets'), 'int'),
                'visits' => array(__('Times entered'), 'int'),
                'avg_h' => array(__('Avg. time in stage'), 'hours'),
                'max_h' => array(__('Max. time in stage'), 'hours'),
                'now_in_stage' => array(__('Open now in stage'), 'int'),
            ),
            'rows' => $out,
            'chart' => 'avg_h',
            'note' => __('Time from entering a stage until the next derivation, the close, or now. A ticket that comes back to a stage is counted once per visit.'),
        );
    }

    // Average hours in each stage by help topic: which topics take longest, and where
    static function topicStageMatrix($f, $staff) {
        $rows = static::fetchAll('SELECT s.topic_id, s.stage_id, AVG(s.hours) AS avg_h, COUNT(*) AS visits
            FROM ('.static::stageVisitsSql($f, $staff).') s GROUP BY s.topic_id, s.stage_id');
        $m = array();   // workflow => [stages, topics, cells]
        foreach ($rows as $r) {
            if (!($stage = WorkflowStage::lookup($r['stage_id'])) || !($w = $stage->getWorkflow()))
                continue;
            $wid = $w->getId();
            if (!isset($m[$wid]))
                $m[$wid] = array('workflow' => $w, 'stages' => $w->getStages(), 'topics' => array(), 'cells' => array());
            $tid = (int) $r['topic_id'];
            $m[$wid]['topics'][$tid] = $tid ? (Topic::getTopicName($tid) ?: '#'.$tid) : __('No help topic');
            $m[$wid]['cells'][$tid][(int) $r['stage_id']] = (float) $r['avg_h'];
        }
        return $m;
    }

    static function tickets($f, $staff) {
        $rows = static::fetchAll('SELECT b.*, c.subject, tw.stage_id
            FROM ('.static::baseSql($f, $staff).') b
            LEFT JOIN '.TABLE_PREFIX.'ticket__cdata c ON c.ticket_id = b.ticket_id
            LEFT JOIN '.TICKET_WORKFLOW_TABLE.' tw ON tw.ticket_id = b.ticket_id
            ORDER BY b.created DESC LIMIT 5000');
        $depts = Dept::getDepartments();
        foreach ($rows as &$r) {
            $r['topic'] = $r['topic_id'] ? Topic::getTopicName($r['topic_id']) : '';
            $r['dept'] = $depts[$r['dept_id']] ?? '';
            $r['agent'] = $r['staff_id'] && ($s = Staff::lookup($r['staff_id'])) ? (string) $s->getName() : '';
            $r['stage'] = $r['stage_id'] && ($st = WorkflowStage::lookup($r['stage_id'])) ? $st->getName() : '';
            $r['sla_breached'] = $r['sla_breached'] ? __('Yes') : __('No');
        }
        return array(
            'columns' => array(
                'number' => array(__('Ticket'), 'ticket'),
                'created' => array(__('Created'), 'datetime'),
                'subject' => array(__('Subject'), 'text'),
                'topic' => array(__('Help Topic'), 'text'),
                'dept' => array(__('Department'), 'text'),
                'agent' => array(__('Agent'), 'text'),
                'stage' => array(__('Stage'), 'text'),
                'status' => array(__('Status'), 'text'),
                'closed' => array(__('Closed'), 'datetime'),
                'first_response_h' => array(__('First response'), 'hours'),
                'resolution_h' => array(__('Resolution'), 'hours'),
                'sla_breached' => array(__('Past SLA'), 'text'),
            ),
            'rows' => $rows,
            'note' => __('Up to 5,000 tickets, newest first. Export to CSV to analyze them in a spreadsheet.'),
        );
    }

    static function run($report, $f, $staff) {
        switch ($report) {
        case 'agents':
        case 'topics':
            return static::grouped($f, $staff, $report);
        case 'stages':
            return static::stages($f, $staff);
        case 'tickets':
            return static::tickets($f, $staff);
        default:
            return static::summary($f, $staff);
        }
    }

    /* ---------- Formatting ---------- */

    // 35 min, 12.5 h, 3.2 d
    static function duration($hours) {
        if ($hours === null || $hours === '')
            return '—';
        $hours = (float) $hours;
        if ($hours < 1)
            return sprintf(__('%d min'), round($hours * 60));
        if ($hours < 48)
            return sprintf(__('%s h'), number_format($hours, 1));
        return sprintf(__('%s d'), number_format($hours / 24, 1));
    }

    static function display($value, $type) {
        switch ($type) {
        case 'hours':
            return static::duration($value);
        case 'pct':
            return $value === null ? '—' : round($value).'%';
        case 'int':
            return (string) (int) $value;
        case 'datetime':
            return $value ? Format::datetime($value) : '—';
        default:
            return (string) $value;
        }
    }

    // CSV keeps raw numbers (hours with 2 decimals) for spreadsheets
    static function csv($report, $data) {
        $out = fopen('php://temp', 'w+');
        fwrite($out, "\xEF\xBB\xBF");   // UTF-8 BOM so Excel shows accents
        $cols = $data['columns'];
        fputcsv($out, array_map(function($c) {
            return $c[1] == 'hours' ? $c[0].' (h)' : $c[0];
        }, $cols));
        foreach ($data['rows'] as $r) {
            $line = array();
            foreach ($cols as $k => $c) {
                $v = $r[$k] ?? '';
                $type = $c[1] == 'mixed' ? ($r['type'] ?? 'text') : $c[1];
                if ($type == 'hours' || $type == 'pct')
                    $v = ($v === null || $v === '') ? '' : round((float) $v, 2);
                $line[] = $v;
            }
            fputcsv($out, $line);
        }
        rewind($out);
        return stream_get_contents($out);
    }
}
