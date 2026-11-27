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
/* Cargo view: tap a cargo position in the drawing to list its packages */
(() => {
 const box=document.querySelector('.ccv-craftbox');if(!box)return;
 function pick(i){
  box.querySelectorAll('[data-ccv-pos]').forEach(g=>g.classList.toggle('sel',g.dataset.ccvPos===i));
  box.querySelectorAll('[data-ccv-of]').forEach(p=>p.hidden=p.dataset.ccvOf!==i);
 }
 box.addEventListener('click',e=>{const g=e.target.closest('[data-ccv-pos]');if(g)pick(g.dataset.ccvPos);});
 box.addEventListener('keydown',e=>{const g=e.target.closest('[data-ccv-pos]');if(g&&(e.key==='Enter'||e.key===' ')){e.preventDefault();pick(g.dataset.ccvPos);}});
 const first=box.querySelector('[data-ccv-pos].on');if(first)pick(first.dataset.ccvPos);
})();
