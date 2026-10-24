const fs = require('fs');
const vm = require('vm');
const path = require('path');
let requests = 0;
const context = {
    window: {sessionInfo: {timeRemaining: 90, timeout: 3600}, location: {href: ''}},
    document: {addEventListener() {}, getElementById() { return null; }},
    Date, clearTimeout() {}, clearInterval() {}, setTimeout() {}, setInterval() {},
    fetch: async () => ({ok: false, status: 403, json: async () => ({success: false})})
};
vm.createContext(context);
vm.runInContext(fs.readFileSync(path.join(__dirname, '../src/assets/session.js'), 'utf8'), context);
(async () => {
    vm.runInContext('extendSession()', context);
    await new Promise(resolve => setImmediate(resolve));
    if (context.window.sessionInfo.timeRemaining !== 90) throw Error('Failed request reset the session timer');
    context.fetch = async () => { requests++; return {ok: true, status: 200, json: async () => ({success: true, remaining: 777, timeout: 900})}; };
    vm.runInContext('extendSession()', context);
    await new Promise(resolve => setImmediate(resolve));
    if (requests !== 1 || context.window.sessionInfo.timeRemaining !== 777 || context.window.sessionInfo.timeout !== 900) throw Error('Timer ignored the server expiry');
    console.log(JSON.stringify({checks: 2, failed: 0}));
})().catch(error => { console.error(error.message); process.exit(1); });
