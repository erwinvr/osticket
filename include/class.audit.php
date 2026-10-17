<?php
/*********************************************************************
    class.audit.php

    Audit trail shown in the agent dashboard (Dashboard > Audit).

    It combines what osTicket already records:
      - ticket and task events (thread_event): created, assigned,
        transferred, closed, reopened, edited, overdue...
      - messages, responses and internal notes (thread_entry)
      - workflow stage changes (workflow_history)
      - the system log (syslog)
    with what it doesn't record by default, kept in the audit_log table
    (its own upgrade stream, "audit"):
      - agent and user logins, logouts and failed login attempts
      - changes to configuration objects (agents, teams, departments,
        help topics, settings, workflows...)
      - actions taken by the cron (scheduled vacations, team assignments)
**********************************************************************/

if (!defined('AUDIT_LOG_TABLE'))
    define('AUDIT_LOG_TABLE', TABLE_PREFIX.'audit_log');

class AuditLog extends VerySimpleModel {
    static $meta = array(
        'table' => AUDIT_LOG_TABLE,
        'pk' => array('id'),
        'ordering' => array('-created'),
    );

    // Categories shown in the audit view
    static function getCategories() {
        return array(
            'ticket'  => __('Ticket events'),
            'comment' => __('Messages and notes'),
            'stage'   => __('Stage changes'),
            'login'   => __('Logins'),
            'config'  => __('Configuration changes'),
            'cron'    => __('Cron actions'),
            'system'  => __('System log'),
        );
    }

    // Objects whose changes are already recorded in the ticket threads,
    // or that change too often to be useful here
    static $skipClasses = array('Ticket', 'Task', 'TicketThread', 'TaskThread',
        'ThreadEntry', 'MessageThreadEntry', 'ResponseThreadEntry',
        'NoteThreadEntry', 'Lock', 'Draft', 'Attachment', 'AuditLog',
        'TicketWorkflow', 'WorkflowHistory');
    static $skipConfigKeys = array('last_assigned', 'schema_signature');

    static function log($category, $action, $description='', $object=null,
            $actor=null) {
        global $thisstaff, $thisclient;

        $entry = new static(array(
            'category' => $category,
            'action' => substr($action, 0, 32),
            'description' => $description,
            'ip' => osTicket::is_cli() ? 'cli' : ($_SERVER['REMOTE_ADDR'] ?? ''),
            'created' => SqlFunction::NOW(),
        ));
        if (is_object($object)) {
            $entry->object_type = substr(get_class($object), 0, 32);
            $entry->object_id = (int) (method_exists($object, 'getId') ? $object->getId() : 0);
            $entry->object_name = substr(static::nameOf($object), 0, 255);
        }
        $actor = $actor ?: ($thisstaff ?: $thisclient);
        if ($actor instanceof Staff) {
            $entry->staff_id = $actor->getId();
            $entry->username = $actor->getUserName();
        } elseif ($actor && method_exists($actor, 'getId')) {
            $entry->user_id = $actor->getId();
            $entry->username = (string) $actor->getName();
        } elseif (is_string($actor)) {
            $entry->username = $actor;
        } else {
            $entry->username = 'SYSTEM';
        }
        return $entry->save();
    }

    static function nameOf($object) {
        if ($object instanceof ConfigItem)
            return $object->namespace.'.'.$object->key;
        foreach (array('getName', 'getUserName', 'getTitle') as $m)
            if (method_exists($object, $m))
                try { return (string) $object->$m(); } catch (Throwable $e) {}
        return method_exists($object, '__toString') ? (string) $object : '';
    }

    // Hooks into osTicket's signals (called on every request)
    static function bootstrap() {
        static $done = false;
        if ($done)
            return;
        $done = true;

        Signal::connect('person.login', function($person, $info) {
            if ($person instanceof Staff)
                AuditLog::log('login', 'login', __('Agent logged in'), $person, $person);
            elseif ($person)
                AuditLog::log('login', 'login', __('User logged in'), $person, $person);
        });
        Signal::connect('person.logout', function($person, $info) {
            if ($person instanceof Staff)
                AuditLog::log('login', 'logout', __('Agent logged out'), $person, $person);
            elseif ($person)
                AuditLog::log('login', 'logout', __('User logged out'), $person, $person);
        });
        Signal::connect('auth.login.failed', function($null, $info) {
            AuditLog::log('login', 'failed',
                sprintf(__('Failed login attempt for "%s"'), $info['username'] ?? ''),
                null, (string) ($info['username'] ?? ''));
        });
        foreach (array('created', 'edited', 'deleted') as $what) {
            Signal::connect("object.$what", function($object, $info) use ($what) {
                // Generic per-field signals from every ORM save (sessions,
                // last login...) are noise; screens send their own signals
                if (!is_object($object) || !empty($info['orm_audit'])
                        || in_array(get_class($object), AuditLog::$skipClasses))
                    return;
                if ($object instanceof ConfigItem
                        && in_array($object->key, AuditLog::$skipConfigKeys))
                    return;
                $detail = array();
                foreach ((array) $info as $k => $v)
                    if ($k != 'type' && is_scalar($v))
                        $detail[] = is_bool($v) ? $k : "$k: $v";
                AuditLog::log('config', $what, implode(', ', $detail), $object);
            });
        }
        Signal::connect('cron', function($null, $info) {
            // External cron runs only; autocron runs on every page view
            if (empty($info['autocron']))
                AuditLog::log('cron', 'run', __('Scheduled tasks executed'), null, 'CRON');
        });
    }

    /**
     * Unified, newest-first list of events from all sources.
     *
     * $filters: from, to (Y-m-d), category, staff_id, ticket (number), q
     * Returns array(rows, total)
     */
    static function search($filters, $offset=0, $limit=50) {
        $from = $filters['from'] ?? '';
        $to = $filters['to'] ?? '';
        $cat = $filters['category'] ?? '';
        $staff = (int) ($filters['staff_id'] ?? 0);
        $ticket = trim($filters['ticket'] ?? '');
        $q = trim($filters['q'] ?? '');

        // Date range on each source's own column (keeps indexes useful)
        $range = function($col) use ($from, $to) {
            $w = array();
            if ($from)
                $w[] = "$col >= ".db_input($from.' 00:00:00');
            if ($to)
                $w[] = "$col <= ".db_input($to.' 23:59:59');
            return $w;
        };
        $where = function($w) {
            return $w ? ' WHERE '.implode(' AND ', $w) : '';
        };

        $parts = array();
        if (!$cat || $cat == 'ticket') {
            $w = $range('e.timestamp');
            if ($staff) $w[] = "e.staff_id = $staff";
            if ($ticket) $w[] = 'COALESCE(t.number, a.number) = '.db_input($ticket);
            $parts[] = "SELECT e.timestamp AS created, 'ticket' AS category, ev.name AS action,
                COALESCE(NULLIF(e.username, ''), CONCAT(s.firstname, ' ', s.lastname), '') AS actor,
                e.staff_id AS staff_id,
                CASE e.thread_type WHEN 'T' THEN t.number ELSE a.number END AS ref,
                CASE e.thread_type WHEN 'T' THEN t.ticket_id ELSE 0 END AS ticket_id,
                '' AS title, e.data AS detail, '' AS ip
              FROM ".THREAD_EVENT_TABLE." e
              JOIN ".EVENT_TABLE." ev ON ev.id = e.event_id
              JOIN ".THREAD_TABLE." th ON th.id = e.thread_id
              LEFT JOIN ".TICKET_TABLE." t ON e.thread_type = 'T' AND t.ticket_id = th.object_id
              LEFT JOIN ".TASK_TABLE." a ON e.thread_type = 'A' AND a.id = th.object_id
              LEFT JOIN ".STAFF_TABLE." s ON s.staff_id = e.staff_id"
              .$where($w);
        }
        if (!$cat || $cat == 'comment') {
            $w = $range('te.created');
            if ($staff) $w[] = "te.staff_id = $staff";
            if ($ticket) $w[] = 't.number = '.db_input($ticket);
            $parts[] = "SELECT te.created AS created, 'comment' AS category,
                CASE te.type WHEN 'M' THEN 'message' WHEN 'R' THEN 'response' ELSE 'note' END AS action,
                te.poster AS actor, te.staff_id AS staff_id, t.number AS ref, t.ticket_id AS ticket_id,
                te.title AS title, LEFT(te.body, 400) AS detail, te.ip_address AS ip
              FROM ".THREAD_ENTRY_TABLE." te
              JOIN ".THREAD_TABLE." th ON th.id = te.thread_id AND th.object_type = 'T'
              JOIN ".TICKET_TABLE." t ON t.ticket_id = th.object_id"
              .$where($w);
        }
        if (!$cat || $cat == 'stage') {
            $w = $range('h.created');
            if ($staff) $w[] = "h.staff_id = $staff";
            if ($ticket) $w[] = 't.number = '.db_input($ticket);
            $parts[] = "SELECT h.created AS created, 'stage' AS category,
                IF(h.from_stage_id, 'derived', 'entered') AS action,
                COALESCE(CONCAT(s.firstname, ' ', s.lastname), 'SYSTEM') AS actor, h.staff_id AS staff_id,
                t.number AS ref, t.ticket_id AS ticket_id,
                IF(h.from_stage_id, CONCAT(h.from_name, ' → ', h.to_name), h.to_name) AS title,
                h.comments AS detail, '' AS ip
              FROM ".WORKFLOW_HISTORY_TABLE." h
              JOIN ".TICKET_TABLE." t ON t.ticket_id = h.ticket_id
              LEFT JOIN ".STAFF_TABLE." s ON s.staff_id = h.staff_id"
              .$where($w);
        }
        if (in_array($cat, array('', 'login', 'config', 'cron')) && !$ticket) {
            $w = $range('l.created');
            if ($staff) $w[] = "l.staff_id = $staff";
            if ($cat) $w[] = 'l.category = '.db_input($cat);
            $parts[] = "SELECT l.created AS created, l.category AS category, l.action AS action,
                l.username AS actor, l.staff_id AS staff_id, l.object_name AS ref, 0 AS ticket_id,
                CONCAT_WS(' #', NULLIF(l.object_type, ''), NULLIF(l.object_id, 0)) AS title,
                l.description AS detail, l.ip AS ip
              FROM ".AUDIT_LOG_TABLE." l"
              .$where($w);
        }
        if ((!$cat || $cat == 'system') && !$ticket && !$staff) {
            $w = $range('g.created');
            $parts[] = "SELECT g.created AS created, 'system' AS category, LOWER(g.log_type) AS action,
                g.logger AS actor, 0 AS staff_id, '' AS ref, 0 AS ticket_id, g.title AS title,
                LEFT(g.log, 400) AS detail, g.ip_address AS ip
              FROM ".SYSLOG_TABLE." g"
              .$where($w);
        }
        if (!$parts)
            return array(array(), 0);

        $sql = 'FROM ('.implode("\n UNION ALL \n", $parts).') x';
        if ($q) {
            $like = db_input('%'.$q.'%');
            $sql .= " WHERE (x.actor LIKE $like OR x.ref LIKE $like OR x.title LIKE $like
                OR x.detail LIKE $like OR x.action LIKE $like)";
        }

        $total = db_result(db_query('SELECT COUNT(*) '.$sql));
        $rows = array();
        $res = db_query('SELECT x.* '.$sql.' ORDER BY x.created DESC LIMIT '
            .(int) $offset.', '.(int) $limit);
        while ($res && ($row = db_fetch_array($res)))
            $rows[] = $row;
        return array($rows, (int) $total);
    }

    // Readable detail for a row (event data is JSON)
    static function describe($row) {
        $detail = (string) $row['detail'];
        if ($row['category'] == 'ticket' && $detail && $detail[0] == '{') {
            $data = JsonDataParser::decode($detail) ?: array();
            $out = array();
            foreach ($data as $k => $v) {
                if (is_array($v))
                    $v = end($v);
                // Events store team ids; show the team's name
                if ($k == 'team' && is_numeric($v) && ($team = Team::lookup($v)))
                    $v = $team->getName();
                if (is_scalar($v) && $v !== '')
                    $out[] = "$k: $v";
            }
            $detail = implode(', ', $out);
        } elseif ($row['category'] == 'comment' || $row['category'] == 'system') {
            $detail = Format::truncate(trim(html_entity_decode(strip_tags($detail),
                ENT_QUOTES, 'UTF-8')), 200);
        }
        if ($row['title'] && $detail == $row['title'])
            $detail = '';
        return trim(($row['title'] ? $row['title'].($detail ? ' — ' : '') : '').$detail);
    }
}
