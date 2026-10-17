<?php
/*********************************************************************
    class.team.php

    Teams

    Peter Rotich <peter@osticket.com>
    Copyright (c)  2006-2013 osTicket
    http://www.osticket.com

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/

class Team extends VerySimpleModel
implements TemplateVariable {

    static $meta = array(
        'table' => TEAM_TABLE,
        'pk' => array('team_id'),
        'joins' => array(
            'lead' => array(
                'null' => true,
                'constraint' => array('lead_id' => 'Staff.staff_id'),
            ),
            'members' => array(
                'list' => true,
                'reverse' => 'TeamMember.team',
            ),
        ),
    );

    const FLAG_ENABLED  = 0x0001;
    const FLAG_NOALERTS = 0x0002;

    // Automatic distribution of team tickets among members
    const ASSIGN_NONE        = '';
    const ASSIGN_LEAST_BUSY  = 'least_busy';
    const ASSIGN_SEQUENTIAL  = 'sequential';
    const ASSIGN_ROUND_ROBIN = 'round_robin';

    var $_members;
    var $_config;

    function asVar() {
        return $this->__toString();
    }

    function __toString() {
        return (string) $this->getName();
    }

    static function getVarScope() {
        return array(
            'name' => __('Team Name'),
            'lead' => array(
                'class' => 'Staff', 'desc' => __('Team Lead'),
            ),
            'members' => array(
                'class' => 'UserList', 'desc' => __('Team Members'),
            ),
        );
    }

    function getVar($tag) {
        switch ($tag) {
        case 'members':
            return new UserList($this->getMembers()->all());
        }
    }

    function getId() {
        return $this->team_id;
    }

    function getName() {
        return $this->name;
    }
    function getLocalName() {
        return $this->getLocal('name');
    }

    function getNumMembers() {
        return $this->members->count();
    }

    function getMembers() {
        if (!isset($this->_members)) {
            $this->_members = array();
            foreach ($this->members as $m)
                $this->_members[] = $m->staff;
        }
        return $this->_members;
    }

    function getMembersForAlerts() {
        $alertmembers = array();
        $members = $this->members->filter(array(
            'flags__hasbit' => TeamMember::FLAG_ALERTS,
        ));
        foreach ($members as $m)
            $alertmembers[] = $m->staff;

        return $alertmembers;
    }

    function hasMember($staff) {
        return $this->members
            ->filter(array('staff_id'=>$staff->getId()))
            ->count() !== 0;
    }

    function getLeadId() {
        return $this->lead_id;
    }

    function getTeamLead() {
        return $this->lead;
    }

    function getLead() {
        return $this->getTeamLead();
    }

    function getHashtable() {
        $base = $this->ht;
        $base['isenabled'] = $this->isEnabled();
        $base['noalerts'] = !$this->alertsEnabled();
        unset($base['members']);
        return $base;
    }

    function getInfo() {
        return  $this->getHashtable();
    }

    function isEnabled() {
        return $this->flags & self::FLAG_ENABLED;
    }

    function isActive() {
        return $this->isEnabled();
    }

    function isAvailable() {
        return ($this->isActive() && $this->members);
    }

    function hasFlag($flag) {
        return ($this->get('flags', 0) & $flag) != 0;
    }

    function flagChanged($flag, $var) {
        if (($this->hasFlag($flag) && $var != $flag) ||
            (!$this->hasFlag($flag) && $var == $flag))
                return true;
    }

    function alertsEnabled() {
        return ($this->flags & self::FLAG_NOALERTS) == 0;
    }

    /* Ticket assignment among members
     *
     * Settings live in the `team.<id>` config namespace:
     *   assign_mode   - one of the ASSIGN_* constants
     *   member_order  - JSON list of staff ids (sequential / round robin)
     *   last_assigned - staff id of the last member picked (round robin)
     *   lead_fallback - assign to the team lead when no member is available
     *   retry_pending - cron hands out team tickets left without an agent
     */
    static function getAssignmentModes() {
        return array(
            self::ASSIGN_NONE        => __('Disabled — assign to the team only'),
            self::ASSIGN_LEAST_BUSY  => __('Least busy — agent with the fewest open tickets'),
            self::ASSIGN_SEQUENTIAL  => __('Sequential — first available agent in member order'),
            self::ASSIGN_ROUND_ROBIN => __('Round robin — rotate through members in order'),
        );
    }

    function getConfig() {
        if (!isset($this->_config) && $this->getId())
            $this->_config = new Config('team.'.$this->getId());
        return $this->_config;
    }

    function getAssignmentMode() {
        $mode = ($c = $this->getConfig()) ? $c->get('assign_mode', '') : '';
        return array_key_exists($mode, self::getAssignmentModes())
            ? $mode : self::ASSIGN_NONE;
    }

    function getMemberOrder() {
        $order = ($c = $this->getConfig())
            ? JsonDataParser::decode($c->get('member_order', '[]')) : array();
        return is_array($order) ? array_map('intval', $order) : array();
    }

    function isLeadFallbackEnabled() {
        return ($c = $this->getConfig()) && $c->get('lead_fallback');
    }

    function isRetryPendingEnabled() {
        return ($c = $this->getConfig()) && $c->get('retry_pending');
    }

    // Active, not on vacation and allowed to own tickets of the department
    static function canReceiveTicket(Staff $staff, $dept=null) {
        if (!$staff->isAvailable())
            return false;
        if ($dept && (!$staff->canAccessDept($dept) || !$dept->canAssign($staff)))
            return false;
        return true;
    }

    // Team lead, if enabled as fallback and able to take the ticket
    function getFallbackAssignee($dept=null) {
        if (!$this->getAssignmentMode()
                || !$this->isLeadFallbackEnabled()
                || !($lead = $this->getTeamLead()))
            return null;
        return self::canReceiveTicket($lead, $dept) ? $lead : null;
    }

    // Members in the configured order; new members go last, by name
    function getOrderedMembers() {
        $pos = array_flip($this->getMemberOrder());
        $members = $this->getMembers();
        usort($members, function($a, $b) use ($pos) {
            $pa = $pos[$a->getId()] ?? PHP_INT_MAX;
            $pb = $pos[$b->getId()] ?? PHP_INT_MAX;
            return $pa <=> $pb
                ?: strcasecmp((string) $a->getName(), (string) $b->getName());
        });
        return $members;
    }

    /**
     * Picks the member who should receive a ticket assigned to this team,
     * according to the assignment mode. Only active agents who are not on
     * vacation and can be assigned in the department are considered.
     * Returns a Staff or NULL if the mode is disabled or nobody qualifies.
     */
    function getNextAssignee($dept=null) {
        if (!($mode = $this->getAssignmentMode()))
            return null;

        $members = $this->getOrderedMembers();
        $eligible = array();
        foreach ($members as $staff) {
            if (self::canReceiveTicket($staff, $dept))
                $eligible[$staff->getId()] = $staff;
        }
        if (!$eligible)
            return null;

        switch ($mode) {
        case self::ASSIGN_SEQUENTIAL:
            return reset($eligible);

        case self::ASSIGN_ROUND_ROBIN:
            // Continue after the last member picked, in member order
            $ids = array();
            foreach ($members as $staff)
                $ids[] = $staff->getId();
            $last = array_search((int) $this->getConfig()->get('last_assigned'), $ids);
            $start = ($last === false) ? 0 : $last + 1;
            for ($i = 0, $n = count($ids); $i < $n; $i++) {
                $id = $ids[($start + $i) % $n];
                if (isset($eligible[$id]))
                    return $eligible[$id];
            }
            return null;

        case self::ASSIGN_LEAST_BUSY:
            // Ties go to the member listed first
            $load = array_fill_keys(array_keys($eligible), 0);
            $counts = Ticket::objects()
                ->filter(array(
                    'staff_id__in' => array_keys($eligible),
                    'status__state' => 'open',
                ))
                ->values('staff_id')
                ->distinct('staff_id')
                ->annotate(array('count' => SqlAggregate::COUNT('ticket_id')));
            foreach ($counts as $row)
                $load[$row['staff_id']] = (int) $row['count'];
            asort($load, SORT_NUMERIC);
            $min = reset($load);
            foreach ($eligible as $id => $staff)
                if ($load[$id] == $min)
                    return $staff;
        }
        return null;
    }

    /**
     * Cron: gives open tickets of the team that have no agent to members
     * who are now available (e.g. back from vacation), oldest first.
     */
    static function assignPendingTickets($limit=50) {
        $teams = static::objects()->filter(array(
            'flags__hasbit' => self::FLAG_ENABLED,
        ));
        foreach ($teams as $team) {
            if (!$team->getAssignmentMode() || !$team->isRetryPendingEnabled())
                continue;
            $tickets = Ticket::objects()
                ->filter(array(
                    'team_id' => $team->getId(),
                    'staff_id' => 0,
                    'status__state' => 'open',
                ))
                ->order_by('created')
                ->limit($limit);
            foreach ($tickets as $ticket) {
                if ($agent = $ticket->autoAssignTeamMember($team, null, false))
                    $ticket->onAssign($agent, '', true);
            }
        }
    }

    // Remembers who got the last ticket (used by round robin)
    function setLastAssigned(Staff $staff) {
        $config = new Config('team.'.$this->getId());
        $config->set('last_assigned', $staff->getId());
        $this->_config = null;
    }

    function getTranslateTag($subtag) {
        return _H(sprintf('team.%s.%s', $subtag, $this->getId()));
    }
    function getLocal($subtag) {
        $tag = $this->getTranslateTag($subtag);
        $T = CustomDataTranslation::translate($tag);
        return $T != $tag ? $T : $this->ht[$subtag];
    }
    static function getLocalById($id, $subtag, $default) {
        $tag = _H(sprintf('team.%s.%s', $subtag, $id));
        $T = CustomDataTranslation::translate($tag);
        return $T != $tag ? $T : $default;
    }

    function update($vars, &$errors=array()) {
        if (!$vars['name']) {
            $errors['name']=__('Team name is required');
        } elseif(($tid=self::getIdByName($vars['name'])) && $tid!=$vars['id']) {
            $errors['name']=__('Team name already exists');
        }

        $vars['noalerts'] = isset($vars['noalerts']) ? self::FLAG_NOALERTS : 0;
        if ($this->getId()) {
            //flags
            $auditEnabled = $this->flagChanged(self::FLAG_ENABLED, $vars['isenabled']);
            $auditAlerts = $this->flagChanged(self::FLAG_NOALERTS, $vars['noalerts']);

            foreach ($vars as $key => $value) {
                if (isset($this->$key) && ($this->$key != $value) && $key != 'members' ||
                   ($auditEnabled && $key == 'isenabled' || $auditAlerts && $key == 'noalerts')) {
                    $type = array('type' => 'edited', 'key' => $key);
                    Signal::send('object.edited', $this, $type);
                }
            }
        }

        // Reset team lead if they're getting removed
        if (isset($this->lead_id)
                && $this->lead_id == $vars['lead_id']
                && $vars['remove']
                && in_array($this->lead_id, $vars['remove']))
            $vars['lead_id'] =0 ;

        $this->flags =
              ($vars['isenabled'] ? self::FLAG_ENABLED : 0)
            | ($vars['noalerts']);
        $this->lead_id = $vars['lead_id'] ?: 0;
        $this->name = Format::striptags($vars['name']);
        $this->notes = Format::sanitize($vars['notes']);

        $vars['assign_mode'] = $vars['assign_mode'] ?? self::ASSIGN_NONE;
        if (!array_key_exists($vars['assign_mode'], self::getAssignmentModes()))
            $errors['assign_mode'] = __('Select a valid assignment mode');
        $vars['lead_fallback'] = isset($vars['lead_fallback']) ? 1 : 0;
        $vars['retry_pending'] = isset($vars['retry_pending']) ? 1 : 0;
        if ($vars['assign_mode'] && $vars['lead_fallback'] && !$vars['lead_id'])
            $errors['lead_fallback'] = __('Select a team lead to use as fallback');

        // Format access update as [array(staff_id, alerts?)]
        $access = array();
        if (isset($vars['members'])) {
            foreach (@$vars['members'] as $staff_id) {
                $access[] = array($staff_id, @$vars['member_alerts'][$staff_id]);
            }
        }

        if ($errors)
            return false;

        if ($this->save()) {
            $this->updateMembers($access, $errors);
            // Members are posted in the order set on the Members tab
            $order = array();
            foreach ((array) @$vars['members'] as $staff_id)
                $order[] = (int) $staff_id;
            $config = new Config('team.'.$this->getId());
            $config->updateAll(array(
                'assign_mode' => $vars['assign_mode'],
                'member_order' => JsonDataEncoder::encode($order),
                'lead_fallback' => $vars['lead_fallback'],
                'retry_pending' => $vars['retry_pending'],
            ));
            $this->_config = null;
            return true;
        }

        if (isset($this->team_id)) {
            $errors['err']=sprintf(__('Unable to update %s.'), __('this team'))
               .' '.__('Internal error occurred');
        } else {
            $errors['err']=sprintf(__('Unable to create %s.'), __('this team'))
               .' '.__('Internal error occurred');
        }

        return false;
    }

    function updateMembers($access, &$errors) {
      reset($access);
      $dropped = array();
      foreach ($this->members as $member)
          $dropped[$member->staff_id] = 1;
      foreach ($access as $acc) {
          list($staff_id, $alerts) = $acc;
          unset($dropped[$staff_id]);
          if (!$staff_id || !Staff::lookup($staff_id))
              $errors['members'][$staff_id] = __('No such agent');
          $member = $this->members->findFirst(array('staff_id' => $staff_id));
          if (!isset($member)) {
              $member = new TeamMember(array('staff_id' => $staff_id));
              $this->members->add($member);
              $type = array('type' => 'edited', 'key' => 'Members Added');
              Signal::send('object.edited', $this, $type);
          }
          $member->setAlerts($alerts);
      }

      if ($errors)
          return false;

      $this->members->saveAll();
      if ($dropped) {
          $type = array('type' => 'edited', 'key' => 'Members Removed');
          Signal::send('object.edited', $this, $type);
          $this->members
              ->filter(array('staff_id__in' => array_keys($dropped)))
              ->delete();
          $this->members->reset();
      }

      return true;
    }

    function save($refetch=false) {
        if ($this->dirty)
            $this->updated = SqlFunction::NOW();

        return parent::save($refetch || $this->dirty);
    }

    function delete() {
        global $thisstaff;

        if (!$thisstaff || !($id=$this->getId()))
            return false;

        # Remove the team
        if (!parent::delete())
            return false;

        $type = array('type' => 'deleted');
        Signal::send('object.deleted', $this, $type);

        # Remove members of this team
        $this->members->delete();

        # Remove assignment settings
        $config = new Config('team.'.$id);
        $config->destroy();

        # Reset ticket ownership for tickets owned by this team
        Ticket::objects()
            ->filter(array('team_id' => $id))
            ->update(array('team_id' => 0));

        return true;
    }

    /* ----------- Static function ------------------*/
    static function getIdByName($name) {

        $row = self::objects()
            ->filter(array('name'=>trim($name)))
            ->values_flat('team_id')
            ->first();

        return $row ? $row[0] : 0;
    }

    static function getTeams($criteria=array()) {
        static $teams = null;
        if (!$teams || $criteria) {
            $teams = array();
            $query = static::objects()
                ->values_flat('team_id', 'name', 'flags')
                ->order_by('name');

            if (isset($criteria['active']) && $criteria['active']) {
                $query->annotate(array('members_count'=>SqlAggregate::COUNT('members')))
                ->filter(array(
                    'flags__hasbit'=>self::FLAG_ENABLED,
                    'members__staff__isactive'=>1,
                    'members__staff__onvacation'=>0,
                ))
                ->filter(array('members_count__gt'=>0));
            }

            $items = array();
            foreach ($query as $row) {
                //TODO: Fix enabled - flags is a bit field.
                list($id, $name, $flags) = $row;
                $enabled = $flags & self::FLAG_ENABLED;
                $items[$id] = sprintf('%s%s',
                    self::getLocalById($id, 'name', $name),
                    ($enabled || isset($criteria['active']))
                        ? '' : ' ' . __('(disabled)'));
            }

            //TODO: sort if $criteria['localize'];
            if ($criteria)
                return $items;

            $teams = $items;
        }

        return $teams;
    }

    static function getActiveTeams() {
        static $teams = null;

        if (!isset($teams))
            $teams = self::getTeams(array('active'=>true));

        return $teams;
    }

    static function create($vars=false) {
        $team = new static($vars);
        $team->created = SqlFunction::NOW();
        return $team;
    }

    static function __create($vars, &$errors) {
        return self::create($vars)->save();
    }
}

class TeamMember extends VerySimpleModel {
    static $meta = array(
        'table' => TEAM_MEMBER_TABLE,
        'pk' => array('team_id', 'staff_id'),
        'select_related' => array('staff'),
        'joins' => array(
            'team' => array(
                'constraint' => array('team_id' => 'Team.team_id'),
            ),
            'staff' => array(
                'constraint' => array('staff_id' => 'Staff.staff_id'),
            ),
        ),
    );

    const FLAG_ALERTS = 0x0001;

    function isAlertsEnabled() {
        return $this->flags & self::FLAG_ALERTS != 0;
    }

    function setFlag($flag, $value) {
        if ($value)
            $this->flags |= $flag;
        else
            $this->flags &= ~$flag;
    }

    function setAlerts($value) {
        $this->setFlag(self::FLAG_ALERTS, $value);
    }
}

class TeamQuickAddForm
extends AbstractForm {
    function buildFields() {
        return array(
            'name' => new TextboxField(array(
                'required' => true,
                'configuration' => array(
                    'placeholder' => __('Name'),
                    'classes' => 'span12',
                    'autofocus' => true,
                    'length' => 128,
                ),
            )),
            'lead_id' => new ChoiceField(array(
                'label' => __('Optionally select a leader for the team'),
                'default' => 0,
                'choices' =>
                    array(0 => '— '.__('None').' —')
                    + Staff::getStaffMembers(),
                'configuration' => array(
                    'classes' => 'span12',
                ),
            )),
        );
    }

    function render($staff=true, $title=false, $options=array()) {
        return parent::render($staff, $title, $options + array('template' => 'dynamic-form-simple.tmpl.php'));
    }
}
