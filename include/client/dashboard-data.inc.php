<?php
// Figures of the dashboard (also returned alone for the auto refresh)
if (!defined('OSTCLIENTINC')) die('Access Denied');

if (!$dashboard) { ?>
  <p class="faded"><?php echo __('There are no active workflows yet.'); ?></p>
<?php
}
foreach ($dashboard as $d) {
    $stages = $d['stages']; ?>
  <h2><?php echo Format::htmlchars($d['workflow']->getName()); ?></h2>

  <h3><?php echo __('Tickets by help topic and stage'); ?></h3>
  <table class="dash">
    <thead><tr>
      <th><?php echo __('Help Topic'); ?></th>
<?php foreach ($stages as $s) { ?>
      <th><?php echo Format::htmlchars($s->getName()); ?></th>
<?php } ?>
      <th><?php echo __('Total'); ?></th>
    </tr></thead>
    <tbody>
<?php
    $colTotals = array_fill_keys(array_keys($stages), 0);
    foreach ($d['topics'] as $tid => $tname) {
        $rowTotal = 0; ?>
      <tr><td><?php echo Format::htmlchars($tname); ?></td>
<?php   foreach ($stages as $sid => $s) {
            $n = $d['counts'][$tid][$sid] ?? 0;
            $rowTotal += $n;
            $colTotals[$sid] += $n; ?>
        <td class="<?php echo $n ? '' : 'zero'; ?>"><?php echo $n; ?></td>
<?php   } ?>
        <td><strong><?php echo $rowTotal; ?></strong></td></tr>
<?php
    }
    if (!$d['topics']) { ?>
      <tr><td colspan="<?php echo count($stages) + 2; ?>" class="faded"><?php echo __('No tickets in this workflow yet.'); ?></td></tr>
<?php
    } ?>
      <tr class="total"><td><?php echo __('Total'); ?></td>
<?php foreach ($colTotals as $n) { ?><td><?php echo $n; ?></td><?php } ?>
        <td><?php echo array_sum($colTotals); ?></td></tr>
    </tbody>
  </table>

  <h3><?php echo __('SLA breaches by stage'); ?></h3>
  <table class="dash">
    <thead><tr>
      <th><?php echo __('Stage'); ?></th>
      <th><?php echo __('Open tickets'); ?></th>
      <th><?php echo __('Past SLA'); ?></th>
      <th><?php echo __('% past SLA'); ?></th>
    </tr></thead>
    <tbody>
<?php
    $open = $late = 0;
    foreach ($stages as $sid => $s) {
        list($o, $l) = $d['sla'][$sid] ?? array(0, 0);
        $open += $o; $late += $l;
        $pct = $o ? round(100 * $l / $o) : 0; ?>
      <tr><td><?php echo Format::htmlchars($s->getName());
            if ($s->isFinal()) echo ' <small class="faded">('.__('final').')</small>'; ?></td>
        <td class="<?php echo $o ? '' : 'zero'; ?>"><?php echo $o; ?></td>
        <td class="<?php echo $l ? 'late' : 'zero'; ?>"><?php echo $l; ?></td>
        <td><?php echo $pct; ?>%<span class="meter"><span style="width:<?php echo $pct; ?>%"></span></span></td></tr>
<?php
    } ?>
      <tr class="total"><td><?php echo __('Total'); ?></td><td><?php echo $open; ?></td>
        <td><?php echo $late; ?></td><td><?php echo $open ? round(100 * $late / $open) : 0; ?>%</td></tr>
    </tbody>
  </table>
<?php
} ?>
  <p class="faded"><small><?php echo __('Tickets are counted in the stage they are in now. "Past SLA" counts open tickets whose due date has already passed.'); ?></small></p>
