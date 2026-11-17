const { test } = require('node:test'), assert = require('node:assert/strict'), fs = require('node:fs'), vm = require('node:vm');
function fixture() {
    let factory; const pending = [];
    const context = {Intl, Date, document:{addEventListener:(_,fn)=>fn()}, window:{Alpine:{data:(_,fn)=>{factory=fn;}},axios:{get:()=>new Promise((resolve,reject)=>pending.push({resolve,reject}))}}};
    const source = fs.readFileSync('resources/js/qms-archives.js','utf8').replace(/^import .*;\r?\n/gm,'');
    vm.runInNewContext(fs.readFileSync('resources/js/portal-date.js','utf8').replace('export function','function')+'\n'+source,context);
    const app=factory(), dialog=()=>({open:false,showModal(){this.open=true;}});
    app.$refs={details:dialog(),folderDetails:dialog()};
    return {app,pending};
}
test('Document details open immediately; late results cannot replace the newer selection', async()=>{
    const {app,pending}=fixture();
    const first=app.inspect({_id:'old'});
    assert.equal(app.$refs.details.open,true);assert.equal(app.detailLoading,true);assert.equal(app.detail,null);
    const second=app.inspect({_id:'new'});
    pending[1].resolve({data:{data:{_id:'new'},versions:[],activity:[]}});await second;
    pending[0].resolve({data:{data:{_id:'old'},versions:[],activity:[]}});await first;
    assert.equal(app.detail._id,'new');assert.equal(app.detailLoading,false);
});
test('Folder failure is visible in the open dialog and retry replaces the error',async()=>{
    const {app,pending}=fixture();const folder={_id:'folder'};
    const first=app.inspectFolder(folder);assert.equal(app.$refs.folderDetails.open,true);assert.equal(app.folderDetailLoading,true);
    pending[0].reject({response:{data:{message:'Folder access changed'}}});await first;
    assert.equal(app.folderDetailError,'Folder access changed');assert.equal(app.folderDetailLoading,false);
    const retry=app.inspectFolder(app.folderDetailTarget);pending[1].resolve({data:{data:{name:'Current folder'}}});await retry;
    assert.equal(app.folderDetail.name,'Current folder');assert.equal(app.folderDetailError,'');
});
