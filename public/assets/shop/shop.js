'use strict';
document.querySelectorAll('form[data-paypal-return], form[data-provider-return]').forEach(form => form.requestSubmit());
function syncSidebar() {
  const sidebar = document.querySelector('.sidebar');
  if (sidebar) sidebar.inert = window.matchMedia('(max-width: 760px)').matches && !document.body.classList.contains('menu-open');
}
syncSidebar();
window.matchMedia('(max-width: 760px)').addEventListener('change', syncSidebar);
function closeMenu() {
  document.body.classList.remove('menu-open');
  document.querySelectorAll('[data-menu-toggle]').forEach(b => { b.setAttribute('aria-expanded','false'); b.setAttribute('aria-label','Menü öffnen'); });
  syncSidebar();
}
document.querySelectorAll('[data-menu-close]').forEach(button => button.addEventListener('click', () => {
  closeMenu();
  document.querySelector('[data-menu-toggle]')?.focus();
}));
document.querySelectorAll('[data-menu-toggle]').forEach(button => {
  button.addEventListener('click', () => {
    const open = document.body.classList.toggle('menu-open');
    button.setAttribute('aria-expanded', String(open));
    button.setAttribute('aria-label', open ? 'Menü schließen' : 'Menü öffnen');
    syncSidebar();
  });
});
document.addEventListener('keydown', event => {
  if (event.key === 'Escape') {
    closeMenu();
  }
});
document.querySelectorAll('#navigation a').forEach(link => link.addEventListener('click', () => {
  closeMenu();
}));
// Each portal navigation opens its current section and closes other groups.
document.querySelectorAll('.sidebar nav').forEach(nav => {
  const groups = nav.querySelectorAll('[data-admin-nav-group], [data-customer-nav-group]');
  groups.forEach(group => {
    group.open = group.classList.contains('has-active');
    group.addEventListener('toggle', () => {
      if (group.open) groups.forEach(other => {
        if (other !== group) other.open = false;
      });
    });
  });
});
document.querySelectorAll('[data-password-toggle]').forEach(button => {
  button.addEventListener('click', () => {
    const input = button.parentElement.querySelector('input');
    const visible = input.type === 'password';
    input.type = visible ? 'text' : 'password';
    button.setAttribute('aria-pressed', String(visible));
    button.setAttribute('aria-label', visible ? 'Passwort verbergen' : 'Passwort anzeigen');
  });
});
const catalog = document.querySelector('[data-catalog]');
if (catalog) {
  let category = catalog.dataset.initialFilter || '';
  const search = catalog.querySelector('[data-product-search]');
  const cards = [...catalog.querySelectorAll('[data-product]')];
  function update() {
    const needle = (search.value || '').trim().toLocaleLowerCase('de');
    let count = 0;
    cards.forEach(card => {
      card.hidden = Boolean((category && card.dataset.category !== category) || (needle && !card.dataset.name.includes(needle)));
      if (!card.hidden) count++;
    });
    catalog.querySelector('[data-no-products]').hidden = count > 0;
    catalog.querySelectorAll('[data-filter]').forEach(b => b.setAttribute('aria-pressed', String(b.dataset.filter === category)));
  }
  search.addEventListener('input', update);
  catalog.querySelectorAll('[data-filter]').forEach(button => button.addEventListener('click', () => {
    category = button.dataset.filter;
    const url = new URL(window.location.href);
    if (category) url.searchParams.set('kategorie', category); else url.searchParams.delete('kategorie');
    history.replaceState(null, '', url);
    update();
  }));
  update();
}
document.querySelectorAll('form[data-confirm]').forEach(form => form.addEventListener('submit', event => {
  if (!window.confirm(form.dataset.confirm)) event.preventDefault();
}));
document.querySelectorAll('form[method="post"]').forEach(form => form.addEventListener('submit', event => {
  if (event.defaultPrevented || !form.checkValidity() || form.hasAttribute('data-download')) return;
  const button = event.submitter;
  if (button) window.setTimeout(() => { button.disabled = true; }, 0);
}));

const proxmoxAuthSelect = document.querySelector('select[name="auth_mode"]');
if (proxmoxAuthSelect) {
  const syncProxmoxAuth = () => {
    proxmoxAuthSelect.closest('form').querySelectorAll('[data-proxmox-auth]').forEach(field => {
      field.hidden = field.dataset.proxmoxAuth !== proxmoxAuthSelect.value;
    });
  };
  proxmoxAuthSelect.addEventListener('change', syncProxmoxAuth);
  syncProxmoxAuth();
}

document.querySelectorAll('[data-proxmox-settings]').forEach(form => {
  const reload = form.querySelector('[data-proxmox-reload]');
  const status = form.querySelector('[data-proxmox-target-status]');
  let connectionChanged = false;
  let message = status.textContent;
  const showStatus = value => {
    message = value;
    status.textContent = value + (connectionChanged ? ' Geänderte Zugangsdaten zuerst speichern; die Listen verwenden den gespeicherten Zugang.' : '');
  };
  ['auth_mode', 'url', 'username', 'password', 'token_id', 'token_secret', 'ca_pem'].forEach(name => {
    form.querySelector(`[name="${name}"]`)?.addEventListener('input', () => {
      connectionChanged = true;
      showStatus(message);
    });
  });
  const replaceOptions = (name, options) => {
    const select = form.querySelector(`[data-proxmox-target="${name}"]`);
    const selected = select.value;
    const items = options.map(option => new Option(option.label, option.value));
    if (selected && !options.some(option => option.value === selected)) items.push(new Option(selected + ' (gespeichert/ausgewählt – aktuell nicht sichtbar)', selected));
    select.replaceChildren(new Option('Bitte auswählen', ''), ...items);
    select.value = selected;
  };
  const loadTargets = async () => {
    if (form.dataset.proxmoxConfigured !== '1' || reload.disabled) return;
    reload.disabled = true;
    form.setAttribute('aria-busy', 'true');
    showStatus('Nodes und Ressourcenpools werden aus Proxmox geladen …');
    const controller = new AbortController();
    const timeout = window.setTimeout(() => controller.abort(), 65000);
    try {
      const body = new URLSearchParams({action:'proxmox_targets',csrf_token:form.querySelector('[name="csrf_token"]').value});
      const response = await fetch(window.location.pathname, {method:'POST',body,credentials:'same-origin',headers:{Accept:'application/json'},signal:controller.signal});
      if (!response.headers.get('content-type')?.includes('application/json')) throw new Error('Bitte die Seite neu laden und gegebenenfalls erneut anmelden.');
      const result = await response.json();
      if (!response.ok || result.status !== 'ok') throw new Error(result.message || 'Die Proxmox-Auswahl konnte nicht geladen werden.');
      for (const key of ['nodes', 'pools']) {
        if (!Array.isArray(result[key]) || !result[key].every(option => typeof option.value === 'string' && typeof option.label === 'string')) throw new Error('Proxmox hat keine gültige Auswahlliste geliefert.');
      }
      const errors = result.errors || {};
      if (!errors.nodes) replaceOptions('node', result.nodes);
      if (!errors.pools) replaceOptions('pool', result.pools);
      const details = [];
      details.push(errors.nodes ? 'Nodes: ' + errors.nodes : result.nodes.length + ' Node(s) geladen.');
      details.push(errors.pools ? 'Pools: ' + errors.pools : result.pools.length ? result.pools.length + ' Ressourcenpool(s) geladen.' : 'Keine Ressourcenpools sichtbar. Lege bei Bedarf in Proxmox einen Shop-Pool an und lade die Liste neu; prüfe bei fehlenden Einträgen die Leserechte.');
      if (!errors.nodes && !result.nodes.length) details.push('Keine Nodes sichtbar. Bitte die Proxmox-Leserechte prüfen.');
      details.push('Die gewünschte Auswahl treffen und speichern.');
      showStatus(details.join(' '));
    } catch (error) {
      showStatus(error.name === 'AbortError' ? 'Die Proxmox-Abfrage dauert zu lange. Bitte Verbindung prüfen und die Listen erneut laden.' : error.message || 'Die Proxmox-Auswahl konnte nicht geladen werden.');
    } finally {
      window.clearTimeout(timeout);
      reload.disabled = false;
      form.removeAttribute('aria-busy');
    }
  };
  reload.addEventListener('click', loadTargets);
  loadTargets();
});

document.querySelectorAll('[data-smtp-settings]').forEach(form => {
  const security = form.querySelector('[name="security"]');
  const port = form.querySelector('[name="port"]');
  security.addEventListener('change', () => {
    if (['465', '587', ''].includes(port.value)) port.value = security.value === 'ssl' ? '465' : '587';
  });
});

const packageEditor = document.querySelector('[data-package-editor]');
if (packageEditor) {
  const category = packageEditor.querySelector('[name="category"]');
  const providers = {hosting:'ispconfig',server:'proxmox',domain:'ovh',managed:'manual'};
  const syncPackage = () => packageEditor.querySelectorAll('[data-package-provider]').forEach(group => {
    group.hidden = group.dataset.packageProvider !== providers[category.value];
  });
  category.addEventListener('change', () => {
    if (category.value === 'domain' && packageEditor.querySelector('[name="billing"]').value === 'month') packageEditor.querySelector('[name="billing"]').value = 'year';
    syncPackage();
  });
  syncPackage();
}

// Keep the customer on their own order while the scheduled worker provisions it.
document.querySelectorAll('[data-order-progress][data-watch="1"]').forEach(panel => {
  let attempts = 0;
  const poll = async () => {
    if (document.hidden) { window.setTimeout(poll, 5000); return; }
    try {
      const body = new URLSearchParams({action:'order_status',order_id:panel.dataset.orderId,csrf_token:panel.dataset.csrf});
      const response = await fetch(window.location.pathname, {method:'POST',body,credentials:'same-origin',headers:{Accept:'application/json'}});
      if (!response.ok || !response.headers.get('content-type')?.includes('application/json')) throw new Error('Status unavailable');
      const status = await response.json();
      panel.querySelector('[data-order-label]').textContent = status.label;
      const link = panel.querySelector('[data-order-service]');
      if (status.service_url && /^\/konto\/verwalten\?id=\d+$/.test(status.service_url)) {
        link.href = status.service_url;
        link.hidden = false;
      }
      if (!['queued','provisioning','domain_verification'].includes(status.fulfillment_status)) {
        panel.querySelector('[data-order-note]').textContent = '';
        return;
      }
    } catch {
      panel.querySelector('[data-order-note]').textContent = 'Der Status konnte gerade nicht aktualisiert werden. Lade die Seite später erneut.';
      return;
    }
    if (++attempts < 90) window.setTimeout(poll, 5000);
    else panel.querySelector('[data-order-note]').textContent = 'Die Einrichtung läuft weiter. Du kannst den aktuellen Stand durch Neuladen der Seite prüfen.';
  };
  window.setTimeout(poll, 4000);
});

// This preview helps with the selection. Domain purchases always repeat the
// authoritative lookup on the server before creating a PayPal payment.
document.querySelectorAll('form[data-checkout-domain-mode]').forEach(form => {
  const input = form.querySelector('[data-checkout-domain-input]');
  const button = form.querySelector('[data-checkout-domain-button]');
  const output = form.querySelector('[data-checkout-domain-result]');
  if (!input || !button || !output) return;
  let controller = null;
  let requested = '';
  let revision = 0;
  const check = async () => {
    const domain = input.value.trim().toLowerCase();
    if (controller && requested === domain) return;
    controller?.abort();
    const current = ++revision;
    if (!domain) {
      output.dataset.status = 'error';
      output.textContent = 'Bitte gib zuerst eine Wunschdomain ein.';
      return;
    }
    const request = new AbortController();
    controller = request;
    requested = domain;
    output.dataset.status = 'checking';
    output.textContent = 'Die Domain wird direkt bei OVHcloud geprüft …';
    try {
      const body = new URLSearchParams({action:'checkout_domain_check',domain,product:form.elements.product.value,csrf_token:form.elements.csrf_token.value});
      const response = await fetch(window.location.pathname, {method:'POST',body,credentials:'same-origin',signal:request.signal,headers:{Accept:'application/json'}});
      if (!response.headers.get('content-type')?.includes('application/json')) throw new Error('Session expired');
      const result = await response.json();
      if (revision !== current) return;
      output.dataset.status = result.status;
      output.textContent = result.message;
      const selectedExtension = form.elements.domain_extension?.value;
      const payButton = form.querySelector('button.full-width');
      if (selectedExtension && payButton) {
        const changed = result.extension !== selectedExtension;
        payButton.dataset.originalDisabled ??= payButton.disabled ? '1' : '0';
        payButton.disabled = changed || payButton.dataset.originalDisabled === '1';
        const reprice = form.querySelector('[data-domain-reprice]');
        if (changed) {
          output.textContent += ' Die Endung wurde geändert. Bitte lade den passenden Tarif und prüfe seinen Preis vor der Zahlung.';
          if (reprice && result.url?.startsWith('/bestellen?')) reprice.href = result.url;
        }
      }
      if (form.dataset.checkoutDomainMode === 'hosting' && result.status === 'unavailable') output.textContent += ' Eine bereits registrierte eigene Domain kannst du für deinen Webspace verwenden.';
      if (form.dataset.checkoutDomainMode === 'hosting' && result.status === 'available') output.textContent += ' Die Domainregistrierung ist separat zu bestellen.';
    } catch (error) {
      if (error.name === 'AbortError' || revision !== current) return;
      output.dataset.status = 'error';
      output.textContent = 'Die Domainprüfung ist gerade nicht erreichbar. Bitte versuche es erneut oder lade die Seite neu.';
    } finally {
      if (revision === current) controller = null;
    }
  };
  input.addEventListener('input', () => {
    controller?.abort(); controller = null; revision++;
    output.dataset.status = '';
    output.textContent = form.dataset.checkoutDomainMode === 'registration' ? 'Die Verfügbarkeit wird vor der Weiterleitung zu PayPal automatisch erneut geprüft.' : 'Du kannst eine neue Wunschdomain oder eine bereits registrierte eigene Domain verwenden.';
  });
  input.addEventListener('blur', () => { if (input.value.trim()) check(); });
  button.addEventListener('click', check);
  if (input.value.trim()) check();
});

// Prices are entered as net amounts; customers pay the displayed gross amount.
document.querySelectorAll('[data-net-price]').forEach(input => {
  const form = input.closest('form');
  const output = form?.querySelector('[data-tax-preview]');
  if (!output) return;
  const format = cents => new Intl.NumberFormat('de-DE', {style:'currency',currency:'EUR'}).format(cents/100);
  const update = () => {
    const value = input.value.trim().replace(',', '.');
    if (!/^\d{1,6}(?:\.\d{1,2})?$/.test(value)) { output.textContent = value ? 'Bitte einen Nettopreis mit höchstens zwei Nachkommastellen eintragen.' : 'Ohne Preis: individuelles Angebot.'; return; }
    const [whole, fraction = ''] = value.split('.');
    const net = Number(whole)*100 + Number(fraction.padEnd(2,'0'));
    const gross = Math.floor((net*11900+5000)/10000);
    output.textContent = `${format(net)} netto + ${format(gross-net)} MwSt. (19 %) = ${format(gross)} Kundenpreis`;
  };
  input.addEventListener('input', update); update();
});
const saleMode = document.querySelector('[name="sale_mode"]');
if (saleMode) {
  const direct = saleMode.form.querySelector('[name="direct_purchase"]');
  const sync = () => { if (direct) { direct.disabled = saleMode.value === 'request'; direct.closest('label').hidden = direct.disabled; } };
  saleMode.addEventListener('change', sync); sync();
}

// Public domain alternatives use individually checked, fresh provider results.
document.querySelectorAll('[data-domain-search]').forEach(form => {
  form.addEventListener('submit', event => {
    event.preventDefault();
    const url = new URL('/domain-pruefen', location.origin);
    url.searchParams.set('domain', form.elements.domain.value.trim());
    if(Number(form.dataset.rewardId))url.searchParams.set('reward_id',form.dataset.rewardId);
    location.assign(url.href);
  });
});
const domainRows = Array.from(document.querySelectorAll('[data-domain-row]'));
if (domainRows.length) {
  const labels = {available:'Aktuell registrierbar',unavailable:'Nicht neu registrierbar',unsupported:'Nicht im Sortiment',error:'Prüfung nicht möglich'};
  async function lookup(row) {
    const status = row.querySelector('[data-domain-status]');
    const message = row.querySelector('[data-domain-message]');
    const action = row.querySelector('[data-domain-action]');
    const retry = row.querySelector('[data-domain-retry]');
    row.dataset.domainState = 'checking'; status.textContent = 'Wird geprüft …';
    action.hidden = true; retry.hidden = true;
    const controller = new AbortController();const timer = setTimeout(() => controller.abort(), 25000);
    try {
      const response = await fetch('/api/domain-check?domain='+encodeURIComponent(row.dataset.domain), {credentials:'same-origin',signal:controller.signal,headers:{Accept:'application/json'}});
      const result = await response.json();
      if (!response.ok || !labels[result.status]) throw new Error(result.message || 'Die Prüfung ist gerade nicht erreichbar.');
      row.dataset.domainState = result.status;
      status.textContent = labels[result.status]; message.textContent = result.message;
      row.querySelector('[data-domain-price]').textContent = result.price;
      const taxNote = row.querySelector('[data-domain-tax-note]');if (taxNote && typeof result.tax_note === 'string') taxNote.textContent = result.tax_note;
      if (result.url?.startsWith('/') && !result.url.startsWith('//') && result.action) {
        const rewardId=new URLSearchParams(location.search).get('reward_id');const offerUrl=new URL(result.url,location.origin);if(rewardId && /^\d+$/.test(rewardId))offerUrl.searchParams.set('reward_id',rewardId);action.href = offerUrl.pathname+offerUrl.search;action.textContent = result.action;action.hidden = false;
      }
    } catch(error) {
      row.dataset.domainState = 'error';status.textContent = labels.error;
      message.textContent = error.name==='AbortError' ? 'Die Prüfung dauert zu lange. Bitte erneut versuchen.' : (error.message || 'Bitte erneut versuchen.');
      retry.hidden = false;
    } finally {clearTimeout(timer);}
  }
  domainRows.forEach(row => row.querySelector('[data-domain-retry]').addEventListener('click', () => lookup(row)));
  const queue = domainRows.filter(row => row.dataset.domainChecked !== '1');
  const run = async () => {let row;while((row=queue.shift()))await lookup(row);};
  run();run();
}
document.querySelectorAll('[data-domain-reprice]').forEach(link => {
  const form = link.closest('form'); const input = form?.elements.domain;
  if (!input) return;
  const update = () => {const url = new URL('/bestellen', location.origin);url.searchParams.set('produkt',form.elements.product.value);url.searchParams.set('domain',input.value.trim());link.href=url.pathname+url.search;};
  input.addEventListener('input',update);update();
});

const cancellationKind = document.querySelector('[data-cancellation-kind]');
if (cancellationKind) {
  const reason = document.querySelector('[data-cancellation-reason]');
  const syncCancellation = () => { reason.required = cancellationKind.value === 'extraordinary'; };
  cancellationKind.addEventListener('change', syncCancellation);
  syncCancellation();
}

// Configurable packages: all binding prices are recalculated by the server.
document.querySelectorAll('[data-module-configurator]').forEach(configurator=>{
  const money=value=>new Intl.NumberFormat('de-DE',{style:'currency',currency:'EUR'}).format(value/100);
  const update=()=>{let net=Number(configurator.dataset.baseNet)||0,gross=Math.round(net*119/100);const list=configurator.querySelector('[data-module-description]');list.replaceChildren();
    configurator.querySelectorAll('[data-module-quantity]').forEach(input=>{const choice=input.tagName==='SELECT'?input.selectedOptions[0]:null;const qty=choice?(Number(input.value)?1:0):(input.type==='checkbox'?(input.checked?1:0):Math.max(0,Number(input.value)||0));const amount=qty*(Number(choice?.dataset.net??input.dataset.net)||0);net+=amount;gross+=Math.round(amount*119/100);if(qty){const row=document.createElement('li');row.textContent=qty+' × '+input.dataset.label+(choice?.dataset.optionLabel?': '+choice.dataset.optionLabel:'')+(input.dataset.unit?' ('+input.dataset.unit+')':'')+' · '+money(amount)+' netto';list.append(row);}});
    if(configurator.dataset.separateBilling!=='1')gross=Math.round(net*119/100);
    configurator.querySelector('[data-module-net]').textContent=money(net);configurator.querySelector('[data-module-tax]').textContent=money(gross-net);configurator.querySelector('[data-module-gross]').textContent=money(gross);
    const unknown=configurator.dataset.baseUnknown==='1', purchaseApplied=!!configurator.closest('[data-pricing-applied]');const summary=document.querySelector('.summary-price strong');if(summary && !unknown && !summary.hasAttribute('data-preserve-price'))summary.textContent=money(gross);if(!unknown && !purchaseApplied)document.querySelectorAll('[data-configured-price]').forEach(value=>value.textContent=money(gross));const tax=document.querySelector('.summary-panel .tax-summary');if(tax && !unknown && !purchaseApplied){const values=tax.querySelectorAll('dd');if(values.length===3){values[0].textContent=money(net);values[1].textContent=money(gross-net);values[2].textContent=money(gross);}}
    if(!list.children.length){const row=document.createElement('li');row.textContent='Grundausstattung ohne Zusatzmodule';list.append(row);}
    const profile=JSON.parse(configurator.dataset.baseProfile||'{}');
    const flagLabels={cgi_enabled:'CGI',ssi_enabled:'SSI',perl_enabled:'Perl',ruby_enabled:'Ruby',python_enabled:'Python',suexec_enabled:'SuEXEC',errordocs_enabled:'Eigene Fehlerseiten',ssl_enabled:'SSL',letsencrypt_enabled:"Let's Encrypt SSL"};
    configurator.querySelectorAll('[data-module-quantity]').forEach(input=>{const selected=input.tagName==='SELECT'?input.selectedOptions[0]:null;const qty=selected?(Number(input.value)?1:0):(input.type==='checkbox'?(input.checked?1:0):Math.max(0,Number(input.value)||0));if(!qty)return;const target=input.dataset.target;
      if(selected){profile[target]=selected.dataset.technicalValue;if(target==='php_version'){profile.php_version_label=selected.dataset.optionLabel;if(profile.php_mode==='no')profile.php_mode='php-fpm';}}
      else if(flagLabels[target]||target==='php_enabled'){profile[target]=1;if(target==='php_enabled'&&profile.php_mode==='no')profile.php_mode='php-fpm';}
      else if(target!=='description_only')profile[target]=(Number(profile[target])||0)+qty*(Number(input.dataset.unitValue)||1);
    });
    if(profile.letsencrypt_enabled)profile.ssl_enabled=1;
    const runtimeNames={no:'Deaktiviert','fast-cgi':'Fast-CGI',cgi:'CGI',mod:'Mod-PHP','php-fpm':'PHP-FPM'};
    const specs={'Webspace (MB)':profile.storage_mb,'FTP-Zugänge':profile.ftp_limit,'Datenbanken':profile.database_limit,'Postfächer':profile.mail_limit,'Speicher je Postfach (MB)':profile.mail_quota_mb,'Web-Cronjobs':profile.cron_limit,'Zusätzliche Systemplatte (GB)':profile.disk_extra_gb,'vCPU':profile.cores,'Arbeitsspeicher (MB)':profile.memory_mb,'PHP':runtimeNames[profile.php_mode],'PHP-Version':profile.php_version_label||'Systemstandard','Auto-Subdomain':{none:'Keine',www:'www.','*':'*.'}[profile.auto_subdomain]};
    for(const [key,label] of Object.entries(flagLabels))specs[label]=profile[key]?'Enthalten':'Nicht enthalten';
    document.querySelectorAll('[data-package-spec-caption]').forEach(node=>{const value=specs[node.dataset.packageSpecCaption];if(value!==undefined)node.textContent=String(value);});

  };
  configurator.addEventListener('input',update);configurator.addEventListener('change',update);update();
});
document.querySelectorAll('[data-reveal-stored-password]').forEach(button=>button.addEventListener('click',()=>{const input=button.closest('.field').querySelector('[data-stored-password]');input.type=input.type==='password'?'text':'password';}));

// Central module catalog: show only controls that belong to the selected category and target.
document.querySelectorAll('[data-catalog-module-editor]').forEach(form=>{
  const category=form.querySelector('[data-catalog-category]'),target=form.querySelector('[data-catalog-target]');
  const flags=['php_enabled','cgi_enabled','ssi_enabled','perl_enabled','ruby_enabled','python_enabled','suexec_enabled','errordocs_enabled','ssl_enabled','letsencrypt_enabled'];
  const set=(node,visible)=>{node.hidden=!visible;node.querySelectorAll('input,select,textarea').forEach(input=>input.disabled=!visible);};
  const update=()=>{target.querySelectorAll('option').forEach(option=>{option.disabled=option.dataset.moduleCategory!==category.value;option.hidden=option.disabled;});if(target.selectedOptions[0]?.disabled)target.value=[...target.options].find(o=>!o.disabled)?.value||'';
    const choice=['auto_subdomain','php_mode','php_version'].includes(target.value),boolean=flags.includes(target.value);
    set(form.querySelector('[data-catalog-number-fields]'),!choice&&!boolean);set(form.querySelector('[data-catalog-boolean-fields]'),boolean);
    form.querySelectorAll('[data-catalog-choice-fields]').forEach(field=>set(field,field.dataset.catalogChoiceFields===target.value));
  };category.addEventListener('change',update);target.addEventListener('change',update);update();
});
document.querySelectorAll('[data-hosting-feature-fields]').forEach(section=>{
  const mode=section.querySelector('[data-hosting-php-mode]'),version=section.querySelector('[data-hosting-php-version]');
  const sync=()=>{for(const option of version.options){option.disabled=Boolean(option.value!=='0'&&!option.dataset.phpModes?.split(',').includes(mode.value));option.hidden=option.disabled;}if(version.selectedOptions[0]?.disabled)version.value='0';};mode.addEventListener('change',sync);sync();
});
document.querySelectorAll('[data-package-module-bindings]').forEach(section=>{
  const category=section.closest('form').querySelector('[name=category]'),configurator=section.querySelector('[data-package-configurator]');
  const update=()=>{section.querySelectorAll('[data-package-module-category]').forEach(field=>{const visible=field.dataset.packageModuleCategory===category.value;field.hidden=!visible;field.querySelectorAll('input,select').forEach(input=>input.disabled=!visible);});section.querySelectorAll('[data-binding-customer-mode]').forEach(field=>{field.hidden=false;});};category.addEventListener('change',update);configurator.addEventListener('change',update);update();
});

// Storage units are explicit; count-based modules keep their own customer label.
document.querySelectorAll('[data-module-unit-field]').forEach(field => {
  const form=field.closest('form'),target=form.querySelector('[name=target]'),storage=field.querySelector('[data-storage-unit]'),count=field.querySelector('[data-count-unit]');
  const update=()=>{
    const isStorage=['storage_mb','mail_quota_mb','memory_mb','disk_extra_gb'].includes(target.value);
    storage.hidden=!isStorage;storage.disabled=!isStorage;storage.required=isStorage;
    count.hidden=isStorage;count.disabled=isStorage;
    storage.removeAttribute('name');count.removeAttribute('name');(isStorage?storage:count).name='unit_label';
  };
  form.addEventListener('change',update);update();
});

// Module amounts count units; resource sizes are defined by the separate technical increment.
document.querySelectorAll('[data-module-quantity-fields]').forEach(fields=>{
  const form=fields.closest('form'),minimum=fields.querySelector('[name=min_qty]'),maximum=fields.querySelector('[name=max_qty]'),preset=fields.querySelector('[name=default_qty]');
  const target=form.querySelector('[name=target]'),control=form.querySelector('select[name=control]'),status=fields.querySelector('[data-module-quantity-status]'),hint=form.querySelector('[data-module-technical-hint]');
  const step=form.querySelector('[name=unit_value]'),conversion=form.querySelector('[data-module-storage-conversion]');
  const help=JSON.parse(form.dataset.moduleQuantityHelp||'{}');
  const update=()=>{
    const numeric=!fields.hidden&&!preset.disabled&&(!control||control.value==='number');
    if(hint)hint.textContent=help[target?.value]||'Technischer Zuwachs pro zusätzlicher Moduleinheit.';
    if(step)step.setCustomValidity('');if(conversion)conversion.textContent='';
    if(numeric&&step&&['storage_mb','mail_quota_mb','memory_mb','disk_extra_gb'].includes(target?.value)){
      const unit=form.querySelector('[data-storage-unit]')?.value,mb=Number(step.value)*({MB:1,GB:1024,TB:1048576}[unit]||0),format=value=>new Intl.NumberFormat('de-DE',{maximumFractionDigits:3}).format(value);
      if(mb>0&&conversion)conversion.textContent='1 Moduleinheit = '+format(mb)+' MB'+(mb>=1024?' ('+format(mb/1024)+' GB)':'')+'.'+(Number(preset.value)>0?' Vorauswahl '+preset.value+': +'+format(mb*Number(preset.value))+' MB.':'');
      if(target.value==='disk_extra_gb'&&mb>0&&mb%1024!==0){const message='Für VPS-Festplatten muss der Zuwachs ganze GB ergeben. Bei Einheit MB zum Beispiel 1024 für 1 GB eintragen.';step.setCustomValidity(message);if(conversion)conversion.textContent=message;}
    }
    minimum.setCustomValidity('');maximum.setCustomValidity('');preset.setCustomValidity('');
    if(!numeric){if(status)status.textContent='Ein-/Aus-Optionen verwenden die Auswahl 0 oder 1.';return;}
    const min=Number(minimum.value)||0,max=Number(maximum.value)||0,value=Number(preset.value)||0;
    let message='Die Vorauswahl ist die beim Kunden bereits eingetragene Menge. Für ein optionales Modul: Mindestmenge 0 und Vorauswahl 0.';
    if(max>0&&max<min){message='Die Höchstmenge ('+max+') liegt unter der Mindestmenge ('+min+'). Erhöhe die Höchstmenge oder lasse sie für unbegrenzt leer.';maximum.setCustomValidity(message);}
    else if(value<min){message='Die vorausgewählte Menge ('+value+') liegt unter der Mindestmenge ('+min+'). Trage mindestens '+min+' ein oder setze die Mindestmenge für ein optionales Modul auf 0.';preset.setCustomValidity(message);}
    else if(max>0&&value>max){message='Die vorausgewählte Menge ('+value+') überschreitet die Höchstmenge ('+max+').';preset.setCustomValidity(message);}
    if(status)status.textContent=message;
  };
  form.addEventListener('input',update);form.addEventListener('change',update);update();
});

// Template changes refresh the authoritative checkout profile before payment approval.
document.querySelectorAll('[data-server-template-reload]').forEach(select => select.addEventListener('change', () => {
  const form = select.closest('form'); const params = new URLSearchParams();
  new FormData(form).forEach((value, key) => {if(key === 'product') params.set('produkt', value); else if(key === 'domain' || key === 'server_template' || key.startsWith('modules[')) params.set(key, value);});
  window.location.assign('/bestellen?' + params.toString());
}));

document.querySelectorAll('[data-virtualization-select]').forEach(select => {
  const update = () => select.closest('fieldset').querySelectorAll('[data-qemu-field]').forEach(field => {field.hidden = select.value === 'lxc';field.querySelectorAll('input').forEach(input => {input.disabled = select.value === 'lxc';});});
  select.addEventListener('change', update); update();
});

// A changed choice must be quoted again before it can be submitted as an order.
document.querySelectorAll('[data-purchase-pricing-form]').forEach(form=>{
  const stale=form.querySelector('[data-pricing-stale]'), checkout=form.querySelector('[data-pricing-checkout]');
  const changed=event=>{if(!event.target.matches('[data-module-quantity], [name="coupon_code"], [name="prepay_months"], [name="reward_id"]'))return;if(stale)stale.hidden=false;if(checkout)checkout.disabled=true;form.dataset.pricingStale='1';};
  form.addEventListener('input',changed);form.addEventListener('change',changed);
  form.addEventListener('submit',event=>{if(form.dataset.pricingStale==='1' && event.submitter?.value!=='pricing_preview'){event.preventDefault();stale?.scrollIntoView({block:'center'});}});
});
// The saved profile (or guest cookie) is rendered by PHP before the first paint.
document.querySelectorAll('[data-theme-toggle-form]').forEach(form => {
  form.addEventListener('submit', async event => {
    event.preventDefault();
    const button = form.querySelector('[data-theme-toggle]');
    const feedback = form.querySelector('[data-theme-feedback]');
    button.disabled = true;
    try {
      const response = await fetch(form.action, {method:'POST', body:new FormData(form), headers:{Accept:'application/json'}});
      const result = await response.json();
      if (!response.ok || !result.success || !['dark','light'].includes(result.theme)) throw new Error();
      const light = result.theme === 'light';
      document.documentElement.dataset.theme = result.theme;
      document.querySelector('meta[name="color-scheme"]').content = result.theme;
      document.querySelector('meta[name="theme-color"]').content = light ? '#f5f7fb' : '#09090b';
      document.querySelectorAll('[data-theme-toggle-form]').forEach(toggle => {
        toggle.querySelector('[name="theme"]').value = light ? 'dark' : 'light';
        const control = toggle.querySelector('[data-theme-toggle]');
        const label = (light ? 'Dunklen' : 'Hellen') + ' Modus aktivieren';
        control.setAttribute('aria-label',label);
        control.setAttribute('title',label);
        control.setAttribute('aria-pressed',String(light));
        toggle.querySelector('[data-theme-icon="light"]').hidden = light;
        toggle.querySelector('[data-theme-icon="dark"]').hidden = !light;
      });
      document.querySelectorAll('.appearance-settings [name="theme"]').forEach(input => {input.checked = input.value === result.theme;});
      feedback.textContent = light ? 'Heller Modus gespeichert.' : 'Dunkler Modus gespeichert.';
    } catch {
      feedback.textContent = 'Die Darstellung konnte nicht gespeichert werden. Bitte lade die Seite neu und versuche es erneut.';
      button.title = feedback.textContent;
    } finally { button.disabled = false; }
  });
});
