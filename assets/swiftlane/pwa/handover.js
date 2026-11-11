(function(){
 'use strict';
 const phone=matchMedia('(max-width:767px), (max-height:500px) and (max-width:950px) and (pointer:coarse)').matches;
 if(phone)document.querySelectorAll('[data-pwa-collapse]').forEach(details=>{details.open=false;});
 document.querySelectorAll('[data-pwa-handover]').forEach(form=>{
   const panels=Array.from(form.querySelectorAll('[data-pwa-step]')),indicator=form.querySelector('[data-pwa-steps]');
   if(panels.length!==3)return;
   let step=0,previewURL;
   indicator.hidden=false;form.querySelectorAll('[data-pwa-next],[data-pwa-prev]').forEach(button=>{button.hidden=false;});
   function fieldsValid(panel){for(const input of panel.querySelectorAll('input,select,textarea'))if(!input.checkValidity()){input.reportValidity();return false;}return true;}
   function show(index,focus){step=index;panels.forEach((panel,i)=>{panel.hidden=i!==step;});indicator.querySelectorAll('li').forEach((li,i)=>{if(i===step)li.setAttribute('aria-current','step');else li.removeAttribute('aria-current');});
     const receiver=form.querySelector('[name=receiver]');form.querySelector('[data-pwa-review-receiver]').textContent=receiver.value.trim()||'Not entered';
     if(focus){const target=panels[step].querySelector('input,button');if(target)target.focus();}
   }
   function next(){if(fieldsValid(panels[step]))show(Math.min(step+1,2),true);}
   form.querySelectorAll('[data-pwa-next]').forEach(button=>button.addEventListener('click',next));
   form.querySelectorAll('[data-pwa-prev]').forEach(button=>button.addEventListener('click',()=>show(Math.max(step-1,0),true)));
   form.addEventListener('submit',event=>{
     if(step<2){event.preventDefault();event.stopImmediatePropagation();next();return;}
     for(let i=0;i<2;i++)if(Array.from(panels[i].querySelectorAll('input,select,textarea')).some(input=>!input.checkValidity())){event.preventDefault();event.stopImmediatePropagation();show(i,true);fieldsValid(panels[i]);return;}
     if(!navigator.onLine){event.preventDefault();event.stopImmediatePropagation();if(window.SL)SL.toast('Reconnect before confirming delivery. Your form is still here.',true);}
   },true);
   const photo=form.querySelector('[name=photo]'),image=form.querySelector('[data-pwa-photo-preview]'),label=form.querySelector('[data-pwa-photo-label]');
   photo.addEventListener('change',()=>{
     if(previewURL)URL.revokeObjectURL(previewURL);image.hidden=true;
     const file=photo.files[0];if(!file){label.textContent='Take a photo of the handover';return;}
     if(file.size>8*1048576||!['image/jpeg','image/png','image/webp'].includes(file.type)){photo.value='';label.textContent='Choose a JPEG, PNG or WebP under 8 MB';if(window.SL)SL.toast(label.textContent,true);return;}
     previewURL=URL.createObjectURL(file);image.src=previewURL;image.hidden=false;label.textContent='Photo added · tap to change';
   });
   addEventListener('pagehide',()=>{if(previewURL)URL.revokeObjectURL(previewURL);});
   show(0,false);
 });
})();
