(() => {
 const root=document.querySelector('[data-fleet]');if(!root)return;
 const get=s=>root.querySelector(s),roster=[...root.querySelectorAll('[data-rider]')];
 const names=new Map(roster.map(row=>[row.dataset.rider,row.querySelector('b').textContent]));
 let data=JSON.parse(get('[data-fleet-initial]').textContent),selected='',map,markers=[];
 function render(){
  const counts=new Map();data.stops.forEach(s=>counts.set(String(s.driver_id),(counts.get(String(s.driver_id))||0)+1));
  roster.forEach(row=>{const id=row.dataset.rider;row.setAttribute('aria-pressed',String(id===selected));row.querySelector('[data-rider-count]').textContent=(counts.get(id)||0)+' active stops';});
  root.querySelectorAll('[data-fleet-total]').forEach(el=>el.textContent=Number(data.totals?.[el.dataset.fleetTotal]||0).toLocaleString());
  const list=get('[data-fleet-stops]');list.replaceChildren();
  data.stops.filter(s=>!selected||String(s.driver_id)===selected).forEach(s=>{const a=document.createElement('a');a.href='delivery_dispatch.php?id='+Number(s.id);const title=document.createElement('b'),address=document.createElement('span');title.textContent='#'+s.id+' · '+(names.get(String(s.driver_id))||'Assigned rider');address.textContent=s.address;a.append(title,address);list.append(a);});
  if(!list.children.length)list.textContent='No active stops for this selection.';
  get('[data-fleet-time]').textContent='Updated '+new Date(data.asOf).toLocaleTimeString();
  if(!map)return;markers.forEach(m=>m.setMap(null));markers=[];
  const bounds=new google.maps.LatLngBounds();
  data.stops.filter(s=>!selected||String(s.driver_id)===selected).forEach(s=>{
   for(const point of [{lat:s.lat,lng:s.lng,label:'D',title:'Destination'},{lat:s.latitude,lng:s.longitude,label:'R',title:'Rider position · '+s.recorded_at+' UTC'}]){
    if(point.lat==null||point.lng==null)continue;
    const position={lat:Number(point.lat),lng:Number(point.lng)};
    const marker=new google.maps.Marker({map,position,label:point.label,title:point.title});
    marker.addListener('click',()=>location.href='delivery_dispatch.php?id='+Number(s.id));
    markers.push(marker);bounds.extend(position);
   }
  });
  get('[data-fleet-status]').textContent=markers.length?'D · destination    R · recent rider position':'No active positions to display.';
  if(markers.length){map.fitBounds(bounds);google.maps.event.addListenerOnce(map,'idle',()=>{if(map.getZoom()>16)map.setZoom(16);});}
 }
 roster.forEach(row=>row.addEventListener('click',()=>{selected=row.dataset.rider;render();}));
 get('[data-fleet-all]').addEventListener('click',()=>{selected='';render();});
 get('[data-fleet-search]').addEventListener('input',e=>{const q=e.target.value.toLowerCase();roster.forEach(row=>row.hidden=!row.textContent.toLowerCase().includes(q));});
 get('[data-fleet-expand]').addEventListener('click',()=>{root.classList.toggle('expanded');get('[data-fleet-expand]').textContent=root.classList.contains('expanded')?'Close map':'Expand map';if(map){google.maps.event.trigger(map,'resize');render();}});
 render();
 async function refresh(){if(document.hidden)return;try{const r=await fetch('delivery_dispatch.php?snapshot=1',{cache:'no-store'});if(!r.ok)throw Error();const next=await r.json();if(!Array.isArray(next.stops))throw Error();data=next;render();}catch{get('[data-fleet-time]').textContent='Updates paused';data.stops.forEach(s=>{s.latitude=null;s.longitude=null;});if(map)render();}}
 setInterval(refresh,30000);
 if(!root.dataset.key){get('[data-fleet-status]').textContent='Map key unavailable. Active stops are listed below.';return;}
 window.gm_authFailure=()=>get('[data-fleet-status]').textContent='Map unavailable. Active stops are listed below.';
 window.slFleetMapReady=()=>{map=new google.maps.Map(get('[data-fleet-map]'),{center:{lat:0,lng:0},zoom:2,streetViewControl:false,mapTypeControl:false});render();};
 const script=document.createElement('script');script.async=true;script.onerror=window.gm_authFailure;script.src='https://maps.googleapis.com/maps/api/js?'+new URLSearchParams({key:root.dataset.key,callback:'slFleetMapReady',loading:'async'});document.head.append(script);
})();
