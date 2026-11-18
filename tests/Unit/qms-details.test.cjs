const { test } = require('node:test'), assert = require('node:assert/strict'), fs = require('node:fs'), vm = require('node:vm');
function fixture() {
    let factory; const pending = [];
    const context = {Intl, Date, AbortController, document:{addEventListener:(_,fn)=>fn()}, window:{Alpine:{data:(_,fn)=>{factory=fn;}},axios:{get:(url,config)=>new Promise((resolve,reject)=>pending.push({url,config,resolve,reject}))}}};
    const source = fs.readFileSync('resources/js/qms-archives.js','utf8').replace(/^import .*;\r?\n/gm,'');
    vm.runInNewContext(fs.readFileSync('resources/js/portal-date.js','utf8').replace('export function','function')+'\n'+source,context);
    const app=factory(), dialog=()=>({open:false,showModal(){this.open=true;}});
    app.$refs={details:dialog(),folderDetails:dialog()};
    return {app,pending};
}

test('Super Admin sees the current cycle in My Drive and all cycle roots in Audit Records', async()=>{
    const {app,pending}=fixture();
    app.tree={canViewDocuments:false,units:[{_id:'department',name:'Department'}],categories:[],repositoryFolders:[
        {_id:'current',parentId:null,name:'Internal Quality Audit August 2026',currentCycle:true,auditRelated:true},
        {_id:'past',parentId:null,name:'Internal Quality Audit August 2025',currentCycle:false,auditRelated:true},
        {_id:'campus',parentId:'current',name:'USTP Claveria',auditRelated:true},
    ]};
    assert.equal(app.repositoryChildren.map(f=>f._id).join(','),'current');
    assert.equal(app.folders.length,0);assert.equal(app.categories.length,0);
    await app.switchView('cycles');
    assert.equal(app.repositoryChildren.map(f=>f._id).join(','),'current,past');
    await app.openRepositoryFolder(app.tree.repositoryFolders[0]);
    assert.equal(app.repositoryChildren.map(f=>f._id).join(','),'campus');
    assert.equal(app.breadcrumbs.map(f=>f.name).join('/'),'Audit Records/Internal Quality Audit August 2026');
    assert.equal(pending.length,0);assert.equal(app.documents.length,0);
});
test('Assigned Audits opens the workspace path without exposing another auditor', async()=>{
    const {app,pending}=fixture();
    app.tree={actorId:'me',units:[],categories:[],assignedAudits:[{_id:'audit'}],repositoryFolders:[
        {_id:'cycle',parentId:null,name:'Internal Quality Audit August 2026',cycleRoot:true,auditRelated:true,navigationOnly:true},
        {_id:'workspace',parentId:'campus',name:'02 Auditor Workspaces',workspaceArea:true,workspaceRoot:true,navigationOnly:true},
        {_id:'me',parentId:'workspace',name:'My name',workspaceArea:true,auditorId:'me',navigationOnly:true},
        {_id:'other',parentId:'workspace',name:'Other auditor',workspaceArea:true,auditorId:'other'},
    ]};
    await app.switchView('assigned');
    assert.equal(app.repositoryChildren.map(folder=>folder._id).join(','),'workspace');
    await app.openRepositoryFolder(app.tree.repositoryFolders[1]);
    assert.equal(app.repositoryChildren.map(folder=>folder._id).join(','),'me');
    assert.equal(pending.length,0,'Navigation containers do not request files');
});

test('Recent always requests five files from the first page', async()=>{
    const {app,pending}=fixture();
    const request=app.switchView('recent');
    assert.equal(pending[0].config.params.perPage,5);assert.equal(pending[0].config.params.page,1);
    pending[0].resolve({data:{data:[],meta:{currentPage:1,lastPage:1,total:0}}});await request;
    assert.equal(app.emptyMessage,'No recently opened files yet.');
});

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
