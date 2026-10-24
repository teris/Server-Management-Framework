const fs = require('fs'), vm = require('vm'), path = require('path');
const sent = [];
class XHR {
    open() { this.headers = {}; }
    setRequestHeader(name,value) { this.headers[name] = value; }
    send() {}
}
const context = {
    document: {querySelector() {return {content:'server-token'};}},
    location: {href:'https://app.example.test/public/login.php',origin:'https://app.example.test'},
    URL, Request, XMLHttpRequest: XHR,
    window: {fetch:async request => {sent.push(request);return {};}}
};
vm.createContext(context);
vm.runInContext(fs.readFileSync(path.join(__dirname,'../src/assets/security.js'),'utf8'),context);
(async()=>{
    await context.window.fetch('https://app.example.test/change',{method:'POST'});
    await context.window.fetch('https://app.example.test/read');
    await context.window.fetch('https://other.example.test/change',{method:'POST'});
    if(sent[0].headers.get('X-CSRF-Token')!=='server-token')throw Error('Same-origin POST missing CSRF');
    if(sent[1].headers.has('X-CSRF-Token')||sent[2].headers.has('X-CSRF-Token'))throw Error('CSRF token escaped request boundary');
    const local=new XHR();local.open('POST','/change');local.send();
    const remote=new XHR();remote.open('POST','https://other.example.test/change');remote.send();
    if(local.headers['X-CSRF-Token']!=='server-token'||remote.headers['X-CSRF-Token'])throw Error('XHR boundary is incorrect');
    console.log(JSON.stringify({checks:4,failed:0}));
})().catch(error=>{console.error(error.message);process.exit(1);});
