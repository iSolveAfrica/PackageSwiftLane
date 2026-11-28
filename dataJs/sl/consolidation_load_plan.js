(() => {
 const root=document.querySelector('[data-load-plan]');if(!root)return;
 const grid=root.querySelector('.clp-grid'),deck=root.querySelector('.clp-deck');
 let zoom=1;
 root.querySelectorAll('[data-load-view]').forEach(button=>button.addEventListener('click',()=>{
  root.dataset.view=button.dataset.loadView;
  root.querySelectorAll('[data-load-view]').forEach(b=>b.setAttribute('aria-pressed',String(b===button)));
 }));
 root.querySelectorAll('[data-load-zoom]').forEach(button=>button.addEventListener('click',()=>{
  zoom=button.dataset.loadZoom==='reset'?1:Math.min(1.8,Math.max(.6,zoom+(button.dataset.loadZoom==='in'?.1:-.1)));
  grid.style.zoom=zoom;
 }));
 const rows=[...root.querySelectorAll('[data-load-row]')];
 function positions(){
  return rows.flatMap(row=>{
   const p={id:Number(row.dataset.loadRow)};
   ['bay','row','tier'].forEach(axis=>p[axis]=Number(row.querySelector('[name="'+axis+'"]').value));
   return p.bay||p.row||p.tier?[p]:[];
  });
 }
 function balance(){
  const totals=new Map();
  rows.forEach(row=>{
   const bay=Number(row.querySelector('[name="bay"]').value);
   if(bay)totals.set(bay,(totals.get(bay)||0)+Number(row.dataset.weight));
  });
  const target=root.querySelector('[data-load-balance]');target.replaceChildren();
  for(const [bay,weight] of [...totals.entries()].sort((a,b)=>a[0]-b[0])){
   const node=document.createElement('div');node.setAttribute('role','listitem');
   node.textContent='Bay '+bay+' · '+weight.toLocaleString(undefined,{maximumFractionDigits:2})+' '+root.dataset.weightUnit;
   target.append(node);
  }
  if(!totals.size)target.textContent='Save cargo positions to see recorded weight by bay.';
 }
 rows.forEach(row=>row.querySelector('[data-load-clear]').addEventListener('click',()=>{
  row.querySelectorAll('input').forEach(input=>input.value='');balance();
 }));
 const form=root.querySelector('[data-load-form]');
 if(!form)return;
 form.addEventListener('input',balance);balance();
 form.addEventListener('submit',async event=>{
  event.preventDefault();
  const status=root.querySelector('[data-load-feedback]'),button=form.querySelector('[type="submit"]');
  const items=positions(),occupied=new Set();
  for(const p of items){
   if(!Number.isInteger(p.bay)||p.bay<1||p.bay>100||!Number.isInteger(p.row)||p.row<1||p.row>20||!Number.isInteger(p.tier)||p.tier<1||p.tier>10){status.textContent='Complete bay, row and tier for each placed package.';return;}
   const key=[p.bay,p.row,p.tier].join('-');
   if(occupied.has(key)){status.textContent='Two packages occupy '+key+'. Choose separate positions.';return;}occupied.add(key);
  }
  button.disabled=true;status.textContent='Saving…';
  try{
   const response=await fetch('ajax/sl/consolidation_load_plan.php',{method:'POST',
    headers:{'X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},
    body:new URLSearchParams({id:root.dataset.id,type:root.dataset.type,revision:root.dataset.revision,positions:JSON.stringify(items)})});
   const result=await response.json();if(!response.ok||!result.success)throw Error(result.message||'Save failed.');
   root.dataset.revision=result.revision;
   root.querySelectorAll('[data-load-label]').forEach(label=>{const p=items.find(p=>p.id===Number(label.dataset.loadLabel));label.textContent=p?[p.bay,p.row,p.tier].join('-'):'Unplaced';});
   status.textContent='Positions saved.';
  }catch(error){status.textContent=error.message;}finally{button.disabled=false;}
 });
})();
/* Packages on board: live search; "Add packages" panel (pick, select all, live filter, Enter = server search) */
(() => {
 const root=document.querySelector('[data-load-plan]');if(!root)return;
 const norm=s=>(s||'').toLowerCase().trim();
 const q=root.querySelector('[data-cpk-q]');
 if(q)q.addEventListener('input',()=>{
  const v=norm(q.value);let shown=0;
  root.querySelectorAll('[data-cpk-group]').forEach(g=>{
   let n=0;g.querySelectorAll('.cpk-row').forEach(r=>{const hit=!v||r.dataset.text.includes(v);r.hidden=!hit;if(hit)n++;});
   g.hidden=!n;shown+=n;
  });
  const none=root.querySelector('[data-cpk-none]');if(none)none.hidden=shown>0;
 });
 const form=root.querySelector('#cpk-add'),toggle=root.querySelector('[data-cpk-add]');
 if(!form)return;
 const boxes=[...form.querySelectorAll('.cpk-av:not(.cpk-all) input')],all=form.querySelector('[data-cpk-all]');
 const ids=form.querySelector('[data-cpk-ids]'),sel=form.querySelector('[data-cpk-sel]'),submit=form.querySelector('[type="submit"]');
 const unit=root.dataset.weightUnit||'';
 function open(on){form.hidden=!on;if(toggle)toggle.setAttribute('aria-expanded',String(on));if(on){const f=form.querySelector('[data-cpk-aq]');if(f)f.focus();}}
 if(toggle)toggle.addEventListener('click',()=>open(form.hidden));
 document.querySelectorAll('[data-cpk-open]').forEach(a=>a.addEventListener('click',e=>{e.preventDefault();open(true);root.querySelector('#packages').scrollIntoView({behavior:'smooth'});}));
 form.querySelector('[data-cpk-close]').addEventListener('click',()=>open(false));
 function sync(){
  const on=boxes.filter(b=>b.checked);
  ids.value=on.map(b=>b.value).join(',');
  const w=on.reduce((s,b)=>s+Number(b.dataset.w||0),0);
  sel.textContent=on.length?on.length+' selected · '+w.toLocaleString(undefined,{maximumFractionDigits:1})+' '+unit:'Nothing selected';
  submit.disabled=!on.length;
  boxes.forEach(b=>b.closest('.cpk-av').classList.toggle('on',b.checked));
  const vis=boxes.filter(b=>!b.closest('.cpk-av').hidden);
  if(all){all.checked=vis.length>0&&vis.every(b=>b.checked);all.indeterminate=!all.checked&&vis.some(b=>b.checked);}
 }
 boxes.forEach(b=>b.addEventListener('change',sync));
 if(all)all.addEventListener('change',()=>{boxes.forEach(b=>{if(!b.closest('.cpk-av').hidden)b.checked=all.checked;});sync();});
 const aq=form.querySelector('[data-cpk-aq]');
 if(aq){
  aq.addEventListener('input',()=>{const v=norm(aq.value);boxes.forEach(b=>{const l=b.closest('.cpk-av');l.hidden=!!v&&!l.dataset.text.includes(v);});sync();});
  aq.addEventListener('keydown',e=>{if(e.key==='Enter'){e.preventDefault();location.href=root.dataset.self+'&add=1&q='+encodeURIComponent(aq.value)+'#packages';}});
 }
 form.addEventListener('sl:done',e=>{if(!e.detail||!e.detail.success)return;
  setTimeout(()=>{if(/[?&]add=1/.test(location.search))location.href=root.dataset.self+'#packages';else location.reload();},300);});
 sync();
 if(!form.hidden&&location.hash!=='#packages')root.querySelector('#packages').scrollIntoView();
})();
