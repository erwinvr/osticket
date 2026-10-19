<?php
/*********************************************************************
    class.automation.php

    Automations on top of the ticket filters (Admin Panel > Manage >
    Filters), evaluated when a ticket is created:

      - the help topic an automation applies to: other topics never match
      - conditions on form fields by field type: lists (is one of), numbers
        (greater / less than, between), dates (before, after, between),
        checkboxes (checked or not) and text

    They combine with the filter's own text rules through the filter's
    "match all / any" setting, and the filter's actions run as usual.
    Tables live in their own upgrade stream ("automation").
**********************************************************************/

if (!defined('FILTER_TOPIC_TABLE')) {
    define('FILTER_TOPIC_TABLE', TABLE_PREFIX.'filter_topic');
    define('FILTER_CONDITION_TABLE', TABLE_PREFIX.'filter_condition');
}

class FilterTopic extends VerySimpleModel {
    static $meta = array(
        'table' => FILTER_TOPIC_TABLE,
        'pk' => array('filter_id'),
    );
}

class FilterCondition extends VerySimpleModel {
    static $meta = array(
        'table' => FILTER_CONDITION_TABLE,
        'pk' => array('id'),
        'ordering' => array('sort'),
    );

    function getValue() {
        return JsonDataParser::decode($this->value);
    }
}

class FilterAutomation {

    // Operators by field type
    static function getOperators() {
        return array(
            'text' => array(
                'equal' => __('Equal'),
                'not_equal' => __('Not Equal'),
                'contains' => __('Contains'),
                'dn_contain' => __('Does Not Contain'),
                'starts' => __('Starts With'),
                'ends' => __('Ends With'),
            ),
            'choice' => array(
                'in' => __('Is one of'),
                'not_in' => __('Is not any of'),
            ),
            'number' => array(
                'equal' => __('Equal'),
                'gt' => __('Greater than'),
                'lt' => __('Less than'),
                'between' => __('Between'),
            ),
            'date' => array(
                'on' => __('On'),
                'before' => __('Before'),
                'after' => __('After'),
                'between' => __('Between'),
            ),
            'bool' => array(
                'checked' => __('Is checked'),
                'unchecked' => __('Is not checked'),
            ),
        );
    }

    // Kind of condition for a form field, or null if it can't be used
    static function fieldType($field) {
        $type = $field->get('type');
        if ($type == 'text') {
            $config = $field->getConfiguration();
            return (($config['validator'] ?? '') == 'number') ? 'number' : 'text';
        }
        if (in_array($type, array('memo', 'phone')))
            return 'text';
        if ($type == 'datetime')
            return 'date';
        if ($type == 'bool')
            return 'bool';
        if (in_array($type, array('choices', 'priority')) || strpos($type, 'list-') === 0)
            return 'choice';
        return null;
    }

    /**
     * Fields that can be used in conditions, by form:
     *   [form_id => [title, general?, fields => [field_id => [label, type, choices]]]]
     * plus the forms of each help topic: [topic_id => [form_id, ...]]
     */
    static function getCatalog() {
        $forms = array();
        $list = DynamicForm::objects()->filter(array('type__in' => array('T', 'G')));
        foreach ($list as $form) {
            $fields = array();
            foreach ($form->getFields() as $f) {
                if (!($type = static::fieldType($f)) || !$f->get('id'))
                    continue;
                $choices = array();
                if ($type == 'choice') {
                    try {
                        foreach ((array) $f->getImpl()->getChoices() as $k => $v)
                            $choices[(string) $k] = (string) $v;
                    } catch (Throwable $e) {}
                }
                $fields[(int) $f->get('id')] = array(
                    'label' => (string) $f->getLocal('label'),
                    'type' => $type,
                    'choices' => $choices,
                );
            }
            if ($fields)
                $forms[(int) $form->getId()] = array(
                    'title' => (string) $form->getTitle(),
                    // The ticket form is shared by every help topic
                    'general' => $form->get('type') == 'T',
                    'fields' => $fields,
                );
        }
        $topics = array();
        $res = db_query('SELECT topic_id, form_id FROM '.TOPIC_FORM_TABLE);
        while ($res && (list($tid, $fid) = db_fetch_row($res)))
            $topics[(int) $tid][] = (int) $fid;
        return array('forms' => $forms, 'topics' => $topics);
    }

    static function findField($catalog, $field_id) {
        foreach ($catalog['forms'] as $form)
            if (isset($form['fields'][$field_id]))
                return $form['fields'][$field_id];
        return null;
    }

    /* ---------- Load / save ---------- */

    static function getTopicId($filter_id) {
        return ($t = FilterTopic::lookup($filter_id)) ? (int) $t->topic_id : 0;
    }

    static function getConditions($filter_id) {
        return FilterCondition::objects()->filter(array('filter_id' => $filter_id));
    }

    // Posted conditions as [field, op, value]
    static function posted($vars) {
        $out = array();
        foreach ((array) @$vars['conds'] as $c) {
            if (!is_array($c) || empty($c['f']))
                continue;
            $out[] = array(
                'f' => (int) $c['f'],
                'op' => (string) ($c['op'] ?? ''),
                'v' => $c['v'] ?? null,
                'v2' => $c['v2'] ?? null,
            );
        }
        return $out;
    }

    // Whether the form defines anything beyond the text rules
    static function hasCriteria($vars) {
        return !empty($vars['auto_topic_id']) || static::posted($vars);
    }

    static function isDate($d) {
        $dt = DateTime::createFromFormat('!Y-m-d', (string) $d);
        return $dt && $dt->format('Y-m-d') === $d;
    }

    /**
     * Validates the posted topic and conditions. Returns the rows to save
     * ([field_id, op, value]) or false.
     */
    static function validate($vars, &$errors) {
        $catalog = static::getCatalog();
        $ops = static::getOperators();
        $topic_id = (int) ($vars['auto_topic_id'] ?? 0);
        if ($topic_id && !Topic::lookup($topic_id))
            $errors['auto_topic_id'] = __('Select a valid help topic');

        $rows = array();
        foreach (static::posted($vars) as $i => $c) {
            $field = static::findField($catalog, $c['f']);
            $err = null;
            if (!$field)
                $err = __('Select a form field');
            elseif (!isset($ops[$field['type']][$c['op']]))
                $err = __('Select a condition');
            else {
                $type = $field['type'];
                $v = $c['v'];
                $value = null;
                switch ($type) {
                case 'text':
                    if (trim((string) $v) === '')
                        $err = __('Value required');
                    $value = trim((string) $v);
                    break;
                case 'choice':
                    $value = array_values(array_intersect(array_map('strval', (array) $v),
                        array_map('strval', array_keys($field['choices']))));
                    if (!$value)
                        $err = __('Select at least one option');
                    break;
                case 'number':
                    if (!is_numeric($v) || ($c['op'] == 'between' && !is_numeric($c['v2'])))
                        $err = __('Enter a number');
                    elseif ($c['op'] == 'between' && $c['v2'] < $v)
                        $err = __('The second number must be greater than the first');
                    $value = $c['op'] == 'between' ? array((float) $v, (float) $c['v2']) : (float) $v;
                    break;
                case 'date':
                    if (!static::isDate($v) || ($c['op'] == 'between' && !static::isDate($c['v2'])))
                        $err = __('Enter a valid date');
                    elseif ($c['op'] == 'between' && $c['v2'] < $v)
                        $err = __('The second date must be on or after the first');
                    $value = $c['op'] == 'between' ? array($v, $c['v2']) : $v;
                    break;
                case 'bool':
                    $value = null;
                    break;
                }
            }
            if ($err)
                $errors["cond_$i"] = $err;
            else
                $rows[] = array($c['f'], $c['op'], $value);
        }
        return isset($errors['auto_topic_id']) || preg_grep('/^cond_/', array_keys($errors))
            ? false : $rows;
    }

    static function save($filter, $vars, $rows) {
        $id = $filter->getId();
        FilterTopic::objects()->filter(array('filter_id' => $id))->delete();
        if ($topic_id = (int) ($vars['auto_topic_id'] ?? 0)) {
            $t = new FilterTopic(array('filter_id' => $id, 'topic_id' => $topic_id));
            $t->save();
        }
        FilterCondition::objects()->filter(array('filter_id' => $id))->delete();
        foreach ($rows as $n => $r) {
            list($field_id, $op, $value) = $r;
            $c = new FilterCondition(array(
                'filter_id' => $id,
                'field_id' => $field_id,
                'op' => $op,
                'value' => JsonDataEncoder::encode($value),
                'sort' => $n + 1,
            ));
            $c->save();
        }
    }

    static function delete($filter_id) {
        FilterTopic::objects()->filter(array('filter_id' => $filter_id))->delete();
        FilterCondition::objects()->filter(array('filter_id' => $filter_id))->delete();
    }

    /* ---------- Evaluation ---------- */

    /**
     * Topic check and condition results for a filter against the data of
     * a new ticket ($vars: topicId and __raw => [field_id => value]).
     * Returns [topic => true|false|null (no topic), results => [bool, ...]]
     */
    static function evaluate($filter_id, $vars) {
        $out = array('topic' => null, 'results' => array());
        if ($topic_id = static::getTopicId($filter_id))
            $out['topic'] = ((int) ($vars['topicId'] ?? 0)) === $topic_id;
        if ($out['topic'] === false)
            return $out;

        $catalog = null;
        foreach (static::getConditions($filter_id) as $c) {
            $catalog = $catalog ?: static::getCatalog();
            $field = static::findField($catalog, (int) $c->field_id);
            $raw = $vars['__raw'][(int) $c->field_id] ?? null;
            $out['results'][] = $field
                ? static::test($field['type'], $c->op, $c->getValue(), $raw)
                : false;
        }
        return $out;
    }

    static function test($type, $op, $want, $raw) {
        switch ($type) {
        case 'choice':
            $have = static::choiceKeys($raw);
            $hit = (bool) array_intersect($have, array_map('strval', (array) $want));
            return $op == 'not_in' ? !$hit : $hit;

        case 'bool':
            return $op == 'unchecked' ? !$raw : (bool) $raw;

        case 'number':
            if (!is_numeric($raw))
                return false;
            $n = (float) $raw;
            switch ($op) {
            case 'gt': return $n > (float) $want;
            case 'lt': return $n < (float) $want;
            case 'between': return $n >= (float) $want[0] && $n <= (float) $want[1];
            default: return $n == (float) $want;
            }

        case 'date':
            if (!($d = static::dateOf($raw)))
                return false;
            switch ($op) {
            case 'before': return $d < $want;
            case 'after': return $d > $want;
            case 'between': return $d >= $want[0] && $d <= $want[1];
            default: return $d === $want;
            }

        default:
            $text = static::textOf($raw);
            $want = (string) $want;
            switch ($op) {
            case 'not_equal': return strcasecmp($text, $want) !== 0;
            case 'contains': return $want !== '' && stripos($text, $want) !== false;
            case 'dn_contain': return $want === '' || stripos($text, $want) === false;
            case 'starts': return $want !== '' && stripos($text, $want) === 0;
            case 'ends': return $want !== '' && strcasecmp(substr($text, -strlen($want)), $want) === 0;
            default: return strcasecmp($text, $want) === 0;
            }
        }
    }

    // Selected option keys of a list / choices / priority value
    static function choiceKeys($raw) {
        if ($raw === null || $raw === '' || $raw === false)
            return array();
        if (is_object($raw))
            return method_exists($raw, 'getId') ? array((string) $raw->getId()) : array();
        if (is_array($raw))
            return array_map('strval', array_keys($raw));
        return array((string) $raw);
    }

    static function textOf($raw) {
        if (is_array($raw))
            return implode(', ', array_map('strval', $raw));
        if (is_object($raw))
            return method_exists($raw, '__toString') ? (string) $raw : '';
        return trim((string) $raw);
    }

    // Y-m-d in the helpdesk timezone
    static function dateOf($raw) {
        global $cfg;
        if (!$raw)
            return null;
        $tz = new DateTimeZone($cfg->getDefaultTimezone() ?: 'UTC');
        if ($raw instanceof DateTimeInterface)
            $d = new DateTime('@'.$raw->getTimestamp());
        elseif (is_numeric($raw))
            $d = (new DateTime('@'.(int) $raw));
        elseif (!($d = Format::parseDateTime((string) $raw)))
            return null;
        $d->setTimezone($tz);
        return $d->format('Y-m-d');
    }
}
