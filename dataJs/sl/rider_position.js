(() => {
 const button=document.querySelector('[data-position-id]');
 if(!button)return;
 const status=document.querySelector('[data-position-status]');
 let active=false,timer;
 const send=data=>fetch('ajax/sl/rider_position.php',{method:'POST',headers:{'X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},body:new URLSearchParams({id:button.dataset.positionId,...data}),keepalive:true});
 function stop(message){active=false;clearTimeout(timer);button.textContent='Share location';button.setAttribute('aria-pressed','false');status.textContent=message;send({op:'stop'}).catch(()=>{});}
 function sample(){if(!active)return;navigator.geolocation.getCurrentPosition(async ({coords})=>{if(!active)return;try{const response=await send({lat:coords.latitude,lng:coords.longitude});if(!response.ok)throw Error();status.textContent='Shared at '+new Date().toLocaleTimeString();timer=setTimeout(sample,30000);}catch{stop('Sharing paused. Try again when connected.');}},()=>stop('Location unavailable. Check device permissions.'),{timeout:15000,maximumAge:10000});}
 button.addEventListener('click',()=>{if(active)return stop('Sharing stopped.');if(!navigator.geolocation){status.textContent='Location unavailable on this device.';return;}active=true;button.textContent='Stop sharing';button.setAttribute('aria-pressed','true');status.textContent='Finding your position…';sample();});
 addEventListener('pagehide',()=>{if(active)stop('Sharing stopped.');});
})();
