<?php
if(!defined('OSTADMININC') || !$thisstaff || !$thisstaff->isAdmin()) die('Access Denied');

$matches=Filter::getSupportedMatches();
$match_types=Filter::getSupportedMatchTypes();

$info = $qs = array();
if($filter && $_REQUEST['a']!='add'){
    $title=__('Update Filter');
    $action='update';
    $submit_text=__('Save Changes');
    $info=array_merge($filter->getInfo());
    $info['id']=$filter->getId();
    $info['rules'] = $filter->getRules();
    $qs += array('id' => $filter->getId());
}else {
    $title=__('Add New Filter');
    $action='add';
    $submit_text=__('Add Filter');
    $info['isactive']=isset($info['isactive'])?$info['isactive']:0;
    $info['rules'] = array();
    $qs += array('a' => $_REQUEST['a']);
}
$info=Format::htmlchars(($errors && $_POST)?$_POST:$info, true);

// Automation: help topic and form-field conditions
$autoCatalog = FilterAutomation::getCatalog();
if ($errors && $_POST) {
    $autoTopic = (int) ($_POST['auto_topic_id'] ?? 0);
    $autoConds = FilterAutomation::posted($_POST);
} else {
    $autoTopic = ($filter && $filter->getId()) ? FilterAutomation::getTopicId($filter->getId()) : 0;
    $autoConds = array();
    if ($filter && $filter->getId()) {
        foreach (FilterAutomation::getConditions($filter->getId()) as $c) {
            $v = $c->getValue();
            $between = is_array($v) && $c->op == 'between';
            $autoConds[] = array('f' => (int) $c->field_id, 'op' => $c->op,
                'v' => $between ? $v[0] : $v, 'v2' => $between ? $v[1] : null);
        }
    }
}
$autoErrors = array();
foreach ((array) $errors as $k => $e)
    if (strpos($k, 'cond_') === 0)
        $autoErrors[(int) substr($k, 5)] = $e;
?>
<form action="filters.php?<?php echo Http::build_query($qs); ?>" method="post" class="save">
    <?php csrf_token(); ?>
    <input type="hidden" name="do" value="<?php echo $action; ?>">
    <input type="hidden" name="a" value="<?php echo Format::htmlchars($_REQUEST['a']); ?>">
    <input type="hidden" name="id" value="<?php echo $info['id']; ?>">
    <h2><?php echo $title; ?>
        <?php if (isset($info['name'])) { ?><small>
        — <?php echo $info['name']; ?></small>
        <?php } ?>
    </h2>
    <table class="form_table" width="940" border="0" cellspacing="0" cellpadding="2">
        <thead>
            <tr>
                <th colspan="2">
                    <em><?php echo __('Filters are executed based on execution order. Filter can target specific ticket source.');?></em>
                </th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td width="180" class="required">
                  <?php echo __('Filter Name');?>:
                </td>
                <td>
                    <input type="text" size="30" name="name" value="<?php echo $info['name']; ?>"
                        autofocus>
                    &nbsp;<span class="error">*&nbsp;<?php echo $errors['name']; ?></span>
                </td>
            </tr>
            <tr>
                <td width="180" class="required">
                  <?php echo __('Execution Order');?>:
                </td>
                <td>
                    <input type="text" size="6" name="execorder" value="<?php echo $info['execorder']; ?>">
                    <em>(1...99)</em>
                    &nbsp;<span class="error">*&nbsp;<?php echo $errors['execorder']; ?></span>
                    &nbsp;&nbsp;&nbsp;
                    <label class="inline checkbox">
                    <input type="checkbox" name="stop_onmatch" value="1" <?php echo $info['stop_onmatch']?'checked="checked"':''; ?> >
                    <?php echo __('<strong>Stop</strong> processing further on match!');?>
                    </label>
                    &nbsp;<i class="help-tip icon-question-sign" href="#execution_order"></i>
                </td>
            </tr>
            <tr>
                <td width="180" class="required">
                    <?php echo __('Filter Status');?>:
                </td>
                <td>
                    <input type="radio" name="isactive" value="1" <?php echo
                    $info['isactive']?'checked="checked"':''; ?>> <?php echo __('Active'); ?>
                    <input type="radio" name="isactive" value="0" <?php echo !$info['isactive']?'checked="checked"':''; ?>
                    > <?php echo __('Disabled'); ?>
                    &nbsp;<span class="error">*&nbsp;</span>
                </td>
            </tr>
            <tr>
                <td width="180">
                    <?php echo __('Help Topic');?>:
                </td>
                <td>
                    <select name="auto_topic_id" id="auto-topic">
                        <option value="0">&mdash; <?php echo __('All help topics');?> &mdash;</option>
<?php               foreach (Topic::getHelpTopics(false, true) as $tid => $tname) { ?>
                        <option value="<?php echo $tid; ?>" <?php echo $autoTopic == $tid ? 'selected="selected"' : ''; ?>><?php echo Format::htmlchars($tname); ?></option>
<?php               } ?>
                    </select>
                    <span class="error">&nbsp;<?php echo $errors['auto_topic_id']; ?></span>
                    <div class="faded"><em><?php echo __('With a help topic, the filter only applies to new tickets of that topic, and its form-field conditions can use the fields of that topic\'s forms.'); ?></em></div>
                </td>
            </tr>
            <tr>
                <td width="180" class="required">
                    <?php echo __('Target Channel');?>:
                </td>
                <td>
                    <select name="target">
                       <option value="">&mdash; <?php echo __('Select a Channel');?> &mdash;</option>
                       <?php
                       foreach(Filter::getTargets() as $k => $v) {
                           echo sprintf('<option value="%s" %s>%s</option>',
                                   $k, (($k==$info['target'])?'selected="selected"':''), $v);
                        }
                        $sql='SELECT email_id,email,name FROM '.EMAIL_TABLE.' email ORDER by name';
                        if(($res=db_query($sql)) && db_num_rows($res)) {
                            echo sprintf('<OPTGROUP label="%s">', __('System Emails'));
                            while(list($id,$email,$name)=db_fetch_row($res)) {
                                $selected=($info['email_id'] && $id==$info['email_id'])?'selected="selected"':'';
                                if($name)
                                    $email=Format::htmlchars("$name <$email>");
                                echo sprintf('<option value="%d" %s>%s</option>',$id,$selected,$email);
                            }
                            echo '</OPTGROUP>';
                        }
                        ?>
                    </select>
                    &nbsp;
                    <span class="error">*&nbsp;<?php echo $errors['target']; ?></span>&nbsp;
                    <i class="help-tip icon-question-sign" href="#target_channel"></i>
                </td>
            </tr>
        </tbody>
    </table>
    <ul class="clean tabs" style="margin-top:20px;" id="filter-tabs">
        <li class="active"><a href="#filter_rules"><i class="icon-filter"></i> <?php echo __('Filter Rules'); ?></a></li>
        <li><a href="#filter_actions"><i class="icon-bolt"></i> <?php echo __('Filter Actions'); ?></a></li>
        <li><a href="#internal_notes"><i class="icon-file-text-alt"></i> <?php echo __('Internal Notes'); ?></a></li>
    </ul>
    <!-- ====================== FILTER RULES ========================== -->
    <div class="tab_content" id="filter_rules">
        <table class="form_table" width="940" border="0" cellspacing="0" cellpadding="2">
            <thead>
                <tr>
                    <th colspan="2" style="text-align:left;">
                        <em><strong><?php echo __('Filter Rules');?></strong>: <?php
                        echo __('Rules are applied based on the criteria.');?>&nbsp;<span class="error">*&nbsp;<?php echo
                        $errors['rules']; ?></span></em>
                    </th>
                </tr>
            </thead>
            <tbody id="rules">
                <tr>
                    <td colspan=2>
                       <em><?php echo __('Rules Matching Criteria');?>:</em>
                        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                        <label>
                        <input type="radio" name="match_all_rules" value="1" <?php echo $info['match_all_rules']?'checked="checked"':''; ?>>
                            <?php echo __('Match All');?>
                        </label>
                        <span style="display:inline-block;width:10px"> </span>
                        <label>
                        <input type="radio" name="match_all_rules" value="0" <?php echo !$info['match_all_rules']?'checked="checked"':''; ?>>
                            <?php echo __('Match Any');?>
                        </label>
                        <span class="error">*</span>
                        <em>(<?php echo __('case-insensitive comparison');?>)</em>
                        &nbsp;<i class="help-tip icon-question-sign" href="#rules_matching_criteria"></i>
                    </td>
                </tr>
                <?php
                foreach ($info['rules'] as $i=>$rule) { ?>
                <tr>
                    <td colspan="2">
                        <select style="max-width: 200px;" name="rules[<?php echo $i; ?>][w]">
                            <option value="">&mdash; <?php echo __('Select One');?> &mdash;</option>
                                <?php
                                foreach ($matches as $group=>$ms) { ?>
                                    <optgroup label="<?php echo __($group); ?>"><?php
                                    foreach ($ms as $k=>$v) {
                                        $sel=($rule["w"]==$k)?'selected="selected"':'';
                                        echo sprintf('<option value="%s" %s>%s</option>',
                                            $k,$sel,__($v));
                                    } ?>
                                </optgroup>
                                <?php } ?>
                            </select>
                            <select name="rules[<?php echo $i; ?>][h]">
                                <option value="0">&mdash; <?php echo __('Select One');?> &mdash;</option>
                                <?php
                                    foreach($match_types as $k=>$v){
                                    $sel=($rule["h"]==$k)?'selected="selected"':'';
                                    echo sprintf('<option value="%s" %s>%s</option>',
                                        $k,$sel,$v);
                                }
                                ?>
                            </select>&nbsp;
                            <input type="text" size="60" name="rules[<?php echo $i; ?>][v]" value="<?php echo $rule["v"]; ?>">
                        <div class="pull-right" style="padding-right:20px;"><a href="#" class="clearrule"
                            onclick="javascript: $(this).closest('tr').remove();">(<?php echo __('clear');?>)</a></div>
                        <div class="error"><?php echo $errors["rule_$i"]; ?></div>
                    </td>
                </tr>
<?php           $maxi = max($maxi ?: 0, $i+1);
                } ?>
            </tbody>
            <tbody class="hidden" id="new-rule-template">
                <tr>
                    <td colspan="2">
                        <select style="max-width: 200px;" data-name="rulew">
                            <option value="">&mdash; <?php echo __('Select One');?> &mdash;</option>
                            <?php
                            foreach ($matches as $group=>$ms) { ?>
                                <optgroup label="<?php echo __($group); ?>"><?php
                                foreach ($ms as $k=>$v) {
                                    echo sprintf('<option value="%s">%s</option>',
                                        $k,__($v));
                                } ?>
                            </optgroup>
                            <?php } ?>
                        </select>
                        <select data-name="ruleh">
                            <option value="0">&mdash; <?php echo __('Select One');?> &mdash;</option>
                            <?php
                                foreach($match_types as $k=>$v){
                                echo sprintf('<option value="%s">%s</option>',
                                    $k,$v);
                            }
                            ?>
                        </select>&nbsp;
                        <input type="text" size="60" data-name="rulev">
                    </td>
                </tr>
            </tbody>
        </table>
        <div style="padding: 5px">
            <button class="green button" type="button" id="add-rule">
                <i class="icon-plus-sign"></i> <?php echo __('Add Rule'); ?>
            </button>
        </div>
        <table class="form_table" width="940" border="0" cellspacing="0" cellpadding="2" style="margin-top:14px">
            <thead>
                <tr><th style="text-align:left;">
                    <em><strong><?php echo __('Form field conditions'); ?></strong>: <?php
                    echo __('Conditions on the fields of the ticket forms, by field type. They are combined with the rules above using the "All" / "Any" option.'); ?></em>
                </th></tr>
            </thead>
            <tbody id="auto-conds"></tbody>
        </table>
        <div style="padding: 5px">
            <button class="green button" type="button" id="add-cond">
                <i class="icon-plus-sign"></i> <?php echo __('Add Condition'); ?>
            </button>
        </div>
    </div>
    <!-- ======================= FILTER ACTIONS ========================= -->
    <div class="tab_content hidden" id="filter_actions">
        <table class="form_table" width="940" border="0" cellspacing="0" cellpadding="2">
            <thead>
                <tr>
                    <th colspan="2">
                        <em><strong><?php echo __('Filter Actions');?></strong>:
                        <div><?php
                            echo __('Can be overridden by other filters depending on processing order.');
                        ?><br/><?php
                            echo __('Actions are executed in the order declared below');
                            ?></div></em>
                    </th>
                </tr>
            </thead>
            <tbody id="dynamic-actions" class="sortable-rows">
                <?php
                $existing = array();
                if ($filter) { foreach ($filter->getActions() as $A) {
                    $_warn = '';
                    $existing[] = $A->type;
                    $config = JsonDataParser::parse($A->configuration);
                    if($A->type == 'dept') {
                      $errors['topic_id'] = '';
                      $dept = Dept::lookup($config['dept_id']);
                      if($dept && !$dept->isActive())
                        $_warn = sprintf(__('%s must be active'), __('Department'));
                    }
                    elseif($A->type == 'topic') {
                      $errors['dept_id'] = '';
                      $topic = Topic::lookup($config['topic_id']);
                      if($topic && !$topic->isActive())
                        $_warn = sprintf(__('%s must be active'), __('Help Topic'));
                    }
                ?>
                <tr style="background-color:white"><td><i class="icon-sort icon-large icon-muted"></i>
                    <?php echo $A->getImpl()->getName(); ?>:</td>
                    <td>
                        <div style="position:relative"><?php
                        $form = $A->getImpl()->getConfigurationForm($_POST ?: false);
                        // XXX: Drop this when the ORM supports proper caching
                        $form->isValid();
                        include STAFFINC_DIR . 'templates/dynamic-form-simple.tmpl.php';
                        if($_warn) {
                            ?>&nbsp;<span class="error">*&nbsp;<?php echo $_warn; ?></span>
                        <?php } ?>
                        <input type="hidden" name="actions[]" value="<?php
                            echo $A->getId() ? "I".$A->getId() : "N".$A->getImpl()->getType(); ?>"/>
                        <div class="pull-right" style="position:absolute;top:2px;right:2px;">
                            <a href="#" title="<?php echo __('clear'); ?>" onclick="javascript:
                                if (!confirm(__('You sure?')))
                                return false;
                                $(this).closest('td').find('input[name=\'actions[]\']')
                                    .val(function(i,v) { return 'D' + v.substring(1); });
                                $(this).closest('tr').fadeOut(400, function() { $(this).hide(); });
                                return false;"><i class="icon-trash"></i></a>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php } } ?>
                </tbody>
            </table>
            <div style="padding: 5px">
                <i class="icon-plus-sign"></i>
                <select name="new-action" id="new-action-select"
                        onchange="javascript: $('#new-action-btn').trigger('click');">
                    <option value="">— <?php echo __('Select an Action'); ?> —</option>
                    <?php
                    $current_group = '';
                    foreach (FilterAction::allRegistered() as $group=>$actions) {
                        if ($group && $current_group != $group) {
                            if ($current_group) echo '</optgroup>';
                            $current_group = $group;
                            ?><optgroup label="<?php echo Format::htmlchars($group); ?>"><?php
                        }
                        foreach ($actions as $type=>$name) {
                    ?>
                    <option data-title="<?php echo $name; ?>" value="<?php echo $type; ?>"
                            data-multi-use="<?php echo $mu = FilterAction::lookupByType($type)->hasFlag(TriggerAction::FLAG_MULTI_USE); ?> " <?php
                            if (in_array($type, $existing) && !$mu) echo 'disabled="disabled"';
                            ?>><?php echo $name; ?></option>
                    <?php }
                    } ?>
                </select>
                <button id="new-action-btn" type="button" class="inline green button" onclick="javascript:
                    var dropdown = $('#new-action-select'), selected = dropdown.find(':selected');
                    dropdown.val('');
                    if (selected.val() === '')
                        return;
                    $('#dynamic-actions')
                      .append($('<tr></tr>')
                        .append($('<td></td>')
                          .text(selected.data('title') + ':')
                        ).append($('<td></td>')
                          .append($('<em></em>').text(__('Loading ...')))
                          .load('ajax.php/filter/action/' + selected.val() + '/config', function() {
                            if (!selected.data('multiUse')) selected.prop('disabled', true);
                          })
                        )
                      ).append(
                        $('<input>').attr({type:'hidden',name:'actions[]',value:'N'+selected.val()})
                      );"><?php echo __('Add'); ?>
                </button>
            </div>
    </div>
    <!-- ======================== INTERNAL NOTES ======================== -->
    <div class="tab_content hidden" id="internal_notes">
        <table class="form_table" width="940" border="0" cellspacing="0" cellpadding="2">
            <thead>
                <tr>
                    <th colspan="2">
                        <em><strong><?php echo __('Internal Notes');?></strong>: <?php
                            echo __("Be liberal, they're internal");?></em>
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td colspan=2>
                        <textarea class="richtext no-bar" name="notes" cols="21"
                            rows="8" style="width: 80%;"><?php echo Format::viewableImages($info['notes']); ?></textarea>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    <p style="text-align:center;">
        <input type="submit" name="submit" value="<?php echo $submit_text; ?>">
        <input type="reset"  name="reset"  value="<?php echo __('Reset');?>">
        <input type="button" name="cancel" value="<?php echo __('Cancel');?>" onclick='window.location.href="filters.php"'>
    </p>
</form>
<script type="text/javascript">
   var fixHelper = function(e, ui) {
      ui.children().each(function() {
          $(this).width($(this).width());
      });
      return ui;
   };
   $(function() {
     $('#dynamic-actions').sortable({helper: fixHelper, opacity: 0.5});
     var next = <?php echo $maxi ?: 0; ?>;
     $('#add-rule').click(function() {
       var clone = $('#new-rule-template tr').clone();
       clone.find('[data-name=rulew]').attr('name', 'rules['+next+'][w]');
       clone.find('[data-name=ruleh]').attr('name', 'rules['+next+'][h]');
       clone.find('[data-name=rulev]').attr('name', 'rules['+next+'][v]');
       clone.appendTo('#rules');
       next++;
     });
<?php if (!$info['rules']) { ?>
        $('#add-rule').trigger('click').trigger('click');
<?php } ?>
   });
</script>

<script type="text/javascript">
$(function() {
    var catalog = <?php echo JsonDataEncoder::encode($autoCatalog); ?>;
    var ops = <?php echo JsonDataEncoder::encode(FilterAutomation::getOperators()); ?>;
    var initial = <?php echo JsonDataEncoder::encode(array_values($autoConds)); ?>;
    var errors = <?php echo JsonDataEncoder::encode((object) $autoErrors); ?>;
    var L = <?php echo JsonDataEncoder::encode(array(
        'field' => __('Select a field'), 'cond' => __('Condition'), 'and' => __('and'),
        'remove' => __('clear'), 'general' => __('All help topics'))); ?>;
    var n = 0;
    var esc = function(t) { return $('<div>').text(t == null ? '' : String(t)).html(); };

    // Forms usable with the selected help topic: the ticket form + the topic's forms
    var forms = function() {
        var tid = $('#auto-topic').val(), ids = (catalog.topics || {})[tid] || [];
        var out = [];
        $.each(catalog.forms, function(fid, form) {
            if (tid == '0' || form.general || ids.indexOf(+fid) >= 0) out.push([fid, form]);
        });
        return out;
    };
    var fieldOf = function(id) {
        var f = null;
        $.each(catalog.forms, function(_, form) { if (form.fields[id]) f = form.fields[id]; });
        return f;
    };
    var fieldSelect = function(i, sel) {
        var h = '<select name="conds[' + i + '][f]" class="c-field" style="max-width:260px"><option value="">— ' + esc(L.field) + ' —</option>';
        forms().forEach(function(p) {
            h += '<optgroup label="' + esc(p[1].title) + '">';
            $.each(p[1].fields, function(id, f) {
                h += '<option value="' + id + '"' + (id == sel ? ' selected' : '') + '>' + esc(f.label) + '</option>';
            });
            h += '</optgroup>';
        });
        return h + '</select>';
    };
    var valueWidget = function(i, f, op, v, v2) {
        if (!f) return '';
        var nm = 'conds[' + i + ']';
        switch (f.type) {
        case 'bool': return '';
        case 'choice':
            var sel = (v || []).map(String), h = '';
            $.each(f.choices, function(k, label) {
                h += '<label style="display:inline-block;margin-right:12px"><input type="checkbox" name="' + nm + '[v][]" value="' + esc(k) + '"'
                    + (sel.indexOf(String(k)) >= 0 ? ' checked' : '') + '> ' + esc(label) + '</label>';
            });
            return h;
        case 'number':
        case 'date':
            var t = f.type == 'date' ? 'date' : 'number';
            var h = '<input type="' + t + '" step="any" name="' + nm + '[v]" value="' + esc(v) + '">';
            if (op == 'between')
                h += ' ' + esc(L.and) + ' <input type="' + t + '" step="any" name="' + nm + '[v2]" value="' + esc(v2) + '">';
            return h;
        default:
            return '<input type="text" size="40" name="' + nm + '[v]" value="' + esc(v) + '">';
        }
    };
    var opSelect = function(i, f, sel) {
        if (!f) return '';
        var h = '<select name="conds[' + i + '][op]" class="c-op">';
        $.each(ops[f.type], function(k, label) {
            h += '<option value="' + k + '"' + (k == sel ? ' selected' : '') + '>' + esc(label) + '</option>';
        });
        return h + '</select>';
    };
    var render = function(tr, c) {
        var i = tr.data('i'), f = fieldOf(c.f);
        if (f && !ops[f.type][c.op]) c.op = Object.keys(ops[f.type])[0];
        tr.find('td').html(fieldSelect(i, f ? c.f : '') + ' <span class="c-opbox">' + opSelect(i, f, c.op)
            + '</span> <span class="c-val">' + valueWidget(i, f, c.op, c.v, c.v2) + '</span>'
            + ' <a href="#" class="c-del">(' + esc(L.remove) + ')</a>'
            + (errors[i] ? '<div class="error">' + esc(errors[i]) + '</div>' : ''));
    };
    var add = function(c) {
        var tr = $('<tr><td></td></tr>').data('i', n++).appendTo('#auto-conds');
        render(tr, c || {});
    };
    // Current values of a row, to keep them while re-rendering
    var read = function(tr) {
        var c = {f: tr.find('.c-field').val(), op: tr.find('.c-op').val(),
                 v: tr.find('[name$="[v]"]').val(), v2: tr.find('[name$="[v2]"]').val()};
        var boxes = tr.find('input[type=checkbox]:checked');
        if (boxes.length) c.v = boxes.map(function() { return this.value; }).get();
        return c;
    };
    $('#auto-conds').on('change', '.c-field', function() {
        var tr = $(this).closest('tr');
        render(tr, {f: $(this).val()});
    }).on('change', '.c-op', function() {
        var tr = $(this).closest('tr');
        render(tr, read(tr));
    }).on('click', '.c-del', function() {
        $(this).closest('tr').remove();
        return false;
    });
    // A new topic changes the fields available
    $('#auto-topic').on('change', function() {
        $('#auto-conds tr').each(function() {
            var tr = $(this), c = read(tr), ok = false;
            forms().forEach(function(p) { if (p[1].fields[c.f]) ok = true; });
            render(tr, ok ? c : {});
        });
    });
    $('#add-cond').on('click', function() { add(); return false; });
    initial.forEach(function(c) { add(c); });
});
</script>
