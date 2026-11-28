document.querySelectorAll('[data-dispatch-ai]').forEach(button=>button.addEventListener('click',async()=>{
 const form=button.closest('form'),status=form.querySelector('[data-dispatch-feedback]');
 button.disabled=true;status.textContent='Comparing riders…';
 try{
  const response=await fetch('ajax/sl/dispatch_ai.php',{method:'POST',headers:{'X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},body:new URLSearchParams({id:form.querySelector('[name=id]').value})});
  const reply=await response.json();if(!response.ok)throw Error(reply.message||'AI unavailable.');
  const select=form.querySelector('select');
  if(![...select.options].some(o=>o.value===String(reply.id)))throw Error('Rider availability changed. Refresh dispatch.');
  select.value=String(reply.id);status.textContent=reply.message;
 }catch(error){status.textContent=error.message;}finally{button.disabled=false;}
}));