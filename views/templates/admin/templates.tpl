<div class="panel mailsendvx-template-panel">
  <div class="panel-heading">
    <i class="icon-envelope"></i> {l s='Instant email templates' mod='mailsendvx'}
  </div>

  <form method="post" action="{$mailsendvx_configure_url|escape:'html':'UTF-8'}" class="form-horizontal">
    <input type="hidden" name="id_mailsendvx_template" value="{$mailsendvx_template_form.id_mailsendvx_template|intval}">

    <div class="form-group">
      <label class="control-label col-lg-3">{l s='Event' mod='mailsendvx'}</label>
      <div class="col-lg-5">
        <select name="event_name" class="form-control">
          {foreach from=$mailsendvx_events key=event_name item=event_label}
            <option value="{$event_name|escape:'html':'UTF-8'}"{if $mailsendvx_template_form.event_name == $event_name} selected="selected"{/if}>
              {$event_label|escape:'html':'UTF-8'} ({$event_name|escape:'html':'UTF-8'})
            </option>
          {/foreach}
        </select>
      </div>
    </div>

    <div class="form-group">
      <label class="control-label col-lg-3">{l s='Template name' mod='mailsendvx'}</label>
      <div class="col-lg-5">
        <input type="text" name="template_name" class="form-control" value="{$mailsendvx_template_form.name|escape:'html':'UTF-8'}">
      </div>
    </div>

    <div class="form-group">
      <label class="control-label col-lg-3">{l s='Subject' mod='mailsendvx'}</label>
      <div class="col-lg-5">
        <input type="text" name="subject" class="form-control" value="{$mailsendvx_template_form.subject|escape:'html':'UTF-8'}">
        <p class="help-block">{l s='You can use variables such as {customer_name}, {order_reference}, {order_status}, {shop_name}.' mod='mailsendvx'}</p>
      </div>
    </div>

    <div class="form-group">
      <label class="control-label col-lg-3">{l s='Mail wrapper' mod='mailsendvx'}</label>
      <div class="col-lg-5">
        <input type="text" name="mail_template" class="form-control" value="{$mailsendvx_template_form.mail_template|escape:'html':'UTF-8'}">
        <p class="help-block">{l s='Default value: mailsendvx_default.' mod='mailsendvx'}</p>
      </div>
    </div>

    <div class="form-group">
      <label class="control-label col-lg-3">{l s='Language' mod='mailsendvx'}</label>
      <div class="col-lg-3">
        <select name="id_lang" class="form-control">
          <option value="0"{if $mailsendvx_template_form.id_lang == 0} selected="selected"{/if}>{l s='All languages' mod='mailsendvx'}</option>
          {foreach from=$mailsendvx_languages item=language}
            <option value="{$language.id_lang|intval}"{if $mailsendvx_template_form.id_lang == $language.id_lang} selected="selected"{/if}>
              {$language.name|escape:'html':'UTF-8'}
            </option>
          {/foreach}
        </select>
      </div>
      <label class="control-label col-lg-1">{l s='Shop' mod='mailsendvx'}</label>
      <div class="col-lg-2">
        <input type="number" name="id_shop" class="form-control" value="{$mailsendvx_template_form.id_shop|intval}" min="0">
        <p class="help-block">{l s='Use 0 for all shops.' mod='mailsendvx'}</p>
      </div>
    </div>

    <div class="form-group">
      <label class="control-label col-lg-3">{l s='HTML content' mod='mailsendvx'}</label>
      <div class="col-lg-8">
        <textarea name="html_content" class="form-control mailsendvx-code-field" rows="8">{$mailsendvx_template_form.html_content|escape:'html':'UTF-8'}</textarea>
      </div>
    </div>

    <div class="form-group">
      <label class="control-label col-lg-3">{l s='Text content' mod='mailsendvx'}</label>
      <div class="col-lg-8">
        <textarea name="text_content" class="form-control mailsendvx-code-field" rows="5">{$mailsendvx_template_form.text_content|escape:'html':'UTF-8'}</textarea>
      </div>
    </div>

    <div class="form-group">
      <label class="control-label col-lg-3">{l s='Active' mod='mailsendvx'}</label>
      <div class="col-lg-8">
        <span class="switch prestashop-switch fixed-width-lg">
          <input type="radio" name="active" id="mailsendvx_template_active_on" value="1"{if $mailsendvx_template_form.active} checked="checked"{/if}>
          <label for="mailsendvx_template_active_on">{l s='Yes' mod='mailsendvx'}</label>
          <input type="radio" name="active" id="mailsendvx_template_active_off" value="0"{if !$mailsendvx_template_form.active} checked="checked"{/if}>
          <label for="mailsendvx_template_active_off">{l s='No' mod='mailsendvx'}</label>
          <a class="slide-button btn"></a>
        </span>
      </div>
    </div>

    <div class="panel-footer">
      <button type="submit" name="submitMailsendvxTemplate" class="btn btn-primary pull-right">
        <i class="process-icon-save"></i> {l s='Save template' mod='mailsendvx'}
      </button>
      {if $mailsendvx_template_form.id_mailsendvx_template}
        <a href="{$mailsendvx_configure_url|escape:'html':'UTF-8'}" class="btn btn-default">
          <i class="icon-plus"></i> {l s='New template' mod='mailsendvx'}
        </a>
      {/if}
    </div>
  </form>
</div>

{if $mailsendvx_preview}
  <div class="panel">
    <div class="panel-heading">
      <i class="icon-eye-open"></i> {l s='Preview' mod='mailsendvx'}: {$mailsendvx_preview.name|escape:'html':'UTF-8'}
    </div>
    <p><strong>{l s='Subject' mod='mailsendvx'}:</strong> {$mailsendvx_preview.subject|escape:'html':'UTF-8'}</p>
    <div class="mailsendvx-preview-frame">
      {$mailsendvx_preview.html nofilter}
    </div>
    <pre class="mailsendvx-text-preview">{$mailsendvx_preview.text|escape:'html':'UTF-8'}</pre>
  </div>
{/if}

<div class="panel">
  <div class="panel-heading">
    <i class="icon-list"></i> {l s='Templates' mod='mailsendvx'}
  </div>

  {if $mailsendvx_templates}
    <div class="table-responsive">
      <table class="table">
        <thead>
          <tr>
            <th>{l s='Name' mod='mailsendvx'}</th>
            <th>{l s='Event' mod='mailsendvx'}</th>
            <th>{l s='Subject' mod='mailsendvx'}</th>
            <th>{l s='Language' mod='mailsendvx'}</th>
            <th>{l s='Shop' mod='mailsendvx'}</th>
            <th>{l s='Active' mod='mailsendvx'}</th>
            <th>{l s='Actions' mod='mailsendvx'}</th>
          </tr>
        </thead>
        <tbody>
          {foreach from=$mailsendvx_templates item=template}
            <tr>
              <td>{$template.name|escape:'html':'UTF-8'}</td>
              <td><code>{$template.event_name|escape:'html':'UTF-8'}</code></td>
              <td>{$template.subject|escape:'html':'UTF-8'}</td>
              <td>{$template.id_lang|intval}</td>
              <td>{$template.id_shop|intval}</td>
              <td>
                {if $template.active}
                  <span class="label label-success">{l s='Yes' mod='mailsendvx'}</span>
                {else}
                  <span class="label label-default">{l s='No' mod='mailsendvx'}</span>
                {/if}
              </td>
              <td class="mailsendvx-template-actions">
                <a class="btn btn-default btn-sm" href="{$mailsendvx_configure_url|escape:'html':'UTF-8'}&amp;mailsendvx_edit_template={$template.id_mailsendvx_template|intval}">
                  <i class="icon-pencil"></i> {l s='Edit' mod='mailsendvx'}
                </a>
                <a class="btn btn-default btn-sm" href="{$mailsendvx_configure_url|escape:'html':'UTF-8'}&amp;mailsendvx_preview_template={$template.id_mailsendvx_template|intval}">
                  <i class="icon-eye-open"></i> {l s='Preview' mod='mailsendvx'}
                </a>
                <a class="btn btn-default btn-sm" href="{$mailsendvx_configure_url|escape:'html':'UTF-8'}&amp;mailsendvx_delete_template={$template.id_mailsendvx_template|intval}" onclick="return confirm('{l s='Delete this template?' mod='mailsendvx' js=1}');">
                  <i class="icon-trash"></i> {l s='Delete' mod='mailsendvx'}
                </a>
                <form method="post" action="{$mailsendvx_configure_url|escape:'html':'UTF-8'}" class="mailsendvx-test-form">
                  <input type="hidden" name="test_id_mailsendvx_template" value="{$template.id_mailsendvx_template|intval}">
                  <input type="email" name="test_email" class="form-control input-sm" placeholder="{l s='Test email' mod='mailsendvx'}">
                  <button type="submit" name="submitMailsendvxTest" class="btn btn-default btn-sm">
                    <i class="icon-send"></i> {l s='Send test' mod='mailsendvx'}
                  </button>
                </form>
              </td>
            </tr>
          {/foreach}
        </tbody>
      </table>
    </div>
  {else}
    <p class="text-muted">{l s='No templates yet.' mod='mailsendvx'}</p>
  {/if}
</div>
