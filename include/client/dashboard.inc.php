<?php
if (!defined('OSTCLIENTINC')) die('Access Denied');
?>
<style>
  #dash-bar { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px; margin-bottom:12px; }
  #dash-bar .refresh button { border:1px solid #bbb; background:#f5f5f5; padding:3px 10px; border-radius:4px; cursor:pointer; }
  #dash-bar .refresh button.on { background:#0054a6; border-color:#0054a6; color:#fff; }
  #dash-data table.dash { border-collapse:collapse; width:100%; margin:6px 0 18px; }
  #dash-data table.dash th, #dash-data table.dash td { border:1px solid #ddd; padding:5px 8px; text-align:right; }
  #dash-data table.dash th:first-child, #dash-data table.dash td:first-child { text-align:left; }
  #dash-data table.dash thead th { background:#f0f0f0; }
  #dash-data table.dash tr.total td { font-weight:bold; background:#fafafa; }
  #dash-data td.zero { color:#bbb; }
  #dash-data td.late { color:#b00; font-weight:bold; }
  #dash-data .meter { display:inline-block; vertical-align:middle; width:70px; height:8px; background:#eee; border-radius:4px; margin-left:6px; overflow:hidden; }
  #dash-data .meter span { display:block; height:100%; background:#c0392b; }
  #dash-data h3 { margin:18px 0 4px; }
</style>
<div id="dash-bar">
  <h1 style="margin:0"><?php echo __('Dashboard'); ?></h1>
  <div class="refresh">
    <?php echo __('Auto refresh'); ?>:
    <button type="button" data-every="0"><?php echo __('Off'); ?></button>
    <button type="button" data-every="5000"><?php echo __('5 seconds'); ?></button>
    <button type="button" data-every="300000"><?php echo __('5 minutes'); ?></button>
    <button type="button" data-every="600000"><?php echo __('10 minutes'); ?></button>
    <span class="faded" id="dash-updated"></span>
  </div>
</div>
<div id="dash-data"><?php include CLIENTINC_DIR.'dashboard-data.inc.php'; ?></div>
<script type="text/javascript">
(function() {
  var timer = null, busy = false, KEY = 'osticket.dashboard.refresh';
  var stamp = function() {
    $('#dash-updated').text(<?php echo JsonDataEncoder::encode(__('Updated')); ?> + ' ' + new Date().toLocaleTimeString());
  };
  var reload = function() {
    if (busy) return;
    busy = true;
    $.ajax({url: 'dashboard.php?partial=1', cache: false})
      .done(function(html) { $('#dash-data').html(html); stamp(); })
      .always(function() { busy = false; });
  };
  var every = function(ms) {
    if (timer) clearInterval(timer);
    timer = ms > 0 ? setInterval(reload, ms) : null;
    $('#dash-bar .refresh button').removeClass('on')
      .filter('[data-every="' + ms + '"]').addClass('on');
    try { localStorage.setItem(KEY, ms); } catch (e) {}
  };
  $('#dash-bar .refresh button').on('click', function() { every(+$(this).data('every')); });
  var saved = 0;
  try { saved = +localStorage.getItem(KEY) || 0; } catch (e) {}
  every([0, 5000, 300000, 600000].indexOf(saved) >= 0 ? saved : 0);
  stamp();
})();
</script>
