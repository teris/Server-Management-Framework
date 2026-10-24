'use strict';
(() => {
  const form = document.getElementById('update-upload');
  const message = document.getElementById('update-message');
  const container = document.getElementById('update-jobs');
  if (!form || !container || !message) return;
  const labels = { verified: 'Paket geprüft', staged: 'Vorbereitet', applying: 'Wird aktiviert', activated: 'Aktiv', rolled_back: 'Zurückgenommen', aborted: 'Abgebrochen', recovery_required: 'Wiederherstellung erforderlich' };
  async function request(data) {
    const response = await fetch('api/updates.php', data ? { method: 'POST', body: data } : {});
    const result = await response.json();
    if (!response.ok || !result.success) throw new Error(result.error || 'Die Anfrage ist fehlgeschlagen.');
    return result;
  }
  async function refresh() {
    const result = await request();
    container.replaceChildren();
    for (const job of result.jobs) {
      const section = document.createElement('section');
      const title = document.createElement('h2'); title.textContent = `${job.version} (${job.type})`; section.append(title);
      const status = document.createElement('p'); status.textContent = `${labels[job.state] || job.state}${job.requested ? ' – angefordert: ' + job.requested : ''}`; section.append(status);
      const actions = job.state === 'verified' ? [['stage','Vorbereiten'],['abort','Abbrechen']] : job.state === 'staged' ? [['activate','Aktivieren'],['abort','Abbrechen']] : ['activated','recovery_required'].includes(job.state) ? [['rollback','Zurücknehmen']] : [];
      for (const [action,label] of actions) {
        const button = document.createElement('button'); button.type = 'button'; button.textContent = label; button.disabled = !!job.requested;
        button.addEventListener('click',async () => {
          button.disabled = true;
          try { const data = new FormData(); data.set('id',job.id); data.set('action',action); await request(data); message.textContent = 'Auftrag angenommen. Der Hintergrunddienst übernimmt die Ausführung.'; await refresh(); }
          catch (error) { message.textContent = error.message; button.disabled = false; }
        }); section.append(button);
      }
      container.append(section);
    }
  }
  form.addEventListener('submit',async event => {
    event.preventDefault();
    const button = form.querySelector('button'); button.disabled = true;
    try { const data = new FormData(form); data.set('action','register'); await request(data); message.textContent = 'Signatur und Paketinhalt wurden geprüft.'; await refresh(); }
    catch (error) { message.textContent = error.message; }
    finally { button.disabled = false; }
  });
  refresh().catch(error => { message.textContent = error.message; });
})();
