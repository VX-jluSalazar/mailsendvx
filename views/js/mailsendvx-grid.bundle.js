(function () {
  function getGridRoot(gridId) {
    return document.getElementById(gridId + '_grid')
      || document.getElementById(gridId)
      || document.querySelector('[data-grid-id="' + gridId + '"]');
  }

  function getGridExtension(extensions, names) {
    var index;

    for (index = 0; index < names.length; index += 1) {
      if (extensions[names[index]]) {
        return extensions[names[index]];
      }
    }

    return null;
  }

  function initGrid(gridId, options) {
    var root = getGridRoot(gridId);
    var grid;
    var extensions;
    var Extension;

    if (!window.prestashop || !window.prestashop.component || !window.prestashop.component.Grid || !root) {
      return;
    }

    if (root.getAttribute('data-mailsendvx-grid-ready') === '1') {
      return;
    }

    root.setAttribute('data-mailsendvx-grid-ready', '1');
    grid = new window.prestashop.component.Grid(gridId);
    extensions = window.prestashop.component.GridExtensions || {};

    Extension = getGridExtension(extensions, ['ReloadListExtension']);
    if (Extension) {
      grid.addExtension(new Extension());
    }

    Extension = getGridExtension(extensions, ['SortingExtension', 'ColumnSortingExtension']);
    if (Extension) {
      grid.addExtension(new Extension());
    }

    Extension = getGridExtension(extensions, ['FiltersResetExtension', 'ResetFiltersExtension']);
    if (Extension) {
      grid.addExtension(new Extension());
    }

    Extension = getGridExtension(extensions, ['SubmitRowActionExtension']);
    if (Extension) {
      grid.addExtension(new Extension());
    }

    Extension = getGridExtension(extensions, ['SubmitBulkActionExtension']);
    if (Extension) {
      grid.addExtension(new Extension());
    }

    Extension = getGridExtension(extensions, ['BulkActionCheckboxExtension', 'BulkActionExtension', 'BulkActionsExtension']);
    if (Extension) {
      grid.addExtension(new Extension());
    }

    Extension = getGridExtension(extensions, ['FiltersSubmitButtonEnablerExtension']);
    if (Extension) {
      grid.addExtension(new Extension());
    }

    Extension = getGridExtension(extensions, ['PositionExtension']);
    if (options && options.position && Extension) {
      grid.addExtension(new Extension(grid));
    }

    if (typeof grid.init === 'function') {
      grid.init();
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
