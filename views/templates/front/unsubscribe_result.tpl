<section class="mailsendvx-unsubscribe-shell">
  <div class="mailsendvx-unsubscribe-card">
    <h1>{if $mailsendvx_unsubscribe_success}Desuscripcion confirmada{else}No pudimos completar la desuscripcion{/if}</h1>
    <p>{$mailsendvx_unsubscribe_message|escape:'htmlall':'UTF-8'}</p>
    <p><a href="{$mailsendvx_shop_url|escape:'htmlall':'UTF-8'}">Volver a {$mailsendvx_shop_name|escape:'htmlall':'UTF-8'}</a></p>
  </div>
</section>
