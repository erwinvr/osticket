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
            'team_id' => $s->team_id, 'final' => $s->isFinal(), 'sla_id' => $s->sla_id,
            'pos_x' => $s->pos_x, 'pos_y' => $s->pos_y);
        if ($s->isInitial())
            $initial = $id;
        foreach ($s->getNextStageIds() as $row)
            $next[$id][] = $row[0];
    }
}

$depts = Dept::getDepartments();
$teams = Team::getTeams();
$topics = Topic::getHelpTopics(false, true);
$slas = SLA::getSLAs(array('nameOnly' => true));

$stageRow = function($key, $s) use ($depts, $teams, $slas, $initial, $errors, $saved) { ?>
      <tr class="stage-row">
        <td>
          <a href="#" class="move-stage" data-dir="up" title="<?php echo __('Move up'); ?>"><i class="icon-chevron-up"></i></a>
          <a href="#" class="move-stage" data-dir="down" title="<?php echo __('Move down'); ?>"><i class="icon-chevron-down"></i></a>
          <span class="stage-pos faded"></span>
          <input type="hidden" name="stages[<?php echo $key; ?>][pos_x]" value="<?php echo (int) ($s['pos_x'] ?? -1); ?>"/>
          <input type="hidden" name="stages[<?php echo $key; ?>][pos_y]" value="<?php echo (int) ($s['pos_y'] ?? -1); ?>"/>
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
        <td><select name="stages[<?php echo $key; ?>][sla_id]" style="max-width:130px">
            <option value="0">&mdash; <?php echo __('None'); ?> &mdash;</option>
<?php   foreach ($slas as $id => $n) { ?>
            <option value="<?php echo $id; ?>" <?php echo ($s['sla_id'] ?? 0) == $id ? 'selected="selected"' : '';
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
      <tr class="stage-error"><td></td><td colspan="7" class="error"><?php echo $errors['stages'][$key]; ?></td></tr>
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
        <tr><th colspan="8"><em><strong><?php echo __('Stages'); ?></strong>:
            <?php echo __('Each stage has the department and the team that attend its tickets. Mark one initial stage; final stages close the ticket. The SLA of a stage is measured from when the ticket enters it.'); ?></em>
            <div class="error"><?php echo $errors['stages_err']; ?></div></th></tr>
        <tr>
            <th width="70"><?php echo __('Order'); ?></th>
            <th><?php echo __('Name'); ?></th>
            <th><?php echo __('Department'); ?></th>
            <th><?php echo __('Team'); ?></th>
            <th><?php echo __('SLA'); ?></th>
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
      <tr id="add-stage-row"><td colspan="8">
        <button type="button" class="action-button" id="add-stage"><i class="icon-plus-sign"></i> <?php echo __('Add stage'); ?></button>
      </td></tr>
    </tbody>
 </table>
 <table id="stage-template" style="display:none"><tbody><?php $stageRow('__KEY__', array()); ?></tbody></table>

<?php
// Graphical editor: saved stages as boxes, allowed derivations as arrows.
// The checkboxes of "Allowed derivations" stay the source of truth.
$savedStages = array_intersect_key($saved, $stages);
if ($savedStages) {
    $nodes = array();
    foreach ($savedStages as $id => $st) {
        $d = Dept::lookup($stages[$id]['dept_id'] ?? $st->dept_id);
        $t = Team::lookup($stages[$id]['team_id'] ?? $st->team_id);
        $nodes[] = array(
            'id' => (int) $id,
            'name' => (string) ($stages[$id]['name'] ?? $st->getName()),
            'sub' => sprintf('%s · %s', $d ? $d->getName() : '—', $t ? $t->getName() : '—'),
            'initial' => (string) $initial === (string) $id,
            'final' => !empty($stages[$id]['final']),
            'x' => (int) ($stages[$id]['pos_x'] ?? -1),
            'y' => (int) ($stages[$id]['pos_y'] ?? -1),
        );
    } ?>
 <table class="form_table" width="940" border="0" cellspacing="0" cellpadding="2">
    <thead>
        <tr><th><em><strong><?php echo __('Workflow diagram'); ?></strong>:
            <?php echo __('Drag the stages to arrange them. To allow a derivation, drag from the dot of a stage to another stage. Click an arrow to remove it. Save to keep the changes.'); ?></em></th></tr>
    </thead>
    <tbody><tr><td>
        <svg id="wf-editor" width="920" height="420" style="background:#fcfcfc; border:1px solid #ddd; cursor:default; user-select:none">
            <defs>
                <marker id="wf-arrow" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse">
                    <path d="M0 0L10 5L0 10z" fill="#666"/></marker>
                <marker id="wf-arrow-hot" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse">
                    <path d="M0 0L10 5L0 10z" fill="#c0392b"/></marker>
            </defs>
            <g id="wf-edges"></g><g id="wf-nodes"></g><line id="wf-link" stroke="#2a7ae2" stroke-width="2" stroke-dasharray="4 4" style="display:none"/>
        </svg>
        <div class="faded"><small><?php echo __('Blue: initial stage. Green: final stage (closes the ticket). New stages appear here after saving.'); ?></small></div>
    </td></tr></tbody>
 </table>
 <script type="text/javascript">
 $(function() {
    var nodes = <?php echo JsonDataEncoder::encode($nodes); ?>;
    var W = 180, H = 62, svg = document.getElementById('wf-editor'), NS = 'http://www.w3.org/2000/svg';
    var byId = {};
    nodes.forEach(function(n, i) {
        if (n.x < 0 || n.y < 0) { n.x = 20 + (i % 4) * 225; n.y = 30 + Math.floor(i / 4) * 140; }
        byId[n.id] = n;
    });
    var el = function(tag, attrs, parent) {
        var e = document.createElementNS(NS, tag);
        for (var k in attrs) e.setAttribute(k, attrs[k]);
        if (parent) parent.appendChild(e);
        return e;
    };
    var box = function(id) { return $('.wf-next[data-from="' + id + '"]'); };
    var edges = function() {
        var list = [];
        $('.wf-next:checked').each(function() {
            list.push([+$(this).data('from'), +$(this).data('to')]);
        });
        return list;
    };
    // Point where the line between two centres leaves a box
    var exitPoint = function(a, b) {
        var cx = a.x + W / 2, cy = a.y + H / 2, dx = b.x + W / 2 - cx, dy = b.y + H / 2 - cy;
        if (!dx && !dy) return [cx, cy];
        var s = Math.min(Math.abs((W / 2) / (dx || 1e-9)), Math.abs((H / 2) / (dy || 1e-9)));
        return [cx + dx * s, cy + dy * s];
    };
    var render = function() {
        var ge = document.getElementById('wf-edges'), gn = document.getElementById('wf-nodes');
        ge.innerHTML = ''; gn.innerHTML = '';
        var list = edges(), keys = {};
        list.forEach(function(e) { keys[e[0] + '>' + e[1]] = 1; });
        list.forEach(function(e) {
            var a = byId[e[0]], b = byId[e[1]];
            if (!a || !b) return;
            var p = exitPoint(a, b), q = exitPoint(b, a), off = keys[e[1] + '>' + e[0]] ? 7 : 0;
            var len = Math.hypot(q[0] - p[0], q[1] - p[1]) || 1, ox = -(q[1] - p[1]) / len * off, oy = (q[0] - p[0]) / len * off;
            var line = el('line', {x1: p[0] + ox, y1: p[1] + oy, x2: q[0] + ox, y2: q[1] + oy,
                stroke: '#666', 'stroke-width': 1.5, 'marker-end': 'url(#wf-arrow)', style: 'cursor:pointer'}, ge);
            var hit = el('line', {x1: p[0] + ox, y1: p[1] + oy, x2: q[0] + ox, y2: q[1] + oy,
                stroke: 'transparent', 'stroke-width': 12, style: 'cursor:pointer'}, ge);
            el('title', {}, hit).textContent = a.name + ' → ' + b.name;
            $(hit).on('mouseenter', function() { line.setAttribute('stroke', '#c0392b'); line.setAttribute('marker-end', 'url(#wf-arrow-hot)'); })
                  .on('mouseleave', function() { line.setAttribute('stroke', '#666'); line.setAttribute('marker-end', 'url(#wf-arrow)'); })
                  .on('click', function() {
                if (confirm(<?php echo JsonDataEncoder::encode(__('Remove this derivation?')); ?> + '\n' + a.name + ' → ' + b.name)) {
                    box(a.id).filter('[data-to="' + b.id + '"]').prop('checked', false);
                    render();
                }
            });
        });
        nodes.forEach(function(n) {
            var g = el('g', {transform: 'translate(' + n.x + ',' + n.y + ')', 'data-id': n.id}, gn);
            var stroke = n.initial ? '#2a7ae2' : (n.final ? '#27ae60' : '#999');
            var fill = n.initial ? '#eaf2fd' : (n.final ? '#eaf7ef' : '#fff');
            el('rect', {width: W, height: H, rx: 8, fill: fill, stroke: stroke, 'stroke-width': n.initial || n.final ? 2 : 1.2, style: 'cursor:move', 'class': 'wf-body'}, g);
            var t = el('text', {x: 12, y: 22, 'font-size': 13, 'font-weight': 'bold', fill: '#222', 'pointer-events': 'none'}, g);
            t.textContent = n.name.length > 24 ? n.name.substr(0, 23) + '…' : n.name;
            var s = el('text', {x: 12, y: 40, 'font-size': 11, fill: '#555', 'pointer-events': 'none'}, g);
            s.textContent = n.sub.length > 30 ? n.sub.substr(0, 29) + '…' : n.sub;
            var tag = el('text', {x: 12, y: 55, 'font-size': 10, fill: '#777', 'pointer-events': 'none'}, g);
            tag.textContent = n.initial ? <?php echo JsonDataEncoder::encode(__('Initial')); ?> : (n.final ? <?php echo JsonDataEncoder::encode(__('Final: closes the ticket')); ?> : '');
            if (!n.final) {
                var dot = el('circle', {cx: W, cy: H / 2, r: 7, fill: '#2a7ae2', stroke: '#fff', 'stroke-width': 2, style: 'cursor:crosshair', 'class': 'wf-dot'}, g);
                el('title', {}, dot).textContent = <?php echo JsonDataEncoder::encode(__('Drag to another stage to allow a derivation')); ?>;
            }
        });
    };
    var pt = function(ev) {
        var r = svg.getBoundingClientRect();
        return [ev.clientX - r.left, ev.clientY - r.top];
    };
    var nodeAt = function(p) {
        for (var i = nodes.length - 1; i >= 0; i--) {
            var n = nodes[i];
            if (p[0] >= n.x && p[0] <= n.x + W && p[1] >= n.y && p[1] <= n.y + H) return n;
        }
        return null;
    };
    var drag = null, link = null;
    // Stages must keep some room between them so their arrows stay visible
    var crowded = function(n) {
        return nodes.some(function(m) {
            return m !== n && Math.abs(m.x - n.x) < W + 30 && Math.abs(m.y - n.y) < H + 20;
        });
    };
    $(svg).on('mousedown', '.wf-body', function(ev) {
        var n = byId[$(this).parent().data('id')], p = pt(ev);
        drag = {n: n, dx: p[0] - n.x, dy: p[1] - n.y, x0: n.x, y0: n.y};
        ev.preventDefault();
    }).on('mousedown', '.wf-dot', function(ev) {
        link = byId[$(this).parent().data('id')];
        ev.preventDefault(); ev.stopPropagation();
    });
    $(document).on('mousemove', function(ev) {
        var p = pt(ev);
        if (drag) {
            drag.n.x = Math.max(0, Math.min(920 - W, Math.round(p[0] - drag.dx)));
            drag.n.y = Math.max(0, Math.min(420 - H, Math.round(p[1] - drag.dy)));
            render();
        } else if (link) {
            $('#wf-link').attr({x1: link.x + W, y1: link.y + H / 2, x2: p[0], y2: p[1]}).show();
        }
    }).on('mouseup', function(ev) {
        if (drag) {
            if (crowded(drag.n)) {
                drag.n.x = drag.x0; drag.n.y = drag.y0;
                render();
            }
            $('[name="stages[' + drag.n.id + '][pos_x]"]').val(drag.n.x);
            $('[name="stages[' + drag.n.id + '][pos_y]"]').val(drag.n.y);
            drag = null;
        } else if (link) {
            var target = nodeAt(pt(ev));
            if (target && target.id != link.id)
                box(link.id).filter('[data-to="' + target.id + '"]').prop('checked', true);
            $('#wf-link').hide();
            link = null;
            render();
        }
    });
    $(document).on('change', '.wf-next', render);
    render();
 });
 </script>
<?php
} ?>

 <table class="form_table" width="940" border="0" cellspacing="0" cellpadding="2">
    <thead>
        <tr><th colspan="2"><em><strong><?php echo __('Allowed derivations'); ?></strong>:
            <?php echo __('Stages each stage can derive tickets to. New stages appear here after saving.'); ?></em></th></tr>
    </thead>
    <tbody>
<?php
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
                    <input type="checkbox" class="wf-next" data-from="<?php echo $from; ?>" data-to="<?php echo $to; ?>"
                        name="next[<?php echo $from; ?>][]" value="<?php echo $to; ?>"
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
