(function(){
 'use strict';
 var module=document.getElementById('dl-module');
 if(module){function group(){document.querySelectorAll('[data-dl-module]').forEach(function(g){var on=g.dataset.dlModule===module.value;g.hidden=!on;g.querySelectorAll('input').forEach(function(i){i.disabled=!on;if(!on)i.checked=false;});});}module.addEventListener('change',group);group();}
 var cfg=document.getElementById('dl-map-config');if(!cfg)return;
 var error=document.getElementById('dl-map-error'),button=document.getElementById('dl-quote-button'),locationLabel=document.getElementById('dl-location'),input=document.getElementById('dl-place');
 function fail(message){error.hidden=false;error.textContent=message||'Google Maps could not load. Please retry or contact support.';button.disabled=true;input.value='';}
 if(!cfg.dataset.browserKey){fail('Google Maps is not configured. Please contact the office.');return;}
 window.gm_authFailure=function(){fail('Google Maps access is unavailable. Please contact the office.');};
 window.dlMapsReady=async function(){try{
   var maps=await google.maps.importLibrary('maps'),places=await google.maps.importLibrary('places'),markers=await google.maps.importLibrary('marker');
   var map=new maps.Map(document.getElementById('dl-map'),{center:{lat:5.6037,lng:-0.187},zoom:11,mapId:'DEMO_MAP_ID',streetViewControl:false,mapTypeControl:false});
   var marker=new markers.AdvancedMarkerElement({map:map}),geocoder=new google.maps.Geocoder();
   function select(place){input.value=place.id;marker.position=place.location;map.setCenter(place.location);map.setZoom(17);locationLabel.textContent=place.formattedAddress;error.hidden=true;button.disabled=false;}
   var search=new places.PlaceAutocompleteElement({includedRegionCodes:[cfg.dataset.country.toLowerCase()]});search.setAttribute('aria-label','Search delivery address or landmark');document.getElementById('dl-place-search').appendChild(search);
   search.addEventListener('gmp-select',async function(event){button.disabled=true;input.value='';try{var place=event.placePrediction.toPlace();await place.fetchFields({fields:['id','formattedAddress','location']});select(place);}catch(e){fail('Could not verify that location. Choose another Google Maps result.');}});
   search.addEventListener('gmp-error',function(){fail();});
   // A location change invalidates the old selection before another quote.
   search.addEventListener('input',function(){button.disabled=true;input.value='';});
   async function point(latLng){button.disabled=true;input.value='';try{var result=await geocoder.geocode({location:latLng});var p=result.results[0];if(!p)throw Error();select({id:p.place_id,formattedAddress:p.formatted_address,location:p.geometry.location});}catch(e){fail('Could not identify this pin. Search for a nearby building or landmark.');}}
   map.addListener('click',function(event){point(event.latLng);});
   document.getElementById('dl-locate').addEventListener('click',function(){if(!navigator.geolocation){fail('Location access is unavailable. Search for your address instead.');return;}navigator.geolocation.getCurrentPosition(function(p){point({lat:p.coords.latitude,lng:p.coords.longitude});},function(){fail('Location access was denied or timed out. Search for your address instead.');},{enableHighAccuracy:true,timeout:12000,maximumAge:30000});});
   if(cfg.dataset.origin)geocoder.geocode({placeId:cfg.dataset.origin}).then(function(r){if(r.results[0]&&!input.value)map.setCenter(r.results[0].geometry.location);}).catch(function(){});
 }catch(e){fail();}};
 var script=document.createElement('script');script.src='https://maps.googleapis.com/maps/api/js?'+new URLSearchParams({key:cfg.dataset.browserKey,loading:'async',callback:'dlMapsReady',v:'weekly'});script.async=true;script.onerror=function(){fail();};document.head.appendChild(script);
})();
