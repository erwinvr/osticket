<?php
if (!defined('OSTADMININC') || !$thisstaff || !$thisstaff->isAdmin()) die('Access Denied');

if ($workflow && $_REQUEST['a'] != 'add') {
    $title = __('Update Workflow');
    $action = 'update';
    $submit_text = __('Save Changes');
    $qs = array('id' => $workflow->getId());
} else {
    $title = __('Add New Workflow');
    $action = 'create';
    $submit_text = __('Create Workflow');
    $workflow = Workflow::create();
    $qs = array('a' => 'add');
}

// Values to show: what was posted (form with errors) or what is saved
$posted = $_POST && $errors;
$saved = $workflow->getStages();
if ($posted) {
    $name = $_POST['name'];
    $isactive = !empty($_POST['isactive']);
    $mode = $_POST['mode'] ?? 'free';
    $notes = $_POST['notes'];
    $topicIds = array_map('intval', (array) @$_POST['topics']);
    $stages = (array) @$_POST['stages'];
    $initial = $_POST['initial'] ?? '';
    $next = (array) @$_POST['next'];
} else {
    $name = $workflow->name;
    $isactive = $workflow->isActive();
    $mode = $workflow->isStrict() ? 'strict' : 'free';
    $notes = $workflow->notes;
    $topicIds = $workflow->getId() ? $workflow->getTopicIds() : array();
    $stages = array();
    $initial = '';
    $next = array();
    foreach ($saved as $id => $s) {
        $stages[$id] = array('name' => $s->getName(), 'dept_id' => $s->dept_id,
            'team_id' => $s->team_id, 'final' => $s->isFinal());
        if ($s->isInitial())
            $initial = $id;
        foreach ($s->getNextStageIds() as $row)
            $next[$id][] = $row[0];
    }
}

$depts = Dept::getDepartments();
$teams = Team::getTeams();
$topics = Topic::getHelpTopics(false, true);

$stageRow = function($key, $s) use ($depts, $teams, $initial, $errors, $saved) { ?>
      <tr class="stage-row">
        <td>
          <a href="#" class="move-stage" data-dir="up" title="<?php echo __('Move up'); ?>"><i class="icon-chevron-up"></i></a>
          <a href="#" class="move-stage" data-dir="down" title="<?php echo __('Move down'); ?>"><i class="icon-chevron-down"></i></a>
          <span class="stage-pos faded"></span>
        </td>
        <td><input type="text" size="22" maxlength="128" name="stages[<?php echo $key; ?>][name]"
            value="<?php echo Format::htmlchars($s['name'] ?? ''); ?>" placeholder="<?php echo __('Stage name'); ?>"/></td>
        <td><select name="stages[<?php echo $key; ?>][dept_id]">
            <option value="0">&mdash; <?php echo __('Department'); ?> &mdash;</option>
<?php   foreach ($depts as $id => $n) { ?>
            <option value="<?php echo $id; ?>" <?php echo ($s['dept_id'] ?? 0) == $id ? 'selected="selected"' : '';
                ?>><?php echo Format::htmlchars($n); ?></option>
<?php   } ?>
          </select></td>
        <td><select name="stages[<?php echo $key; ?>][team_id]">
            <option value="0">&mdash; <?php echo __('Team'); ?> &mdash;</option>
<?php   foreach ($teams as $id => $n) { ?>
            <option value="<?php echo $id; ?>" <?php echo ($s['team_id'] ?? 0) == $id ? 'selected="selected"' : '';
                ?>><?php echo Format::htmlchars($n); ?></option>
<?php   } ?>
          </select></td>
        <td style="text-align:center"><input type="radio" name="initial" value="<?php echo $key; ?>"
            <?php echo (string) $initial === (string) $key ? 'checked="checked"' : ''; ?>/></td>
        <td style="text-align:center"><input type="checkbox" name="stages[<?php echo $key; ?>][final]" value="1"
            <?php echo !empty($s['final']) ? 'checked="checked"' : ''; ?>/></td>
        <td>
<?php   if (!isset($saved[$key]) || !$saved[$key]->getNumTickets()) { ?>
          <a href="#" class="drop-stage" title="<?php echo __('Delete'); ?>"><i class="icon-trash"></i></a>
<?php   } else { ?>
          <i class="icon-lock faded" title="<?php echo __('This stage has tickets'); ?>"></i>
<?php   } ?>
        </td>
      </tr>
<?php   if (isset($errors['stages'][$key])) { ?>
      <tr class="stage-error"><td></td><td colspan="6" class="error"><?php echo $errors['stages'][$key]; ?></td></tr>
<?php   }
};
?>
<form action="workflows.php?<?php echo Http::build_query($qs); ?>" method="post" class="save">
 <?php csrf_token(); ?>
 <input type="hidden" name="do" value="<?php echo $action; ?>">
 <input type="hidden" name="id" value="<?php echo $workflow->getId(); ?>">
 <h2><?php echo $title; ?>
<?php if ($workflow->getId()) { ?>
    <small>&mdash; <?php echo Format::htmlchars($workflow->getName()); ?></small>
<?php } ?>
 </h2>
 <table class="form_table" width="940" border="0" cellspacing="0" cellpadding="2">
    <thead>
        <tr><th colspan="2"><em><strong><?php echo __('Workflow'); ?></strong>:
            <?php echo __('A new ticket follows the workflow of its help topic.'); ?></em></th></tr>
    </thead>
    <tbody>
        <tr>
            <td width="180" class="required"><?php echo __('Name'); ?>:</td>
            <td><input type="text" size="40" maxlength="128" name="name" autofocus
                value="<?php echo Format::htmlchars($name); ?>"/>
                &nbsp;<span class="error">*&nbsp;<?php echo $errors['name']; ?></span></td>
        </tr>
        <tr>
            <td width="180" class="required"><?php echo __('Status'); ?>:</td>
            <td>
                <label><input type="radio" name="isactive" value="1" <?php echo $isactive ? 'checked="checked"' : ''; ?>/>
                    <strong><?php echo __('Active'); ?></strong></label>
                &nbsp;
                <label><input type="radio" name="isactive" value="0" <?php echo !$isactive ? 'checked="checked"' : ''; ?>/>
                    <?php echo __('Disabled'); ?></label>
                <div class="faded"><em><?php echo __('A disabled workflow is not used for new tickets; tickets already in it keep their stage.'); ?></em></div>
            </td>
        </tr>
        <tr>
            <td width="180" class="required"><?php echo __('Transfers'); ?>:</td>
            <td>
                <label><input type="radio" name="mode" value="free" <?php echo $mode != 'strict' ? 'checked="checked"' : ''; ?>/>
                    <strong><?php echo __('Free'); ?></strong> &mdash; <?php echo __('agents can also transfer and assign tickets outside the workflow'); ?></label><br/>
                <label><input type="radio" name="mode" value="strict" <?php echo $mode == 'strict' ? 'checked="checked"' : ''; ?>/>
                    <strong><?php echo __('Fixed'); ?></strong> &mdash; <?php echo __('tickets only move between stages with Derive'); ?></label>
            </td>
        </tr>
        <tr>
            <td width="180" style="vertical-align:top"><?php echo __('Help Topics'); ?>:</td>
            <td>
                <span class="error"><?php echo $errors['topics']; ?></span>
<?php       foreach ($topics as $tid => $tname) {
                $other = Workflow::lookupByTopic($tid); ?>
                <label class="checkbox" style="display:block">
                    <input type="checkbox" name="topics[]" value="<?php echo $tid; ?>"
                        <?php echo in_array($tid, $topicIds) ? 'checked="checked"' : ''; ?>/>
                    <?php echo Format::htmlchars($tname); ?>
<?php           if ($other && $other->getId() != $workflow->getId()) { ?>
                    <small class="faded">(<?php echo sprintf(__('uses %s'), Format::htmlchars($other->getName())); ?>)</small>
<?php           } ?>
                </label>
<?php       } ?>
            </td>
        </tr>
    </tbody>
 </table>

 <table class="form_table" width="940" border="0" cellspacing="0" cellpadding="2" id="stages">
    <thead>
        <tr><th colspan="7"><em><strong><?php echo __('Stages'); ?></strong>:
            <?php echo __('Each stage has the department and the team that attend its tickets. Mark one initial stage; final stages close the ticket.'); ?></em>
            <div class="error"><?php echo $errors['stages_err']; ?></div></th></tr>
        <tr>
            <th width="70"><?php echo __('Order'); ?></th>
            <th><?php echo __('Name'); ?></th>
            <th><?php echo __('Department'); ?></th>
            <th><?php echo __('Team'); ?></th>
            <th width="60"><?php echo __('Initial'); ?></th>
            <th width="60"><?php echo __('Final'); ?></th>
            <th width="30"></th>
        </tr>
    </thead>
    <tbody>
<?php
    foreach ($stages as $key => $s)
        $stageRow($key, $s);
    if (!$stages)
        $stageRow('new1', array());
?>
      <tr id="add-stage-row"><td colspan="7">
        <button type="button" class="action-button" id="add-stage"><i class="icon-plus-sign"></i> <?php echo __('Add stage'); ?></button>
      </td></tr>
    </tbody>
 </table>
 <table id="stage-template" style="display:none"><tbody><?php $stageRow('__KEY__', array()); ?></tbody></table>

 <table class="form_table" width="940" border="0" cellspacing="0" cellpadding="2">
    <thead>
        <tr><th colspan="2"><em><strong><?php echo __('Allowed derivations'); ?></strong>:
            <?php echo __('Stages each stage can derive tickets to. New stages appear here after saving.'); ?></em></th></tr>
    </thead>
    <tbody>
<?php
    $savedStages = array_intersect_key($saved, $stages);
    if (!$savedStages) { ?>
        <tr><td colspan="2"><em class="faded"><?php echo __('Save the stages first.'); ?></em></td></tr>
<?php
    }
    foreach ($savedStages as $from => $sf) { ?>
        <tr>
            <td width="180" style="vertical-align:top"><strong><?php echo Format::htmlchars($stages[$from]['name'] ?? $sf->getName()); ?></strong> &rarr;</td>
            <td>
<?php   if (!empty($stages[$from]['final'])) { ?>
                <em class="faded"><?php echo __('Final stage: the ticket is closed here.'); ?></em>
<?php   } else {
            foreach ($savedStages as $to => $st) {
                if ($to == $from) continue; ?>
                <label class="checkbox" style="display:inline-block; margin-right:16px">
                    <input type="checkbox" name="next[<?php echo $from; ?>][]" value="<?php echo $to; ?>"
                        <?php echo in_array($to, (array) ($next[$from] ?? array())) ? 'checked="checked"' : ''; ?>/>
                    <?php echo Format::htmlchars($stages[$to]['name'] ?? $st->getName()); ?>
                </label>
<?php       }
        } ?>
            </td>
        </tr>
<?php
    } ?>
    </tbody>
 </table>

 <table class="form_table" width="940" border="0" cellspacing="0" cellpadding="2">
    <thead><tr><th><em><strong><?php echo __('Admin Notes'); ?></strong>: <?php echo __('Internal notes viewable by all admins.'); ?></em></th></tr></thead>
    <tbody><tr><td><textarea class="richtext no-bar" name="notes" cols="21" rows="6" style="width:80%"><?php
        echo Format::viewableImages($notes); ?></textarea></td></tr></tbody>
 </table>

 <p style="text-align:center">
    <input type="submit" name="submit" value="<?php echo $submit_text; ?>">
    <input type="button" name="cancel" value="<?php echo __('Cancel'); ?>" onclick='window.location.href="workflows.php"'>
 </p>
</form>

<?php
if ($workflow->getId()) { ?>
<form action="workflows.php?id=<?php echo $workflow->getId(); ?>" method="post"
    onsubmit="return confirm('<?php echo __('Delete this workflow?'); ?>');" style="text-align:center">
    <?php csrf_token(); ?>
    <input type="hidden" name="do" value="delete">
    <input type="hidden" name="id" value="<?php echo $workflow->getId(); ?>">
<?php
    if ($workflow->getNumTickets()) { ?>
    <p class="faded"><?php echo __('This workflow has tickets and cannot be deleted. Deactivate it instead.'); ?></p>
<?php
    } else { ?>
    <input type="submit" class="red button" value="<?php echo __('Delete Workflow'); ?>">
<?php
    } ?>
</form>
<?php
} ?>

<script type="text/javascript">
$(function() {
    var counter = 1;
    var number = function() {
        $('#stages tr.stage-row .stage-pos').each(function(i) {
            $(this).text((i + 1) + '.');
        });
    };
    $('#add-stage').on('click', function() {
        var key;
        do { key = 'new' + (++counter); } while ($('[name="stages[' + key + '][name]"]').length);
        var html = $('#stage-template tbody').html().replace(/__KEY__/g, key);
        $(html).insertBefore('#add-stage-row');
        number();
        return false;
    });
    $(document).on('click', '#stages a.drop-stage', function() {
        $(this).closest('tr').next('tr.stage-error').remove();
        $(this).closest('tr').remove();
        number();
        return false;
    });
    $(document).on('click', '#stages a.move-stage', function() {
        var tr = $(this).closest('tr');
        if ($(this).data('dir') == 'up')
            tr.prevAll('tr.stage-row').first().before(tr);
        else
            tr.nextAll('tr.stage-row').first().after(tr);
        number();
        return false;
    });
    number();
});
</script>
