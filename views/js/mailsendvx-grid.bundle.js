(function () {
  function normalizeQueryString(query) {
    if (!query) {
      return '';
    }

    return query.charAt(0) === '?' ? query : ('?' + query);
  }

  function sanitizeQueryString(query) {
    var normalized = normalizeQueryString(query);
    var params;
    var sanitized;

    if (!normalized) {
      return '';
    }

    params = new window.URLSearchParams(normalized);
    sanitized = new window.URLSearchParams();

    params.forEach(function (value, key) {
      if (value === null || value === '') {
        return;
      }

      if (/\[_token\]$/.test(key)) {
        return;
      }

      if (/\[actions\]\[search\]$/.test(key)) {
        return;
      }

      sanitized.append(key, value);
    });

    return sanitized.toString() ? ('?' + sanitized.toString()) : '';
  }

  function getStateNamespace() {
    return 'mailsendvx-admin:' + window.location.pathname;
  }

  function loadUiState() {
    try {
      return JSON.parse(window.sessionStorage.getItem(getStateNamespace()) || '{}');
    } catch (error) {
      return {};
    }
  }

  function saveUiState(state) {
    try {
      window.sessionStorage.setItem(getStateNamespace(), JSON.stringify(state));
    } catch (error) {
      // Ignore storage errors; AJAX navigation should still work.
    }
  }

  function getSavedGridQuery(gridId) {
    var state = loadUiState();

    if (!state.grids || !gridId) {
      return '';
    }

    return sanitizeQueryString(state.grids[gridId] || '');
  }

  function setSavedGridQuery(gridId, query) {
    var state = loadUiState();

    if (!gridId) {
      return;
    }

    state.grids = state.grids || {};
    state.grids[gridId] = sanitizeQueryString(query);
    saveUiState(state);
  }

  function getSavedActiveTab() {
    var state = loadUiState();

    return state.activeTab || '';
  }

  function setSavedActiveTab(tabName) {
    var state = loadUiState();

    state.activeTab = tabName || '';
    saveUiState(state);
  }

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

  function parseJsonResponse(response) {
    return response.text().then(function (text) {
      var payload = {};

      if (text) {
        try {
          payload = JSON.parse(text);
        } catch (error) {
          payload = {};
        }
      }

      return {
        ok: response.ok,
        status: response.status,
        payload: payload
      };
    });
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

  function getShellGridId(shell) {
    return shell ? shell.getAttribute('data-grid-id') || '' : '';
  }

  function getShellQuery(shell) {
    if (!shell) {
      return window.location.search || '';
    }

    return sanitizeQueryString(shell.getAttribute('data-grid-current-query') || window.location.search || '');
  }

  function setShellQuery(shell, query) {
    var normalizedQuery = sanitizeQueryString(query);

    if (!shell) {
      return;
    }

    shell.setAttribute('data-grid-current-query', normalizedQuery);
    setSavedGridQuery(getShellGridId(shell), normalizedQuery);
  }

  function ensureGridFeedback(shell) {
    var feedback = shell.querySelector('.mailsendvx-grid-feedback');

    if (!feedback) {
      feedback = document.createElement('div');
      feedback.className = 'alert mailsendvx-grid-feedback';
      feedback.setAttribute('role', 'alert');
      feedback.hidden = true;
      shell.insertBefore(feedback, shell.firstChild);
    }

    return feedback;
  }

  function hideGridFeedback(shell) {
    var feedback = shell ? shell.querySelector('.mailsendvx-grid-feedback') : null;

    if (!feedback) {
      return;
    }

    feedback.hidden = true;
    feedback.textContent = '';
    feedback.className = 'alert mailsendvx-grid-feedback';
  }

  function showGridFeedback(shell, type, message) {
    var feedback;

    if (!shell || !message) {
      return;
    }

    feedback = ensureGridFeedback(shell);
    feedback.hidden = false;
    feedback.textContent = message;
    feedback.className = 'alert mailsendvx-grid-feedback alert-' + type;
  }

  function setGridLoading(shell, isLoading) {
    if (!shell) {
      return;
    }

    shell.classList.toggle('is-loading', !!isLoading);
    shell.setAttribute('aria-busy', isLoading ? 'true' : 'false');
  }

  function buildGridEndpoint(shell, queryString) {
    var endpoint = shell ? shell.getAttribute('data-grid-panel-endpoint') : '';
    var url;
    var params;

    if (!endpoint) {
      return '';
    }

    url = new window.URL(endpoint, window.location.origin);
    params = new window.URLSearchParams(queryString || '');

    params.forEach(function (value, key) {
      url.searchParams.set(key, value);
    });

    return url.toString();
  }

  function isGridStateLink(trigger) {
    var href = trigger && trigger.getAttribute('href');
    var url;

    if (!href || href === '#' || (trigger.getAttribute('target') || '') === '_blank') {
      return false;
    }

    if (trigger.classList.contains('js-mailsendvx-template-preview')) {
      return false;
    }

    if (trigger.closest('td.column-actions, td.actions, .grid-actions-column, .action-type-column')) {
      return false;
    }

    url = new window.URL(href, window.location.origin);

    return (
      url.origin === window.location.origin
      && (
        url.searchParams.has('orderBy')
        || url.searchParams.has('sortOrder')
        || url.searchParams.has('offset')
        || url.searchParams.has('limit')
        || /filters/i.test(url.search)
        || trigger.classList.contains('js-common-refresh-list')
        || trigger.classList.contains('grid-reset-button')
      )
    );
  }

  function isGridFilterForm(form, gridId) {
    var formData;
    var matches = false;

    if (!form || !gridId) {
      return false;
    }

    formData = new window.FormData(form);
    formData.forEach(function (value, key) {
      if (key === gridId || key.indexOf(gridId + '[') === 0) {
        matches = true;
      }
    });

    return matches;
  }

  function buildGridFilterQuery(gridId, form) {
    var formData = new window.FormData(form);
    var params = new window.URLSearchParams();
    var prefix = gridId + '[';
    var currentQuery = new window.URLSearchParams(getShellQuery(form.closest('[data-mailsendvx-ajax-grid="1"]')));

    currentQuery.forEach(function (value, key) {
      if (key === '_token') {
        params.set(key, value);
        return;
      }

      if (key.indexOf(prefix) !== 0) {
        return;
      }

      if (/\[orderBy\]$/.test(key) || /\[sortOrder\]$/.test(key) || /\[limit\]$/.test(key)) {
        params.set(key, value);
      }
    });

    params.set(gridId + '[offset]', '0');

    formData.forEach(function (value, key) {
      var suffix;
      var targetKey;

      if (!key || key.indexOf(prefix) !== 0 || value === null || value === '') {
        return;
      }

      if (/\[_token\]$/.test(key) || /\[actions\]\[search\]$/.test(key)) {
        return;
      }

      suffix = key.substring(prefix.length, key.length - 1);
      targetKey = gridId + '[filters][' + suffix + ']';
      params.set(targetKey, value);
    });

    return params.toString() ? ('?' + params.toString()) : '';
  }

  function shouldHandleActionForm(shell, form, gridId) {
    if (!shell || shell.getAttribute('data-grid-ajax-actions') !== '1' || !form) {
      return false;
    }

    if ((form.getAttribute('method') || 'GET').toUpperCase() !== 'POST') {
      return false;
    }

    return !isGridFilterForm(form, gridId);
  }

  function fetchGrid(shell, queryString, fallbackUrl) {
    var endpoint = buildGridEndpoint(shell, queryString);

    if (!shell || !endpoint) {
      if (fallbackUrl) {
        window.location.href = fallbackUrl;
      }

      return Promise.resolve();
    }

    setGridLoading(shell, true);
    hideGridFeedback(shell);

    return window.fetch(endpoint, {
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      credentials: 'same-origin'
    })
      .then(parseJsonResponse)
      .then(function (result) {
        if (!result.ok || !result.payload.success || !result.payload.html) {
          throw new Error(result.payload.message || 'No se pudo actualizar la tabla.');
        }

        shell.innerHTML = result.payload.html;
        setShellQuery(shell, queryString || '');
        initGrid(getShellGridId(shell));
        initAjaxGridShell(shell);

        if (result.payload.message) {
          showGridFeedback(shell, 'success', result.payload.message);
        }

        if (fallbackUrl) {
          window.history.replaceState({}, '', fallbackUrl);
        }
      })
      .catch(function (error) {
        if (fallbackUrl) {
          window.location.href = fallbackUrl;
          return;
        }

        showGridFeedback(shell, 'danger', error.message || 'No se pudo actualizar la tabla.');
      })
      .finally(function () {
        setGridLoading(shell, false);
      });
  }

  function submitGridAction(shell, form) {
    var queryString = getShellQuery(shell);

    setGridLoading(shell, true);
    hideGridFeedback(shell);

    return window.fetch(form.action, {
      method: 'POST',
      body: new window.FormData(form),
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      credentials: 'same-origin'
    })
      .then(parseJsonResponse)
      .then(function (result) {
        if (!result.ok || !result.payload.success) {
          throw new Error(result.payload.message || 'No se pudo completar la acción.');
        }

        return fetchGrid(shell, queryString).then(function () {
          if (result.payload.message) {
            showGridFeedback(shell, 'success', result.payload.message);
          }
        });
      })
      .catch(function (error) {
        showGridFeedback(shell, 'danger', error.message || 'No se pudo completar la acción.');
      })
      .finally(function () {
        setGridLoading(shell, false);
      });
  }

  function handleAjaxForm(form) {
    var gridId = form.getAttribute('data-refresh-grid-id') || '';
    var shell = gridId ? document.querySelector('[data-mailsendvx-ajax-grid="1"][data-grid-id="' + gridId + '"]') : null;
    var confirmMessage = form.getAttribute('data-confirm-message') || '';
    var submitButton = form.querySelector('button[type="submit"]');

    if (confirmMessage && !window.confirm(confirmMessage)) {
      return;
    }

    if (submitButton) {
      submitButton.disabled = true;
    }

    if (shell) {
      setGridLoading(shell, true);
      hideGridFeedback(shell);
    }

    window.fetch(form.action, {
      method: (form.getAttribute('method') || 'POST').toUpperCase(),
      body: new window.FormData(form),
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      credentials: 'same-origin'
    })
      .then(parseJsonResponse)
      .then(function (result) {
        if (!result.ok || !result.payload.success) {
          throw new Error(result.payload.message || 'No se pudo completar la acción.');
        }

        if (!shell) {
          return;
        }

        return fetchGrid(shell, getShellQuery(shell)).then(function () {
          if (result.payload.message) {
            showGridFeedback(shell, 'success', result.payload.message);
          }
        });
      })
      .catch(function (error) {
        if (shell) {
          showGridFeedback(shell, 'danger', error.message || 'No se pudo completar la acción.');
        }
      })
      .finally(function () {
        if (submitButton) {
          submitButton.disabled = false;
        }

        if (shell) {
          setGridLoading(shell, false);
        }
      });
  }

  function initAjaxGridShell(shell) {
    var gridId;

    if (!shell || shell.getAttribute('data-mailsendvx-ajax-grid-ready') === '1') {
      return;
    }

    gridId = getShellGridId(shell);
    setShellQuery(shell, getShellQuery(shell));
    shell.setAttribute('data-mailsendvx-ajax-grid-ready', '1');

    shell.addEventListener('click', function (event) {
      var trigger = event.target.closest('a[href]');
      var url;

      if (!trigger || !shell.contains(trigger) || !isGridStateLink(trigger)) {
        return;
      }

      event.preventDefault();
      url = new window.URL(trigger.href, window.location.origin);
      fetchGrid(shell, url.search, trigger.href);
    });

    shell.addEventListener('submit', function (event) {
      var form = event.target;
      var formData;
      var params;

      if (!form || form.tagName !== 'FORM' || !shell.contains(form)) {
        return;
      }

      if (isGridFilterForm(form, gridId)) {
        event.preventDefault();
        params = buildGridFilterQuery(gridId, form);
        fetchGrid(shell, params, window.location.pathname + params);
        return;
      }

      if (shouldHandleActionForm(shell, form, gridId)) {
        event.preventDefault();
        submitGridAction(shell, form);
      }
    });
  }

  function initAjaxGrids() {
    Array.prototype.forEach.call(document.querySelectorAll('[data-mailsendvx-ajax-grid="1"]'), function (shell) {
      var savedQuery = getSavedGridQuery(getShellGridId(shell));

      initAjaxGridShell(shell);
      initGrid(getShellGridId(shell));

      if (savedQuery && savedQuery !== getShellQuery(shell)) {
        fetchGrid(shell, savedQuery);
      }
    });
  }

  function initAjaxForms() {
    Array.prototype.forEach.call(document.querySelectorAll('[data-mailsendvx-ajax-form="1"]'), function (form) {
      if (form.getAttribute('data-mailsendvx-ajax-form-ready') === '1') {
        return;
      }

      form.setAttribute('data-mailsendvx-ajax-form-ready', '1');
      form.addEventListener('submit', function (event) {
        event.preventDefault();
        handleAjaxForm(form);
      });
    });
  }

  function initTemplatePreviewModal() {
    var modal = document.getElementById('mailsendvx-preview-modal');
    if (!modal || modal.getAttribute('data-preview-ready') === '1') {
      return;
    }

    modal.setAttribute('data-preview-ready', '1');

    var title = document.getElementById('mailsendvx-preview-modal-title');
    var note = document.getElementById('mailsendvx-preview-note');
    var subject = document.getElementById('mailsendvx-preview-subject');
    var frame = document.getElementById('mailsendvx-preview-frame');
    var text = document.getElementById('mailsendvx-preview-text');
    var feedback = document.getElementById('mailsendvx-preview-feedback');
    var form = document.getElementById('mailsendvx-preview-test-form');
    var tokenField = form ? form.querySelector('input[name="_token"]') : null;
    var previewField = form ? form.querySelector('input[name="preview"]') : null;
    var submitButton = form ? form.querySelector('button[type="submit"]') : null;

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
        .then(parseJsonResponse)
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
        .then(parseJsonResponse)
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

  function initGridDetailModal() {
    var modal = document.getElementById('mailsendvx-detail-modal');
    var eyebrow;
    var title;
    var summary;
    var content;
    var feedback;

    if (!modal || modal.getAttribute('data-detail-ready') === '1') {
      return;
    }

    eyebrow = document.getElementById('mailsendvx-detail-modal-eyebrow');
    title = document.getElementById('mailsendvx-detail-modal-title');
    summary = document.getElementById('mailsendvx-detail-modal-summary');
    content = document.getElementById('mailsendvx-detail-content');
    feedback = document.getElementById('mailsendvx-detail-feedback');

    if (!eyebrow || !title || !summary || !content || !feedback) {
      return;
    }

    modal.setAttribute('data-detail-ready', '1');

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
      eyebrow.textContent = 'Detalle';
      title.textContent = '';
      summary.textContent = '';
      content.innerHTML = '';
      hideFeedback();
      document.body.classList.remove('modal-open');
    }

    function loadDetail(url) {
      openModal();
      hideFeedback();
      title.textContent = '';
      summary.textContent = '';
      content.innerHTML = '';
      showFeedback('info', 'Cargando detalle...');

      window.fetch(url, {
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin'
      })
        .then(parseJsonResponse)
        .then(function (result) {
          if (!result.ok || !result.payload.success || !result.payload.detail) {
            throw new Error(result.payload.message || 'No se pudo cargar el detalle.');
          }

          eyebrow.textContent = result.payload.detail.eyebrow || 'Detalle';
          title.textContent = result.payload.detail.title || '';
          summary.textContent = result.payload.detail.summary || '';
          content.innerHTML = result.payload.html || '';
          hideFeedback();
        })
        .catch(function (error) {
          showFeedback('danger', error.message || 'No se pudo cargar el detalle.');
        });
    }

    Array.prototype.forEach.call(modal.querySelectorAll('[data-detail-close]'), function (trigger) {
      trigger.addEventListener('click', closeModal);
    });

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && !modal.hidden) {
        closeModal();
      }
    });

    document.addEventListener('click', function (event) {
      var trigger = event.target.closest('.js-mailsendvx-grid-detail');
      if (trigger) {
        event.preventDefault();
        event.stopPropagation();
        loadDetail(trigger.href);
        return;
      }

      var row = event.target.closest('[data-mailsendvx-ajax-grid="1"] tbody tr');
      var interactive;
      var rowTrigger;

      if (!row) {
        return;
      }

      interactive = event.target.closest('a,button,input,label,select,textarea,.dropdown-toggle,.dropdown-menu');
      if (interactive) {
        return;
      }

      rowTrigger = row.querySelector('.js-mailsendvx-grid-detail');
      if (!rowTrigger) {
        return;
      }

      event.preventDefault();
      loadDetail(rowTrigger.href);
    });
  }

  function initDashboardContent() {
    initAjaxGrids();
    initAjaxForms();
    initTemplatePreviewModal();
    initGridDetailModal();
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

    setSavedActiveTab(tabName);
  }

  function initDashboardTabs() {
    var tabsRoot = document.querySelector('[data-dashboard-tabs]');
    var panelsRoot = document.querySelector('[data-dashboard-panels]');
    var savedTab;
    var initialTrigger;

    if (!tabsRoot || !panelsRoot || tabsRoot.getAttribute('data-dashboard-tabs-ready') === '1') {
      return;
    }

    tabsRoot.setAttribute('data-dashboard-tabs-ready', '1');
    savedTab = getSavedActiveTab();

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
        .then(parseJsonResponse)
        .then(function (result) {
          if (!result.ok || !result.payload.html) {
            throw new Error('No se pudo cargar la tab del dashboard.');
          }

          panelsRoot.innerHTML = result.payload.html;
          setActiveDashboardTab(result.payload.activeTab || targetTab);
          window.history.replaceState({}, '', trigger.href);
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

    if (!window.location.search && savedTab && savedTab !== panelsRoot.getAttribute('data-active-tab')) {
      initialTrigger = tabsRoot.querySelector('[data-dashboard-tab="' + savedTab + '"]');
      if (initialTrigger) {
        initialTrigger.click();
      }
    }
  }

  window.mailsendvxAdmin = window.mailsendvxAdmin || {};
  window.mailsendvxAdmin.initDashboardContent = initDashboardContent;
  window.mailsendvxAdmin.initDashboardTabs = initDashboardTabs;
  window.mailsendvxAdmin.refreshGrid = function (gridId) {
    var shell = document.querySelector('[data-mailsendvx-ajax-grid="1"][data-grid-id="' + gridId + '"]');

    if (!shell) {
      return;
    }

    fetchGrid(shell, getShellQuery(shell));
  };

  document.addEventListener('DOMContentLoaded', function () {
    initDashboardContent();
    initDashboardTabs();
  });
})();
