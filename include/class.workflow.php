<?php
/*********************************************************************
    class.workflow.php

    Ticket workflows: a workflow is chosen by the ticket's help topic and
    moves the ticket through stages. Each stage is owned by a department
    and a team; from each stage a ticket can only be derived to the stages
    allowed by the workflow. Reaching a final stage closes the ticket.

    Tables live in their own upgrade stream ("workflow"), so they don't
    interfere with osTicket's core schema upgrades.
**********************************************************************/

if (!defined('WORKFLOW_TABLE')) {
    define('WORKFLOW_TABLE', TABLE_PREFIX.'workflow');
    define('WORKFLOW_TOPIC_TABLE', TABLE_PREFIX.'workflow_topic');
    define('WORKFLOW_STAGE_TABLE', TABLE_PREFIX.'workflow_stage');
    define('WORKFLOW_TRANSITION_TABLE', TABLE_PREFIX.'workflow_transition');
    define('TICKET_WORKFLOW_TABLE', TABLE_PREFIX.'ticket_workflow');
    define('WORKFLOW_HISTORY_TABLE', TABLE_PREFIX.'workflow_history');
}

class Workflow extends VerySimpleModel {
    static $meta = array(
        'table' => WORKFLOW_TABLE,
        'pk' => array('id'),
        'ordering' => array('name'),
    );

    const FLAG_ACTIVE = 0x0001;
    // Fixed: tickets only move through derivations (no free transfers)
    const FLAG_STRICT = 0x0002;

    var $_stages;

    function getId() {
        return $this->id;
    }

    function getName() {
        return $this->name;
    }

    function isActive() {
        return ($this->flags & self::FLAG_ACTIVE) != 0;
    }

    function isStrict() {
        return ($this->flags & self::FLAG_STRICT) != 0;
    }

    function getStages() {
        if (!isset($this->_stages)) {
            $this->_stages = array();
            $stages = WorkflowStage::objects()
                ->filter(array('workflow_id' => $this->getId()))
                ->order_by('sort');
            foreach ($stages as $s)
                $this->_stages[$s->getId()] = $s;
        }
        return $this->_stages;
    }

    function getInitialStage() {
        foreach ($this->getStages() as $s)
            if ($s->isInitial())
                return $s;
        return null;
    }

    function getTopicIds() {
        $ids = array();
        $rows = WorkflowTopic::objects()
            ->filter(array('workflow_id' => $this->getId()))
            ->values_flat('topic_id');
        foreach ($rows as $row)
            $ids[] = (int) $row[0];
        return $ids;
    }

    function getNumTickets() {
        return TicketWorkflow::objects()
            ->filter(array('workflow_id' => $this->getId()))
            ->count();
    }

    static function lookupByTopic($topic_id) {
        if (!$topic_id
                || !($link = WorkflowTopic::lookup(array('topic_id' => $topic_id)))
                || !($workflow = static::lookup($link->workflow_id)))
            return null;
        return $workflow;
    }

    // Workflow for a new ticket of this help topic, if active and usable
    static function getForNewTicket($topic_id) {
        $workflow = static::lookupByTopic($topic_id);
        if (!$workflow || !$workflow->isActive() || !$workflow->getInitialStage())
            return null;
        return $workflow;
    }

    /**
     * Saves the workflow, its stages, allowed derivations and help topics
     * from the admin form.
     *
     * $vars['stages'][<key>] = {name, dept_id, team_id, final, sort}
     *   <key> = existing stage id, or "new<n>" for a new stage
     * $vars['initial'] = <key> of the initial stage
     * $vars['next'][<from stage id>][] = <to stage id>
     * $vars['topics'][] = help topic ids
     */
    function update($vars, &$errors) {
        $name = Format::striptags(trim($vars['name'] ?? ''));
        if (!$name)
            $errors['name'] = __('Name is required');
        elseif (($w = static::lookup(array('name' => $name)))
                && $w->getId() != $this->getId())
            $errors['name'] = __('A workflow with this name already exists');

        // Stages
        $stages = array();
        $keys = array();
        $sort = 0;
        foreach ((array) @$vars['stages'] as $key => $s) {
            $sname = Format::striptags(trim($s['name'] ?? ''));
            $isNew = !strncmp($key, 'new', 3);
            // Blank new rows are ignored
            if ($isNew && !$sname && !$s['dept_id'] && !$s['team_id'])
                continue;
            if (!$isNew && !isset($this->getStages()[$key]))
                continue;
            if (!$sname)
                $errors['stages'][$key] = __('Stage name is required');
            elseif (!$s['dept_id'] || !Dept::lookup($s['dept_id']))
                $errors['stages'][$key] = __('Select a department');
            elseif (!$s['team_id'] || !($team = Team::lookup($s['team_id'])))
                $errors['stages'][$key] = __('Select a team');
            elseif (in_array(mb_strtolower($sname), $keys))
                $errors['stages'][$key] = __('Stage names must be unique');
            elseif (!empty($s['sla_id']) && !SLA::lookup($s['sla_id']))
                $errors['stages'][$key] = __('Select a valid SLA plan');
            $keys[] = mb_strtolower($sname);
            $stages[$key] = array(
                'name' => $sname,
                'dept_id' => (int) $s['dept_id'],
                'team_id' => (int) $s['team_id'],
                'final' => !empty($s['final']),
                'sort' => ++$sort,
                'sla_id' => (int) ($s['sla_id'] ?? 0),
                // Position in the diagram editor (-1 = automatic)
                'pos_x' => isset($s['pos_x']) && is_numeric($s['pos_x']) ? (int) $s['pos_x'] : -1,
                'pos_y' => isset($s['pos_y']) && is_numeric($s['pos_y']) ? (int) $s['pos_y'] : -1,
            );
        }
        if (!$stages)
            $errors['stages_err'] = __('Add at least one stage');
        elseif (!isset($vars['initial']) || !isset($stages[$vars['initial']]))
            $errors['stages_err'] = __('Select the initial stage');
        elseif ($stages[$vars['initial']]['final'])
            $errors['stages_err'] = __('The initial stage cannot be a final stage');

        // Stages removed from the form must not hold tickets
        foreach ($this->getStages() as $id => $stage) {
            if (!isset($stages[$id]) && $stage->getNumTickets())
                $errors['stages_err'] = sprintf(
                    __('Stage "%s" has tickets and cannot be removed'),
                    $stage->getName());
        }

        // Help topics can belong to one workflow only
        $topics = array_filter(array_map('intval', (array) @$vars['topics']));
        foreach ($topics as $tid) {
            if (($w = static::lookupByTopic($tid)) && $w->getId() != $this->getId())
                $errors['topics'] = sprintf(
                    __('Help topic "%s" already uses workflow "%s"'),
                    Topic::getTopicName($tid), $w->getName());
        }

        if ($errors)
            return false;

        $this->name = $name;
        $this->flags = (!empty($vars['isactive']) ? self::FLAG_ACTIVE : 0)
            | (($vars['mode'] ?? '') == 'strict' ? self::FLAG_STRICT : 0);
        $this->notes = Format::sanitize($vars['notes'] ?? '');
        $this->updated = SqlFunction::NOW();
        if (!isset($this->id))
            $this->created = SqlFunction::NOW();
        if (!$this->save())
            return false;

        // Save stages; map form keys to stage ids
        $ids = array();
        foreach ($stages as $key => $info) {
            $stage = $this->getStages()[$key] ?? new WorkflowStage(array(
                'workflow_id' => $this->getId(),
                'created' => SqlFunction::NOW(),
            ));
            $stage->name = $info['name'];
            $stage->dept_id = $info['dept_id'];
            $stage->team_id = $info['team_id'];
            $stage->sort = $info['sort'];
            $stage->sla_id = $info['sla_id'];
            $stage->pos_x = $info['pos_x'];
            $stage->pos_y = $info['pos_y'];
            $stage->flags = ($key == $vars['initial'] ? WorkflowStage::FLAG_INITIAL : 0)
                | ($info['final'] ? WorkflowStage::FLAG_FINAL : 0);
            $stage->updated = SqlFunction::NOW();
            $stage->save();
            $ids[$key] = $stage->getId();
        }

        // Drop removed stages and their derivations
        foreach ($this->getStages() as $id => $stage) {
            if (!in_array($id, $ids)) {
                WorkflowTransition::objects()->filter(array('from_id' => $id))->delete();
                WorkflowTransition::objects()->filter(array('to_id' => $id))->delete();
                $stage->delete();
            }
        }

        // Allowed derivations (only between saved stages; none out of final)
        WorkflowTransition::objects()->filter(array('from_id__in' => array_values($ids) ?: array(0)))->delete();
        foreach ((array) @$vars['next'] as $from => $tos) {
            if (!in_array($from, $ids) || $stages[array_search($from, $ids)]['final'])
                continue;
            foreach ((array) $tos as $to) {
                if ($to != $from && in_array($to, $ids)) {
                    $t = new WorkflowTransition(array('from_id' => $from, 'to_id' => $to));
                    $t->save();
                }
            }
        }

        // Help topics
        WorkflowTopic::objects()->filter(array('workflow_id' => $this->getId()))->delete();
        foreach ($topics as $tid) {
            $link = new WorkflowTopic(array('topic_id' => $tid, 'workflow_id' => $this->getId()));
            $link->save();
        }

        $this->_stages = null;
        return true;
    }

    function delete() {
        if ($this->getNumTickets())
            return false;
        foreach ($this->getStages() as $stage) {
            WorkflowTransition::objects()->filter(array('from_id' => $stage->getId()))->delete();
            $stage->delete();
        }
        WorkflowTopic::objects()->filter(array('workflow_id' => $this->getId()))->delete();
        return parent::delete();
    }

    static function create($vars=array()) {
        $workflow = new static($vars);
        $workflow->flags = self::FLAG_ACTIVE;
        return $workflow;
    }
}

class WorkflowTopic extends VerySimpleModel {
    static $meta = array(
        'table' => WORKFLOW_TOPIC_TABLE,
        'pk' => array('topic_id'),
    );
}

class WorkflowStage extends VerySimpleModel {
    static $meta = array(
        'table' => WORKFLOW_STAGE_TABLE,
        'pk' => array('id'),
        'ordering' => array('sort'),
    );

    const FLAG_INITIAL = 0x0001;
    // Deriving a ticket to a final stage closes it
    const FLAG_FINAL   = 0x0002;

    function getId() {
        return $this->id;
    }

    function getName() {
        return $this->name;
    }

    function getWorkflow() {
        return Workflow::lookup($this->workflow_id);
    }

    function getDept() {
        return Dept::lookup($this->dept_id);
    }

    function getTeam() {
        return Team::lookup($this->team_id);
    }

    // SLA of the stage, measured from when the ticket enters it
    function getSLA() {
        return $this->sla_id ? SLA::lookup($this->sla_id) : null;
    }

    function isInitial() {
        return ($this->flags & self::FLAG_INITIAL) != 0;
    }

    function isFinal() {
        return ($this->flags & self::FLAG_FINAL) != 0;
    }

    function getNextStageIds() {
        return WorkflowTransition::objects()
            ->filter(array('from_id' => $this->getId()))
            ->values_flat('to_id');
    }

    // Stages a ticket can be derived to from this one, in workflow order
    function getNextStages() {
        $next = array();
        $ids = array();
        foreach ($this->getNextStageIds() as $row)
            $ids[] = (int) $row[0];
        if ($ids && ($workflow = $this->getWorkflow()))
            foreach ($workflow->getStages() as $id => $stage)
                if (in_array($id, $ids))
                    $next[$id] = $stage;
        return $next;
    }

    function canDeriveTo($stage) {
        return $stage instanceof WorkflowStage
            && array_key_exists($stage->getId(), $this->getNextStages());
    }

    function getNumTickets() {
        return TicketWorkflow::objects()
            ->filter(array('stage_id' => $this->getId()))
            ->count();
    }
}

class WorkflowTransition extends VerySimpleModel {
    static $meta = array(
        'table' => WORKFLOW_TRANSITION_TABLE,
        'pk' => array('from_id', 'to_id'),
    );
}

class TicketWorkflow extends VerySimpleModel {
    static $meta = array(
        'table' => TICKET_WORKFLOW_TABLE,
        'pk' => array('ticket_id'),
        'joins' => array(
            'ticket' => array(
                'constraint' => array('ticket_id' => 'Ticket.ticket_id'),
            ),
            'stage' => array(
                'constraint' => array('stage_id' => 'WorkflowStage.id'),
                'null' => true,
            ),
        ),
    );

    function getWorkflow() {
        return Workflow::lookup($this->workflow_id);
    }

    function getStage() {
        return WorkflowStage::lookup($this->stage_id);
    }
}

class WorkflowHistory extends VerySimpleModel {
    static $meta = array(
        'table' => WORKFLOW_HISTORY_TABLE,
        'pk' => array('id'),
        'ordering' => array('id'),
    );

    static function log($ticket_id, $from, $to, $staff=null, $comments='') {
        $entry = new static(array(
            'ticket_id' => $ticket_id,
            'from_stage_id' => $from ? $from->getId() : 0,
            'from_name' => $from ? $from->getName() : '',
            'to_stage_id' => $to->getId(),
            'to_name' => $to->getName(),
            'staff_id' => $staff ? $staff->getId() : 0,
            'comments' => $comments,
            'created' => SqlFunction::NOW(),
        ));
        return $entry->save();
    }

    static function forTicket($ticket_id) {
        return static::objects()->filter(array('ticket_id' => $ticket_id));
    }
}

/**
 * Figures for the public dashboard of the client portal (dashboard.php):
 * tickets by help topic and current stage, and SLA breaches by stage.
 * Only aggregated counts; no ticket or user data.
 */
class WorkflowDashboard {

    static function getData() {
        $data = array();
        foreach (Workflow::objects()->order_by('name') as $w) {
            if (!$w->isActive() || !($stages = $w->getStages()))
                continue;
            $data[$w->getId()] = array(
                'workflow' => $w,
                'stages' => $stages,
                'topics' => array(),    // topic_id => name
                'counts' => array(),    // topic_id => stage_id => n
                'sla' => array(),       // stage_id => [open, overdue]
            );
        }
        if (!$data)
            return $data;
        $ids = implode(',', array_map('intval', array_keys($data)));

        // Tickets by help topic and current stage
        $res = db_query('SELECT tw.workflow_id, t.topic_id, tw.stage_id, COUNT(*)
            FROM '.TICKET_WORKFLOW_TABLE.' tw
            JOIN '.TICKET_TABLE.' t ON t.ticket_id = tw.ticket_id
            WHERE tw.workflow_id IN ('.$ids.')
            GROUP BY tw.workflow_id, t.topic_id, tw.stage_id');
        while ($res && (list($wid, $tid, $sid, $n) = db_fetch_row($res))) {
            $data[$wid]['counts'][(int) $tid][(int) $sid] = (int) $n;
            $data[$wid]['topics'][(int) $tid] = $tid
                ? (Topic::getTopicName($tid) ?: __('Unknown')) : __('No help topic');
        }

        // Open tickets and SLA breaches (due date passed) by stage
        $res = db_query('SELECT tw.workflow_id, tw.stage_id, COUNT(*),
                SUM(t.isoverdue = 1 OR COALESCE(t.duedate, t.est_duedate) < NOW())
            FROM '.TICKET_WORKFLOW_TABLE.' tw
            JOIN '.TICKET_TABLE.' t ON t.ticket_id = tw.ticket_id
            JOIN '.TICKET_STATUS_TABLE.' st ON st.id = t.status_id
            WHERE tw.workflow_id IN ('.$ids.') AND st.state = \'open\'
            GROUP BY tw.workflow_id, tw.stage_id');
        while ($res && (list($wid, $sid, $open, $late) = db_fetch_row($res)))
            $data[$wid]['sla'][(int) $sid] = array((int) $open, (int) $late);

        foreach ($data as &$d)
            asort($d['topics'], SORT_NATURAL | SORT_FLAG_CASE);
        return $data;
    }
}
