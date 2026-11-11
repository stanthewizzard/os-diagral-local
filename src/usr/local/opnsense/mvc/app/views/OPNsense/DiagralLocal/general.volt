<script>
$(function() {
 mapDataToFormUI({'frm_general_settings':'/api/diagrallocal/settings/get'}).done(function(){formatTokenizersUI();$('.selectpicker').selectpicker('refresh');});
 function refresh(){ajaxGet('/api/diagrallocal/service/status',{},function(d){$('#status').text(JSON.stringify(d,null,2));});}
 $('#save').click(function(){saveFormToEndpoint('/api/diagrallocal/settings/set','frm_general_settings',refresh);});
 $('.route').click(function(){var mode=$(this).data('mode');$('.route').prop('disabled',true);ajaxCall('/api/diagrallocal/service/'+mode,{},function(d){$('#status').text(JSON.stringify(d,null,2));$('.route').prop('disabled',false);});});
 refresh();setInterval(refresh,10000);
});
</script>
<div class="alert alert-info">Manual TCP 6000 routing only. No alarm commands, automatic fallback or DNS changes. Saving settings does not change the active route. Select Cloud before changing an active configuration, then save and select Local.</div>
{{ partial("layout_partials/base_form",['fields':generalForm,'id':'frm_general_settings']) }}
<div class="col-md-12"><button class="btn btn-primary" id="save" type="button">Save settings</button>
<button class="btn btn-default route" data-mode="local" type="button">Local</button>
<button class="btn btn-default route" data-mode="cloud" type="button">Cloud</button>
<h3>Confirmed routing</h3><pre id="status"></pre></div>
