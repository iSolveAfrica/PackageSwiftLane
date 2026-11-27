(() => {
    const el = document.querySelector('[data-sea-map]');
    if (!el || !window.jQuery?.fn.vectorMap || !window.jvm?.Map.maps.world_mill_en) return;
    const countries = jvm.Map.maps.world_mill_en.paths;
    const country = el.dataset.country.trim().toLowerCase();
    const code = Object.keys(countries).find(key => key.toLowerCase() === country || countries[key].name.toLowerCase() === country);
    const aliases = {usa:'US','united states of america':'US',uk:'GB'};
    jQuery(el).vectorMap({map:'world_mill_en',backgroundColor:'transparent',zoomOnScroll:false,
        selectedRegions:code || aliases[country] ? [code || aliases[country]] : [],
        regionStyle:{initial:{fill:'#d4d8d7',stroke:'#ffffff','stroke-width':0.6},selected:{fill:'#237e71'}}});
    el.style.backgroundImage='none';
    const card=el.closest('.sea-map'),button=card.querySelector('[data-sea-expand]');
    if (!card.requestFullscreen) return;
    button.hidden=false;
    button.addEventListener('click',async()=>{
        try {
            if(document.fullscreenElement) await document.exitFullscreen();
            else await card.requestFullscreen();
        } catch {button.hidden=true;}
    });
    document.addEventListener('fullscreenchange',()=>{
        button.textContent=document.fullscreenElement?'Close':'Expand';
        jQuery(el).vectorMap('get','mapObject').updateSize();
    });
})();
