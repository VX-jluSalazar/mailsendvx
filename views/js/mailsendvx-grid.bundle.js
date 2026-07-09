(function () {
  function initGrid(gridId, options) {
    if (!window.prestashop || !window.prestashop.component || !document.getElementById(gridId + '_grid')) {
      return;
    }

    var grid = new window.prestashop.component.Grid(gridId);
    var extensions = window.prestashop.component.GridExtensions || {};

    if (extensions.ReloadListExtension) {
      grid.addExtension(new extensions.ReloadListExtension());
    }
    if (extensions.SortingExtension) {
      grid.addExtension(new extensions.SortingExtension());
    }
    if (extensions.FiltersResetExtension) {
      grid.addExtension(new extensions.FiltersResetExtension());
    }
    if (extensions.SubmitRowActionExtension) {
      grid.addExtension(new extensions.SubmitRowActionExtension());
    }
    if (extensions.SubmitBulkActionExtension) {
      grid.addExtension(new extensions.SubmitBulkActionExtension());
    }
    if (extensions.BulkActionCheckboxExtension) {
      grid.addExtension(new extensions.BulkActionCheckboxExtension());
    }
    if (extensions.FiltersSubmitButtonEnablerExtension) {
      grid.addExtension(new extensions.FiltersSubmitButtonEnablerExtension());
    }
    if (options && options.position && extensions.PositionExtension) {
      grid.addExtension(new extensions.PositionExtension(grid));
    }
  }

  function initTemplatePreviewModal() {
    var modal = document.getElementById('mailsendvx-preview-modal');
    if (!modal) {
      return;
    }

    var title = document.getElementById('mailsendvx-preview-modal-title');
    var note = document.getElementById('mailsendvx-preview-note');
    var subject = document.getElementById('mailsendvx-preview-subject');
    var frame = document.getElementById('mailsendvx-preview-frame');
    var text = document.getElementById('mailsendvx-preview-text');
    var feedback = document.getElementById('mailsendvx-preview-feedback');
    var form = document.getElementById('mailsendvx-preview-test-form');
    var tokenField = form ? form.querySelector('input[name=\"_token\"]') : null;
    var previewField = form ? form.querySelector('input[name=\"preview\"]') : null;
    var submitButton = form ? form.querySelector('button[type=\"submit\"]') : null;

    if (!title || !note || !subject || !frame || !text || !feedback || !form || !tokenField || !previewField || !submitButton) {
      return;
    }

    function showFeedback(type, message) {
      feedback.className = 'alert mailsendvx-modal-feedback is-visible alert-' + type;
      feedback.textContent = message || '';
    }

    function hideFeedback() {
      feedback.className = 'alert alert-danger mailsendvx-modal-feedback';
      feedback.textContent = '';
    }

    function openModal() {
      modal.hidden = false;
      document.body.classList.add('modal-open');
    }

    function closeModal() {
      modal.hidden = true;
      title.textContent = '';
      note.textContent = '';
      subject.textContent = '';
      frame.innerHTML = '';
      text.textContent = '';
      hideFeedback();
      document.body.classList.remove('modal-open');
    }

    Array.prototype.forEach.call(modal.querySelectorAll('[data-preview-close]'), function (trigger) {
      trigger.addEventListener('click', closeModal);
    });

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && !modal.hidden) {
        closeModal();
      }
    });

    document.addEventListener('click', function (event) {
      var trigger = event.target.closest('.js-mailsendvx-template-preview');
      if (!trigger) {
        return;
      }

      event.preventDefault();
      event.stopPropagation();
      title.textContent = '';
      note.textContent = '';
      subject.textContent = '';
      frame.innerHTML = '';
      text.textContent = '';
      openModal();
      hideFeedback();
      showFeedback('info', 'Cargando previsualizacion...');

      window.fetch(trigger.href, {
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin'
      })
        .then(function (response) {
          return response.json().then(function (payload) {
            return {
              ok: response.ok,
              payload: payload
            };
          });
        })
        .then(function (result) {
          if (!result.ok || !result.payload.success) {
            throw new Error(result.payload.message || 'No se pudo previsualizar la plantilla.');
          }

          title.textContent = result.payload.preview.name || '';
          note.textContent = result.payload.context_message || '';
          subject.textContent = result.payload.preview.subject || '';
          frame.innerHTML = result.payload.preview.html || '';
          text.textContent = result.payload.preview.text || '';
          form.action = result.payload.test_url || '';
          tokenField.value = result.payload.test_token || '';
          previewField.value = result.payload.preview.id_mailsendvx_template || 0;
          hideFeedback();
        })
        .catch(function (error) {
          showFeedback('danger', error.message || 'No se pudo previsualizar la plantilla.');
        });
    });

    form.addEventListener('submit', function (event) {
      event.preventDefault();
      hideFeedback();
      submitButton.disabled = true;

      window.fetch(form.action, {
        method: 'POST',
        body: new window.FormData(form),
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin'
      })
        .then(function (response) {
          return response.json().then(function (payload) {
            return {
              ok: response.ok,
              payload: payload
            };
          });
        })
        .then(function (result) {
          if (!result.ok || !result.payload.success) {
            throw new Error(result.payload.message || 'No se pudo enviar el correo de prueba.');
          }

          showFeedback('success', result.payload.message || 'Correo de prueba enviado.');
        })
        .catch(function (error) {
          showFeedback('danger', error.message || 'No se pudo enviar el correo de prueba.');
        })
        .finally(function () {
          submitButton.disabled = false;
        });
    });
  }

  function initDashboardContent() {
    initGrid('mailsendvx_events');
    initGrid('mailsendvx_logs');
    initGrid('mailsendvx_queue');
    initTemplatePreviewModal();
  }

  function setActiveDashboardTab(tabName) {
    var tabs = document.querySelectorAll('[data-dashboard-tab]');
    var panels = document.querySelector('[data-dashboard-panels]');

    Array.prototype.forEach.call(tabs, function (tab) {
      tab.classList.toggle('is-active', tab.getAttribute('data-dashboard-tab') === tabName);
    });

    if (panels) {
      panels.setAttribute('data-active-tab', tabName);
    }
  }

  function initDashboardTabs() {
    var tabsRoot = document.querySelector('[data-dashboard-tabs]');
    var panelsRoot = document.querySelector('[data-dashboard-panels]');

    if (!tabsRoot || !panelsRoot || tabsRoot.getAttribute('data-dashboard-tabs-ready') === '1') {
      return;
    }

    tabsRoot.setAttribute('data-dashboard-tabs-ready', '1');

    tabsRoot.addEventListener('click', function (event) {
      var trigger = event.target.closest('[data-dashboard-tab]');
      var currentTab = panelsRoot.getAttribute('data-active-tab');
      var targetTab;
      var url;

      if (!trigger) {
        return;
      }

      targetTab = trigger.getAttribute('data-dashboard-tab');
      if (!targetTab || targetTab === currentTab) {
        event.preventDefault();
        return;
      }

      event.preventDefault();
      url = new window.URL(trigger.href, window.location.origin);
      url.searchParams.set('ajax_tab', '1');

      tabsRoot.classList.add('is-loading');
      panelsRoot.classList.add('is-loading');

      window.fetch(url.toString(), {
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin'
      })
        .then(function (response) {
          return response.json().then(function (payload) {
            return {
              ok: response.ok,
              payload: payload
            };
          });
        })
        .then(function (result) {
          if (!result.ok || !result.payload.html) {
            throw new Error('No se pudo cargar la tab del dashboard.');
          }

          panelsRoot.innerHTML = result.payload.html;
          setActiveDashboardTab(result.payload.activeTab || targetTab);
          initDashboardContent();
        })
        .catch(function () {
          window.location.href = trigger.href;
        })
        .finally(function () {
          tabsRoot.classList.remove('is-loading');
          panelsRoot.classList.remove('is-loading');
        });
    });
  }

  window.mailsendvxAdmin = window.mailsendvxAdmin || {};
  window.mailsendvxAdmin.initDashboardContent = initDashboardContent;
  window.mailsendvxAdmin.initDashboardTabs = initDashboardTabs;

  document.addEventListener('DOMContentLoaded', function () {
    initDashboardContent();
    initGrid('mailsendvx_templates');
    initDashboardTabs();
  });
})();
