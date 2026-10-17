<?php
if (!defined('OSTSCPINC') || !$thisstaff) die('Access Denied');

$qs = array_filter(array('from' => $filters['from'], 'to' => $filters['to'],
    'dept_id' => $filters['dept_id'] ?: null, 'topic_id' => $filters['topic_id'] ?: null));
?>
<style>
  table.report { border-collapse:collapse; width:100%; margin:6px 0 16px; }
  table.report th, table.report td { border:1px solid #ddd; padding:4px 7px; text-align:right; vertical-align:middle; }
  table.report th { background:#f0f0f0; }
  table.report td.text, table.report th.text { text-align:left; }
  table.report td.bar { width:150px; text-align:left; }
  table.report .meter { display:inline-block; height:9px; background:#2a7ae2; border-radius:3px; vertical-align:middle; }
  table.report td.heat { color:#000; }
  .report-tabs a { display:inline-block; padding:4px 12px; margin-right:4px; border:1px solid #ccc; border-bottom:none; border-radius:4px 4px 0 0; background:#f5f5f5; }
  .report-tabs a.active { background:#fff; font-weight:bold; }
</style>
<h2><?php echo __('Reports'); ?> &mdash; <?php echo $reports[$report]; ?></h2>

<form method="get" action="reports.php" style="margin:8px 0; padding:8px; background:#f7f7f7; border:1px solid #ddd">
  <input type="hidden" name="r" value="<?php echo $report; ?>">
<?php if ($data) { ?>
  <a class="button no-pjax pull-right" href="reports.php?<?php echo Http::build_query(array('r' => $report) + $qs + array('export' => 'csv')); ?>"><i class="icon-download"></i> <?php echo __('Export CSV'); ?></a>
<?php } ?>
  <label><?php echo __('Created from'); ?>: <input type="date" name="from" value="<?php echo Format::htmlchars($filters['from']); ?>"></label>
  <label><?php echo __('to'); ?>: <input type="date" name="to" value="<?php echo Format::htmlchars($filters['to']); ?>"></label>
  <select name="dept_id">
    <option value="0"><?php echo __('All departments'); ?></option>
<?php foreach ($thisstaff->getDepartmentNames() as $id => $name) { ?>
    <option value="<?php echo $id; ?>" <?php echo $filters['dept_id'] == $id ? 'selected="selected"' : ''; ?>><?php echo Format::htmlchars($name); ?></option>
<?php } ?>
  </select>
  <select name="topic_id" style="max-width:220px">
    <option value="0"><?php echo __('All help topics'); ?></option>
<?php foreach (Topic::getHelpTopics(false, true) as $id => $name) { ?>
    <option value="<?php echo $id; ?>" <?php echo $filters['topic_id'] == $id ? 'selected="selected"' : ''; ?>><?php echo Format::htmlchars($name); ?></option>
<?php } ?>
  </select>
  <button type="submit" class="button"><i class="icon-refresh"></i> <?php echo __('Generate'); ?></button>
</form>

<div class="report-tabs">
<?php foreach ($reports as $k => $label) { ?>
  <a class="<?php echo $k == $report ? 'active' : ''; ?>" href="reports.php?<?php echo Http::build_query(array('r' => $k) + $qs); ?>"><?php echo $label; ?></a>
<?php } ?>
</div>

<?php
if ($data) {
    $cols = $data['columns'];
    $chart = $data['chart'] ?? null;
    $max = 0;
    if ($chart)
        foreach ($data['rows'] as $r)
            $max = max($max, (float) ($r[$chart] ?? 0));
?>
<table class="report" id="report-table">
  <thead><tr>
<?php foreach ($cols as $k => $c) { ?>
    <th class="<?php echo in_array($c[1], array('text', 'ticket')) ? 'text' : ''; ?>"><?php echo $c[0]; ?></th>
<?php   if ($k === $chart) { ?><th class="text"></th><?php } ?>
<?php } ?>
  </tr></thead>
  <tbody>
<?php foreach ($data['rows'] as $r) { ?>
  <tr>
<?php   foreach ($cols as $k => $c) {
            $type = $c[1] == 'mixed' ? $r['type'] : $c[1];
            $v = $r[$k] ?? null; ?>
    <td class="<?php echo in_array($type, array('text', 'ticket')) ? 'text' : ''; ?>"><?php
            if ($type == 'ticket')
                echo sprintf('<a href="tickets.php?id=%d">%s</a>', $r['ticket_id'], Format::htmlchars($v));
            else
                echo Format::htmlchars(TicketReports::display($v, $type)); ?></td>
<?php       if ($k === $chart) { ?>
    <td class="bar"><?php if ($max > 0 && $v) { ?><span class="meter" style="width:<?php echo round(140 * $v / $max); ?>px"></span><?php } ?></td>
<?php       }
        } ?>
  </tr>
<?php }
    if (!$data['rows']) { ?>
  <tr><td class="text" colspan="<?php echo count($cols) + ($chart ? 1 : 0); ?>"><?php echo __('No tickets match these filters.'); ?></td></tr>
<?php } ?>
  </tbody>
</table>
<?php if (!empty($data['note'])) { ?>
<p class="faded"><small><?php echo $data['note']; ?></small></p>
<?php }

    // Help topic x stage: average time, heat-coloured
    foreach ((array) $matrix as $m) {
        $hmax = 0;
        foreach ($m['cells'] as $row)
            $hmax = max($hmax, max($row));
?>
<h3 style="margin-top:20px"><?php echo sprintf(__('%s: average time in each stage by help topic'),
    Format::htmlchars($m['workflow']->getName())); ?></h3>
<table class="report" id="topic-stage-matrix">
  <thead><tr><th class="text"><?php echo __('Help Topic'); ?></th>
<?php   foreach ($m['stages'] as $s) { ?><th><?php echo Format::htmlchars($s->getName()); ?></th><?php } ?>
  </tr></thead>
  <tbody>
<?php   foreach ($m['topics'] as $tid => $tname) { ?>
  <tr><td class="text"><?php echo Format::htmlchars($tname); ?></td>
<?php       foreach ($m['stages'] as $sid => $s) {
                $h = $m['cells'][$tid][$sid] ?? null;
                $a = ($h !== null && $hmax > 0) ? round(0.08 + 0.55 * $h / $hmax, 2) : 0; ?>
    <td class="heat" style="<?php echo $a ? "background:rgba(192,57,43,$a)" : ''; ?>"><?php
                echo TicketReports::duration($h); ?></td>
<?php       } ?>
  </tr>
<?php   } ?>
  </tbody>
</table>
<p class="faded"><small><?php echo __('The darker the cell, the longer tickets of that help topic stay in that stage.'); ?></small></p>
<?php
    }
} ?>
