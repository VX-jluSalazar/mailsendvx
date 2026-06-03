<div class="panel mailsendvx-status-panel">
  <div class="panel-heading">
    <i class="icon-dashboard"></i> {l s='Base status' mod='mailsendvx'}
  </div>
  <div class="row">
    <div class="col-lg-4">
      <div class="mailsendvx-stat">
        <span class="mailsendvx-stat-value">{$mailsendvx_templates_count|intval}</span>
        <span class="mailsendvx-stat-label">{l s='Templates' mod='mailsendvx'}</span>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="mailsendvx-stat">
        <span class="mailsendvx-stat-value">{$mailsendvx_scheduled_count|intval}</span>
        <span class="mailsendvx-stat-label">{l s='Scheduled emails' mod='mailsendvx'}</span>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="mailsendvx-stat">
        <span class="mailsendvx-stat-value">{$mailsendvx_cron_token|escape:'html':'UTF-8'|truncate:8:''}</span>
        <span class="mailsendvx-stat-label">{l s='Cron token prefix' mod='mailsendvx'}</span>
      </div>
    </div>
  </div>

  <div class="mailsendvx-actions">
    <a class="btn btn-default" href="{$mailsendvx_dashboard_url|escape:'html':'UTF-8'}">
      <i class="icon-list"></i> {l s='Open dashboard' mod='mailsendvx'}
    </a>
  </div>

  <h4>{l s='Recent logs' mod='mailsendvx'}</h4>
  {if $mailsendvx_recent_logs}
    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>{l s='Date' mod='mailsendvx'}</th>
            <th>{l s='Event' mod='mailsendvx'}</th>
            <th>{l s='Status' mod='mailsendvx'}</th>
            <th>{l s='Recipient' mod='mailsendvx'}</th>
            <th>{l s='Message' mod='mailsendvx'}</th>
          </tr>
        </thead>
        <tbody>
          {foreach from=$mailsendvx_recent_logs item=log}
            <tr>
              <td>{$log.date_add|escape:'html':'UTF-8'}</td>
              <td>{$log.event_name|escape:'html':'UTF-8'}</td>
              <td><span class="label label-default">{$log.status|escape:'html':'UTF-8'}</span></td>
              <td>{$log.recipient|escape:'html':'UTF-8'}</td>
              <td>{$log.message|escape:'html':'UTF-8'}</td>
            </tr>
          {/foreach}
        </tbody>
      </table>
    </div>
  {else}
    <p class="text-muted">{l s='No logs yet.' mod='mailsendvx'}</p>
  {/if}
</div>

