
(function(){'use strict';
function mount(root,cfg){
 if(root.dataset.mounted)return;root.dataset.mounted='true';
 let map=null,starting=false,ready=false,active=-1,is3D=true,markers=[],disposed=false,attempt=0,requestController=null,loadTimer=null;
 const el=(tag,cls,text)=>{const x=document.createElement(tag);if(cls)x.className=cls;if(text)x.textContent=text;return x;};
 const branches=cfg.branches;
 const heading=el('div','tt-map-heading'),title=el('div');title.append(el('h2','','พบกันได้ที่ทำถูก'),el('p','','เลือกสถานที่ที่คุณต้องการติดต่อ'));heading.append(title,el('p','','ออฟฟิศและโรงงานของเรา'));
 const layout=el('div','tt-map-layout'),list=el('div','tt-map-branches'),stage=el('div','tt-map-stage'),canvas=el('div','tt-map-canvas');canvas.setAttribute('aria-label','แผนที่ที่ตั้งบริษัททำถูก');
 const details=el('div','tt-map-details'),detailTitle=el('strong'),address=el('p'),direction=el('a','tt-map-directions','ดูเส้นทางบน Google Maps ↗');direction.target='_blank';direction.rel='noopener noreferrer';details.append(detailTitle,address,direction);
 const buttons=branches.map((b,i)=>{const btn=el('button','tt-branch');btn.type='button';const copy=el('span');copy.append(el('strong','',b.name),el('small','',b.kind));btn.append(el('span','tt-branch-num',String(i+1).padStart(2,'0')),copy);btn.onclick=()=>select(i);list.append(btn);return btn;});list.append(details);
 const tools=el('div','tt-map-tools'),all=el('button','','ดูทั้งหมด'),flat=el('button','','2D'),three=el('button','','3D');[all,flat,three].forEach(b=>{b.type='button';tools.append(b);});
 const caption=el('div','tt-map-caption','เลือกสาขาเพื่อดูตำแหน่งและเส้นทาง');
 const gate=el('div','tt-map-gate');gate.innerHTML='<svg viewBox="0 0 64 64" fill="none" aria-hidden="true"><path d="M24 18 5 10v38l19 8 17-9 18 7V16l-18-7-17 9Z" stroke="currentColor" stroke-width="2"/><path d="M24 18v38M41 9v38" stroke="currentColor" stroke-width="2"/><path d="M42 24c0 8-10 17-10 17S22 32 22 24a10 10 0 1 1 20 0Z" fill="currentColor" stroke="white" stroke-width="2"/><circle cx="32" cy="24" r="3" fill="white"/></svg>';
 const gateTitle=el('strong','','แวะมาคุยเรื่องงานพิมพ์กัน'),message=el('p','','ดูที่ตั้งออฟฟิศและโรงงาน พร้อมเลือกเส้นทางที่สะดวกสำหรับคุณ'),load=el('button','tt-map-load','เปิดแผนที่');load.type='button';gate.setAttribute('role','status');gate.setAttribute('aria-live','polite');gate.append(gateTitle,message,load);stage.append(canvas,tools,caption,gate);layout.append(list,stage);root.append(heading,layout);
 function token(){return cfg.token||'';}
 function camera(i){const b=branches[i];if(!ready||!b.coordinates)return;map.flyTo({center:b.modelCoordinates||b.coordinates,zoom:b.model?(b.modelZoom||19):16.5,pitch:is3D?(b.modelPitch??58):0,bearing:b.model?(b.modelBearing??25):0,duration:matchMedia('(prefers-reduced-motion: reduce)').matches?0:1400});}
 function select(i){active=i;if(ready)map.setConfigProperty('basemap','show3dBuildings',is3D);direction.hidden=false;all.setAttribute('aria-pressed','false');buttons.forEach((b,j)=>b.setAttribute('aria-pressed',String(j===i)));markers.forEach(({marker,index})=>marker.getElement().setAttribute('aria-pressed',String(index===i)));const b=branches[i];detailTitle.textContent=b.name;address.textContent=b.address;direction.href=b.url;caption.textContent=b.coordinates?(b.model?'โมเดล'+b.name+' • สัดส่วนและแนววางโดยประมาณ':b.name):'กำลังยืนยันตำแหน่งสาขานี้';camera(i);}
 function locationBounds(){const bounds=new mapboxgl.LngLatBounds();branches.filter(b=>b.coordinates).forEach(b=>bounds.extend(b.modelCoordinates||b.coordinates));return bounds;}
 function overviewOptions(){const height=stage.clientHeight||480,width=stage.clientWidth||780,vertical=height*0.28;return {padding:{top:vertical+44,bottom:Math.max(24,vertical-44),left:width*0.22,right:width*0.22},maxZoom:15,retainPadding:false};}
 function overview(duration=900){active=-1;if(ready)map.setConfigProperty('basemap','show3dBuildings',is3D);buttons.forEach(b=>b.setAttribute('aria-pressed','false'));markers.forEach(({marker})=>marker.getElement().setAttribute('aria-pressed','false'));all.setAttribute('aria-pressed','true');detailTitle.textContent='ออฟฟิศและโรงงานทั้ง 3 แห่ง';address.textContent='คลิกหมุดบนแผนที่ หรือเลือกสาขาเพื่อดูรายละเอียดและเส้นทาง';direction.hidden=true;caption.textContent='ภาพรวมทุกสาขา • คลิกหมุดเพื่อดูสถานที่';if(!ready)return;map.fitBounds(locationBounds(),{...overviewOptions(),pitch:0,bearing:0,duration:matchMedia('(prefers-reduced-motion: reduce)').matches?0:duration});}
 function mode(value){is3D=value;flat.setAttribute('aria-pressed',String(!value));three.setAttribute('aria-pressed',String(value));if(ready){map.setConfigProperty('basemap','show3dObjects',value);map.setConfigProperty('basemap','show3dBuildings',value);branches.filter(b=>b.model).forEach(b=>{const id='tt-model-'+b.id;if(map.getLayer(id))map.setLayoutProperty(id,'visibility',value?'visible':'none');});if(active>=0)camera(active);}}
 all.onclick=()=>overview();flat.onclick=()=>mode(false);three.onclick=()=>mode(true);
 function setState(value){root.dataset.mapState=value;stage.setAttribute('aria-busy',String(value==='checking'||value==='loading'));}
 function cancelPending(){if(loadTimer!==null){clearTimeout(loadTimer);loadTimer=null;}if(requestController){requestController.abort();requestController=null;}}
 function reportError(reason='network'){
  attempt++;cancelPending();starting=false;ready=false;markers=[];
  const failedMap=map;map=null;if(failedMap)failedMap.remove();
  canvas.hidden=true;tools.hidden=true;caption.hidden=true;gate.hidden=false;
  gateTitle.textContent='ไม่สามารถแสดงแผนที่ได้';
  const messages={missing:'ยังไม่ได้ตั้งค่าการเชื่อมต่อแผนที่ กรุณาเลือกสาขาแล้วกดดูเส้นทางบน Google Maps',invalid:'การตั้งค่าแผนที่ไม่ถูกต้อง กรุณาเลือกสาขาแล้วกดดูเส้นทางบน Google Maps',denied:'ไม่สามารถเชื่อมต่อบริการแผนที่ได้ กรุณาเลือกสาขาแล้วกดดูเส้นทางบน Google Maps',empty:'ไม่ได้รับข้อมูลแผนที่ กรุณาลองใหม่ หรือใช้ปุ่ม Google Maps ของแต่ละสาขา',timeout:'บริการแผนที่ไม่ตอบสนอง กรุณาลองใหม่ หรือใช้ปุ่ม Google Maps ของแต่ละสาขา',unsupported:'อุปกรณ์นี้ไม่รองรับแผนที่ 3D กรุณาใช้ปุ่ม Google Maps ของแต่ละสาขา',network:'โหลดข้อมูลแผนที่ไม่สำเร็จ กรุณาลองใหม่ หรือใช้ปุ่ม Google Maps ของแต่ละสาขา'};
  message.textContent=messages[reason]||messages.network;load.textContent='ลองใหม่';load.disabled=false;setState('error');
 }
 async function validateToken(value,controller){
  const timeout=setTimeout(()=>controller.abort(),10000);
  try{
   const response=await fetch('https://api.mapbox.com/styles/v1/mapbox/standard?access_token='+encodeURIComponent(value),{signal:controller.signal,credentials:'omit'});
   if(!response.ok)throw Object.assign(new Error('Mapbox unavailable'),{reason:[401,403].includes(response.status)?'denied':'network'});
   let data;try{data=await response.json();}catch(error){throw Object.assign(new Error('Empty map response'),{reason:'empty'});}
   if(!data||data.version!==8||(!(Array.isArray(data.layers)&&data.layers.length)&&!(Array.isArray(data.imports)&&data.imports.length)))throw Object.assign(new Error('Invalid map response'),{reason:'empty'});
  }finally{clearTimeout(timeout);}
 }
 async function start(){
  if(disposed||starting||map)return;
  if(!token()){reportError('missing');return;}
  if(typeof token()!=='string'||!/^pk\.[A-Za-z0-9._-]+$/.test(token())){reportError('invalid');return;}
  if(!window.mapboxgl||!mapboxgl.supported()){reportError('unsupported');return;}
  const run=++attempt;const controller=new AbortController();requestController=controller;
  starting=true;load.disabled=true;load.textContent='กำลังตรวจสอบการเชื่อมต่อ…';gate.hidden=false;canvas.hidden=true;tools.hidden=true;caption.hidden=true;setState('checking');
  try{
   await validateToken(token(),controller);
   if(disposed||run!==attempt)return;
   requestController=null;canvas.hidden=false;setState('loading');load.textContent='กำลังเปิดแผนที่…';
 function modelRotation(b){return Number((cfg.modelRotations||{})[b.id]??b.modelRotation??(b.id==='udomsuk'?cfg.rotation:0))||0;}
 function clipFeature(b){const [lng,lat]=b.modelCoordinates||b.coordinates;const [x0,z0,x1,z1]=b.modelFootprint;const angle=modelRotation(b)*Math.PI/180,cos=Math.cos(angle),sin=Math.sin(angle);const corners=[[x0,z0],[x1,z0],[x1,z1],[x0,z1],[x0,z0]].map(([x,z])=>{const east=x*cos-z*sin,north=-x*sin-z*cos;return [lng+east/(111320*Math.cos(lat*Math.PI/180)),lat+north/111320];});return {type:'Feature',properties:{},geometry:{type:'Polygon',coordinates:[corners]}};}
 const style={version:8,imports:[{id:'basemap',url:'mapbox://styles/mapbox/standard',config:{theme:'default',lightPreset:'day',showPointOfInterestLabels:false,showTransitLabels:false,show3dObjects:is3D,show3dBuildings:is3D,show3dTrees:false,show3dFacades:false,colorLand:'#f7f7f5',colorWater:'#b9dce9',colorGreenspace:'#e4ecdf',colorRoads:'#e2e4e7',colorMotorways:'#cdd2d8',colorTrunks:'#d7dce1',colorBuildings:'#e0e2e5',colorIndustrial:'#eeefed',colorCommercial:'#f3f0ed',colorEducation:'#efeeeb',colorMedical:'#f5eeee',colorPlaceLabels:'#535b65',colorRoadLabels:'#69717b',colorAdminBoundaries:'#d5d8dc'}}],models:{},sources:{},layers:[]};
 style.sources['tt-model-clearance']={type:'geojson',data:{type:'FeatureCollection',features:branches.filter(b=>b.model&&b.modelFootprint).map(clipFeature)}};style.layers.push({id:'tt-model-clearance',type:'clip',source:'tt-model-clearance',slot:'top',minzoom:17,layout:{'clip-layer-scope':['basemap'],'clip-layer-types':['model']}});
 branches.filter(b=>b.model&&b.coordinates).forEach(b=>{const url=(cfg.modelUrls||{})[b.id]||(b.id==='udomsuk'?cfg.modelUrl:null);if(!url)return;const id='tt-model-'+b.id,source=id+'-point';style.models[id]=new URL(url,location.href).href;style.sources[source]={type:'geojson',data:{type:'Feature',properties:{},geometry:{type:'Point',coordinates:b.modelCoordinates||b.coordinates}}};style.layers.push({id,type:'model',slot:'top',source,minzoom:17,layout:{'model-id':id,visibility:is3D?'visible':'none'},paint:{'model-scale':[1,1,1],'model-rotation':[0,0,modelRotation(b)],'model-type':'location-indicator'}});});
 map=new mapboxgl.Map({container:canvas,accessToken:token(),style,bounds:locationBounds(),fitBoundsOptions:overviewOptions(),pitch:0,antialias:true,language:'th',cooperativeGestures:true});
 map.addControl(new mapboxgl.NavigationControl({visualizePitch:true}),'top-right');map.addControl(new mapboxgl.FullscreenControl(),'top-right');
 const currentMap=map;
 loadTimer=setTimeout(()=>{if(!disposed&&run===attempt&&!ready)reportError('timeout');},20000);
 currentMap.on('error',e=>{if(disposed||run!==attempt||map!==currentMap)return;reportError([401,403].includes(e.error&&e.error.status)?'denied':'network');});
 function finishLoading(){
  if(disposed||ready||run!==attempt||map!==currentMap)return;
  if(!currentMap.isStyleLoaded()||!currentMap.areTilesLoaded())return;
  ready=true;starting=false;currentMap.off('load',finishLoading);currentMap.off('idle',finishLoading);cancelPending();gate.hidden=true;tools.hidden=false;caption.hidden=false;setState('ready');
  branches.forEach((b,index)=>{if(!b.coordinates)return;const dot=el('button','tt-map-marker',String(index+1).padStart(2,'0'));dot.type='button';dot.setAttribute('aria-label',b.name);dot.onclick=()=>select(index);const marker=new mapboxgl.Marker({element:dot,anchor:'bottom',offset:b.model?[0,-28]:[0,0]}).setLngLat(b.modelCoordinates||b.coordinates).addTo(currentMap);markers.push({marker,index});});
  if(active>=0)select(active);else overview(0);
 }
 currentMap.on('load',finishLoading);currentMap.on('idle',finishLoading);
 }catch(error){if(!disposed&&run===attempt)reportError(error.name==='AbortError'||controller.signal.aborted?'timeout':error.reason||'network');}
 }
 load.onclick=start;mode(true);overview(0);
 const observer=new ResizeObserver(()=>{if(map)map.resize();});observer.observe(stage);
 root.ttMap={setToken(value){cfg.token=value;},load:start,destroy(){disposed=true;attempt++;cancelPending();observer.disconnect();if(map){map.remove();map=null;}root.replaceChildren();delete root.dataset.mounted;delete root.ttMap;}};
 setState('idle');if(cfg.autoLoad!==false)start();
}
window.TumtookContactMap={mount};
const selector='.tt-contact-map[data-config],.tt-contact-map[data-tt-config]';
function initialize(root){if(root.dataset.mounted)return;try{const inline=root.getAttribute('data-tt-config'),script=document.getElementById(root.dataset.config);const data=inline||(script&&script.textContent);if(data)mount(root,JSON.parse(data));}catch(error){root.textContent='กรุณาเลือกช่องทางติดต่ออื่น หรือดูเส้นทางบน Google Maps';}}
function scan(node){if(node.nodeType!==1)return;if(node.matches(selector))initialize(node);node.querySelectorAll(selector).forEach(initialize);}
function cleanup(node){if(node.nodeType!==1||node.isConnected)return;if(node.ttMap)node.ttMap.destroy();node.querySelectorAll('.tt-contact-map').forEach(root=>{if(root.ttMap)root.ttMap.destroy();});}
document.querySelectorAll(selector).forEach(initialize);
new MutationObserver(records=>{records.forEach(record=>{record.removedNodes.forEach(cleanup);record.addedNodes.forEach(scan);});}).observe(document.body,{childList:true,subtree:true});
})();
