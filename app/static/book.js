/* The journal as an open book. Reads the person's entries, lays them out page by page by counting ruled lines,
   and turns pages with a bending-paper animation. Only the pages near the one being read exist in the document.
   Copyright (c) JSW Software Designs */
(function(){
  var root=document.getElementById('bk'); if(!root) return;
  var C=JSON.parse(root.getAttribute('data-config')||'{}'), L=C.L||{};
  var PW=420, PH=620, SEG=9, PHS={s:616,m:620,l:612}, AHEAD=3;
  var reduced=window.matchMedia&&window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  function el(t,c,h){var e=document.createElement(t); if(c) e.className=c; if(h!=null) e.innerHTML=h; return e;}
  function esc(s){return String(s==null?'':s).replace(/[&<>"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]});}
  function store(k,v){try{ if(v===undefined) return localStorage.getItem(k); localStorage.setItem(k,v);}catch(_){} return null;}

  /* ---------- shell ---------- */
  root.innerHTML='<main class="desk"><div class="bookwrap" id="wrap"><div class="stage" id="stage"><div class="book" id="book"></div></div></div>'+
    '<div class="controls"><button class="arrow" id="prev" type="button" aria-label="'+esc(L.back_page)+'">&#8249;</button><div class="label" id="label" aria-live="polite"></div>'+
    '<button class="arrow" id="next" type="button" aria-label="'+esc(L.next_page)+'">&#8250;</button><button class="zoom" id="zoom" type="button"></button></div>'+
    '<input class="scrub" id="scrub" type="range" min="0" max="1" value="0" aria-label="'+esc(L.scrub)+'"></main>'+
    '<a class="home" href="'+esc(C.home)+'">&#8249; '+esc(L.home)+'</a>'+
    '<div class="tools"><button class="gear penb" id="wbtn" type="button" aria-haspopup="dialog" aria-controls="wsheet">'+esc(L.write)+'</button><button class="gear" id="gear" type="button" aria-haspopup="dialog" aria-controls="sheet">'+esc(L.customize)+'</button></div><div class="scrim" id="scrim" hidden></div>'+
    '<section class="wsheet" id="wsheet" role="dialog" aria-modal="true" aria-labelledby="wT" hidden><div class="wpaper">'+
      '<header class="whead"><h2 id="wT"></h2><button type="button" id="wX" aria-label="'+esc(L.close)+'">&times;</button></header>'+
      '<p class="wnote" id="wNote" hidden></p>'+
      '<div class="wrow" id="wDateRow"><label for="wDate">'+esc(L.w_date)+'</label><input type="date" id="wDate"></div>'+
      '<div id="wFeelBox"><p class="wh">'+esc(L.w_feel)+'</p><div class="wfaces" id="wFaces" role="radiogroup" aria-label="'+esc(L.w_feel)+'"></div>'+
      '<button type="button" class="wlink" id="wMore" aria-expanded="false">'+esc(L.w_more_feel)+'</button><div id="wChips" hidden></div></div>'+
      '<label class="sr" for="wTitle">'+esc(L.w_title)+'</label><input id="wTitle" class="wtitle" maxlength="200" placeholder="'+esc(L.w_title)+'" autocomplete="off">'+
      '<label class="sr" for="wBody">'+esc(L.write_title)+'</label><textarea id="wBody" class="wbody" rows="8"></textarea>'+
      '<div class="wtools" id="wPhotoBox"><label class="wbtn" for="wPhotos">'+esc(L.w_photos)+'</label><input id="wPhotos" class="sr" type="file" accept="image/*" multiple>'+
        '<label class="wbtn" for="wCam">'+esc(L.w_camera)+'</label><input id="wCam" class="sr" type="file" accept="image/*" capture="environment"></div>'+
      '<div class="wthumbs" id="wThumbs"></div>'+
      '<div class="wrow" id="wTripRow"><label for="wTrip">'+esc(L.w_trip)+'</label><select id="wTrip"></select></div>'+
      '<p class="wmsg" id="wMsg" role="status" aria-live="polite"></p>'+
      '<div class="wfoot"><button type="button" class="wbtn wdel" id="wDel" hidden>'+esc(L.w_delete)+'</button><button type="button" class="wbtn wsave" id="wSave">'+esc(L.w_save)+'</button></div>'+
    '</div></section>'+
    '<section class="sheet" id="sheet" role="dialog" aria-modal="true" aria-labelledby="shT" hidden><header><h2 id="shT">'+esc(L.customize)+'</h2><button type="button" id="shX" aria-label="'+esc(L.close)+'">&times;</button></header>'+
    '<h3>'+esc(L.cust_cover)+' <span id="thName"></span></h3><div class="themes" id="themes" role="group"></div>'+
    '<h3>'+esc(L.cust_style)+'</h3><div class="themes fonts" id="fonts" role="group"></div>'+
    '<h3>'+esc(L.cust_size)+' <span id="szName"></span></h3><div class="seg3" id="sizes" role="group"></div></section>'+
    '<p class="measure" id="meas" aria-hidden="true"></p>';
  var book=document.getElementById('book'), wrap=document.getElementById('wrap'), stage=document.getElementById('stage'), meas=document.getElementById('meas');
  var labelEl=document.getElementById('label'), prevB=document.getElementById('prev'), nextB=document.getElementById('next'), zoomB=document.getElementById('zoom'), scrub=document.getElementById('scrub');

  /* ---------- state ---------- */
  var DATA=null, pages=[], leaves=[], P=[], cur=0, nLeaf=0, MAXCUR=0, busy=false, isOpen=true, scaleNow=1, focus='both', drag=null;
  var castL=el('div','castL'), castR=el('div','castR');

  /* ---------- laying text out by ruled lines ---------- */
  function lineH(){ return parseFloat(getComputedStyle(meas).lineHeight)||31; }
  function textH(s){ meas.textContent=s; return meas.offsetHeight; }
  /* the longest start of the text that fits in `cap` lines; returns [fits, remainder] */
  function takeLines(text,cap){
    var lh=lineH(), max=cap*lh+1; if(textH(text)<=max) return [text,''];
    var tk=text.split(/(\s+)/), lo=1, hi=tk.length-1;
    while(lo<hi){ var mid=(lo+hi+1)>>1; if(textH(tk.slice(0,mid).join('').replace(/\s+$/,''))<=max) lo=mid; else hi=mid-1; }
    return [tk.slice(0,lo).join('').replace(/\s+$/,''), tk.slice(lo).join('').replace(/^\s+/,'')];
  }
  function paginate(){
    var N=Math.round(PH/lineH()), out=[];
    DATA.entries.forEach(function(e){
      var rest=String(e.body||'').replace(/\s+$/,''), photos=e.photos.slice(), first=true, guard=0;
      do{
        var ph=photos.length?photos.shift():null;
        var cap=Math.max(2, N-1-2-1-(first?1:0)-(ph?5:0)), piece='';
        if(rest){ var r=takeLines(rest,cap); piece=r[0]; rest=r[1]; }
        out.push({k:'day',e:e,first:first,photo:ph,text:piece}); first=false;
      } while((rest||photos.length)&&++guard<400);
    });
    return out;
  }

  /* ---------- page contents ---------- */
  function pageInner(pg,idx){
    var no='<div class="pno">'+(idx>2?idx-2:'')+'</div>';
    if(pg.k==='cover') return '<div class="cvr"><i class="stitch"></i><div class="label"><div class="emb"></div><div class="t1">'+esc(L.cover_t1)+'</div><div class="t2">'+esc(L.cover_t2)+'</div><div class="nm">'+esc(C.name)+'</div><div class="cap">'+esc(L.cover_cap)+'</div></div><div class="band"></div><div class="shine"></div></div>';
    if(pg.k==='endpaper') return '<div class="endp"><div class="own"><small>'+esc(L.belongs)+'</small><div class="nm">'+esc(C.name)+'</div><small>'+esc(String(L.started||'').replace('{month}',DATA.started))+'</small></div></div>';
    if(pg.k==='title') return '<div class="page"><div class="ttl"><div class="orn">&#10086;</div><h1>'+esc(L.my_journal)+'</h1><p>'+esc(L.tagline)+'</p><div class="orn">&#10086;</div></div></div>';
    if(pg.k==='blank') return '<div class="page"><div class="head"></div>'+no+'</div>';
    if(pg.k==='today') return '<div class="page"><div class="head"><div class="date">'+esc(DATA.todayMd)+'<small>'+esc(DATA.todayDow)+' &middot; '+esc(L.today)+'</small></div></div><div class="tdy"><p>'+esc(L.today_hint)+'</p><button type="button" class="tdyb" data-w="'+esc(DATA.today)+'">'+esc(L.write_open)+'</button>'+pendingHtml()+'</div>'+no+'</div>';
    var e=pg.e;
    return '<div class="page"><div class="head"><div class="date">'+esc(e.md)+'<small>'+esc(e.dow)+(pg.first?'':' &middot; '+esc(L.continued))+'</small></div><div class="mood">'+(pg.first?esc(e.mood):'')+(pg.first?'<button type="button" class="pedit" data-e="'+e.id+'" aria-label="'+esc(L.w_edit)+'">&#9998;</button>':'')+'</div></div>'+
      (pg.first?'<h2 class="etitle">'+esc(e.title||e.trip||'')+'</h2>':'')+
      (pg.photo?'<div class="pslot"><div class="photo"><i data-bg="'+esc(C.photo+pg.photo.id+'/thumb?v='+pg.photo.v)+'"></i></div></div>':'')+
      '<p class="ptext">'+esc(pg.text)+'</p>'+no+'</div>';
  }
  function makeBox(idx,side){
    var k=pages[idx].k, b=el('div','pgbox pg-'+side+(k==='cover'?' cvbox':(k==='endpaper'?' endbox':'')), pageInner(pages[idx],idx));
    Array.prototype.forEach.call(b.querySelectorAll('[data-bg]'),function(i){ i.style.backgroundImage='url("'+i.getAttribute('data-bg')+'")'; });
    return b;
  }

  /* ---------- leaves: made when the reader gets near them ---------- */
  function ensure(j){
    if(leaves[j]) return leaves[j];
    var Lf=el('article','leaf'), rig=el('div','rigid'), f=makeBox(2*j,'right'), b=makeBox(2*j+1,'left');
    rig.appendChild(f); rig.appendChild(b); Lf.appendChild(rig); var fl=el('div','flex'); Lf.appendChild(fl); book.appendChild(Lf);
    return leaves[j]={el:Lf,rig:rig,flex:fl,front:f,back:b,segs:null};
  }
  function syncWindow(){
    for(var j=0;j<nLeaf;j++){
      var near=(j>=cur-AHEAD && j<=cur+AHEAD);
      if(near) ensure(j);
      else if(leaves[j] && !leaves[j].el.classList.contains('flexing')){ book.removeChild(leaves[j].el); leaves[j]=null; }
    }
  }
  /* A leaf that is moving is rebuilt as nested strips. Each strip is turned a little more or less than the one before it,
     so the paper bends: the free edge lags at first, then leads as the page falls over. */
  function buildFlex(Lf){
    var w=PW/SEG; Lf.flex.innerHTML=''; Lf.segs=[]; var parent=Lf.flex;
    for(var i=0;i<SEG;i++){
      var s=el('div','seg'); s.style.left=(i===0?0:w)+'px'; s.style.width=(w+1)+'px';
      var fw=el('div','win fw'), bw=el('div','win bw'), fc=Lf.front.cloneNode(true), bc=Lf.back.cloneNode(true);
      fc.style.left=(-i*w)+'px'; bc.style.left=(-(PW-(i+1)*w))+'px';
      var fs=el('div','sh'), bs=el('div','sh');
      fw.appendChild(fc); fw.appendChild(fs); bw.appendChild(bc); bw.appendChild(bs);
      s.appendChild(fw); s.appendChild(bw); parent.appendChild(s); parent=s; Lf.segs.push({s:s,fs:fs,bs:bs});
    }
  }
  function pose(Lf,p){
    var th=p*180, rel=Math.sin(2*Math.PI*p)*0.5*(reduced?0:12);
    Lf.flex.style.transform='rotateY('+(-th).toFixed(2)+'deg)';
    for(var i=0;i<SEG;i++){
      var sg=Lf.segs[i]; if(i>0) sg.s.style.transform='rotateY('+rel.toFixed(2)+'deg)';
      var cum=th-i*rel, a=Math.abs(Math.sin(cum*Math.PI/180))*.2;
      sg.fs.style.opacity=a.toFixed(3); sg.bs.style.opacity=(a*.9).toFixed(3);
    }
  }
  function rigidPose(){
    for(var j=0;j<nLeaf;j++){ var Lf=leaves[j]; if(!Lf||Lf.el.classList.contains('flexing')) continue;
      Lf.el.style.transform='rotateY('+(-P[j]*180)+'deg)';
      Lf.el.style.zIndex=(j<cur?100+j:100-j);
      Lf.el.style.visibility=(j<cur-2||j>cur+1)?'hidden':'visible'; }
  }
  function startFlex(j){ var Lf=ensure(j); buildFlex(Lf); Lf.el.classList.add('flexing'); Lf.el.style.transform='none'; Lf.el.style.zIndex=1000; Lf.el.style.visibility='visible'; }
  function endFlex(j){ var Lf=leaves[j]; if(!Lf) return; Lf.el.classList.remove('flexing'); Lf.flex.innerHTML=''; Lf.segs=null; }
  function show(j){
    var Lf=leaves[j], p=P[j]; if(!Lf||!Lf.segs) return; pose(Lf,p);
    if(j===0) setOpen(p>.5);
    var s=Math.sin(Math.PI*p); castR.style.opacity=(s*(1-p)*1.25).toFixed(3); castL.style.opacity=(s*p*1.25).toFixed(3);
  }
  function setOpen(o){ if(o===isOpen) return; isOpen=o; book.classList.toggle('closed',!o); layout(); }
  function clearCast(){castL.style.opacity=0; castR.style.opacity=0;}

  function abbr(md){ var a=String(md||'').split(' '); if(a[0]&&a[0].length>3) a[0]=a[0].slice(0,3); return a.join(' '); }
  function nm(p){ return !p?'':(p.k==='day'?abbr(p.e.md):(p.k==='today'?abbr(DATA.todayMd):'')); }
  function settle(){
    cur=0; for(var j=0;j<nLeaf;j++){ if(P[j]>=1) cur=j+1; } cur=Math.min(cur,MAXCUR);
    syncWindow(); rigidPose(); clearCast(); setOpen(cur!==0);
    var l=pages[2*cur-1], r=pages[2*cur];
    labelEl.textContent=(cur===0)?L.cover:(cur===1)?L.title_page:(nm(l)&&nm(r)?nm(l)+'  ·  '+nm(r):(nm(r)||nm(l)));
    prevB.disabled=cur===0; nextB.disabled=cur===MAXCUR; scrub.value=cur;
  }
  function setCur(c){ c=Math.max(0,Math.min(MAXCUR,c)); for(var j=0;j<nLeaf;j++) P[j]=j<c?1:0; settle(); }
  function ease(t){return t<.5?4*t*t*t:1-Math.pow(-2*t+2,3)/2;}
  function easeOut(t){return 1-Math.pow(1-t,3);}
  function animate(j,target,ms,fn){
    var Lf=ensure(j); if(!Lf.segs) startFlex(j);
    var from=P[j], d=Math.abs(target-from), t0=performance.now(); busy=true;
    ms=reduced?140:ms*Math.max(.4,d); fn=fn||ease;
    (function step(t){ var k=Math.min(1,(t-t0)/ms); P[j]=from+(target-from)*fn(k); show(j);
      if(k<1) requestAnimationFrame(step); else{ P[j]=target; endFlex(j); busy=false; settle(); } })(t0);
  }
  function go(dir){
    if(busy) return;
    if(dir>0 && cur<MAXCUR) animate(cur,1,1050,ease);
    else if(dir<0 && cur>0) animate(cur-1,0,1050,ease);
  }

  /* ---------- camera: both pages, or one page filling the screen on a narrow phone ---------- */
  function twoPageScale(){ var W=wrap.clientWidth, H=wrap.clientHeight; return Math.min((W-34)/(PW*2),(H-34)/PH); }
  function narrow(){ return twoPageScale() < 0.6; }
  function layout(){
    var W=wrap.clientWidth, H=wrap.clientHeight, s, tx, ty, gap=34;
    var sBoth=Math.min((W-gap)/(PW*2),(H-gap)/PH), sPage=Math.min((W-18)/PW,(H-gap)/PH);
    if(!isOpen){ s=(focus==='both')?sBoth:sPage; tx=W/2-PW*1.5*s; }
    else if(focus==='both'){ s=sBoth; tx=(W-PW*2*s)/2; }
    else{ s=sPage; var cx=(focus==='right'?PW*1.5:PW*.5); tx=W/2-cx*s; }
    ty=(H-PH*s)/2; scaleNow=s;
    stage.style.height=PH+'px';
    stage.style.transform='translate('+tx.toFixed(1)+'px,'+ty.toFixed(1)+'px) scale('+s.toFixed(4)+')';
    zoomB.style.display=(narrow()&&isOpen)?'':'none';
    zoomB.textContent=focus==='both'?L.zoom_page:L.zoom_both;
  }

  /* ---------- building the whole book (again, when text size or style changes) ---------- */
  function rebuild(anchorId,last){
    pages=[{k:'cover'},{k:'endpaper'},{k:'title'}].concat(paginate());
    if(pages.length%2===1) pages.push({k:'blank'});
    pages.push({k:'today'}); pages.push({k:'blank'});
    nLeaf=pages.length/2; MAXCUR=nLeaf-1; leaves=[]; book.innerHTML='';
    book.appendChild(el('div','cover')); book.appendChild(el('div','blockL')); book.appendChild(el('div','blockR')); book.appendChild(castL); book.appendChild(castR);
    var c=MAXCUR;
    if(anchorId){ var hit=-1; for(var i=3;i<pages.length;i++){ if(pages[i].k==='day'&&pages[i].e.id===anchorId){ if(last||pages[i].first) hit=i; if(!last&&hit>=0) break; } } if(hit>=0) c=Math.max(1,Math.min(MAXCUR,Math.ceil(hit/2))); }
    P=[]; for(var j=0;j<nLeaf;j++) P[j]=j<c?1:0;
    scrub.max=MAXCUR; isOpen=true; book.classList.remove('closed'); cur=c; busy=false;
    focus=narrow()?'right':'both'; settle(); layout();
  }
  function anchorNow(){ var r=pages[2*cur], l=pages[2*cur-1], p=(r&&r.k==='day')?r:((l&&l.k==='day')?l:null); return p?p.e.id:0; }

  /* ---------- input ---------- */
  prevB.onclick=function(){go(-1)}; nextB.onclick=function(){go(1)};
  scrub.addEventListener('input',function(){ if(!busy) setCur(parseInt(scrub.value,10)||0); });
  document.addEventListener('keydown',function(e){ if(/INPUT|TEXTAREA|SELECT/.test((e.target||{}).tagName||'')) return; if(e.key==='ArrowLeft') go(-1); if(e.key==='ArrowRight') go(1); });
  book.addEventListener('pointerdown',function(e){
    if(busy||e.target.closest('textarea,input,button,label,a')) return;
    drag={x:e.clientX,y:e.clientY,mode:null,vx:0,lx:e.clientX,lt:performance.now(),id:e.pointerId};
  });
  book.addEventListener('pointermove',function(e){
    if(!drag) return;
    var dx=e.clientX-drag.x, dy=e.clientY-drag.y, now=performance.now();
    var span=Math.max(160,PW*scaleNow*(focus==='both'?1.5:1.25));
    if(!drag.mode){
      if(Math.abs(dx)>8 && Math.abs(dx)>Math.abs(dy)*1.15){
        if(dx>0 && cur>0) drag.mode={j:cur-1,back:true};
        else if(dx<0 && cur<MAXCUR) drag.mode={j:cur,back:false};
        else drag.mode={rubber:true};
        if(drag.mode.j!==undefined) startFlex(drag.mode.j);
        try{book.setPointerCapture(drag.id);}catch(_){}
      } else if(Math.abs(dy)>10){ drag=null; return; } else return;
    }
    var dt=Math.max(1,now-drag.lt); drag.vx=(e.clientX-drag.lx)/dt; drag.lx=e.clientX; drag.lt=now;
    var mm=drag.mode; if(mm.rubber) return;
    P[mm.j]=mm.back?Math.min(1,Math.max(0,1-dx/span)):Math.min(1,Math.max(0,-dx/span));
    show(mm.j);
  });
  function endDrag(){
    if(!drag) return; var mm=drag.mode, vx=drag.vx; drag=null; if(!mm||mm.rubber) return;
    var p=P[mm.j], t;
    if(mm.back) t=(vx>.5||(p<.6&&vx>-.3))?0:1; else t=(vx<-.5||(p>.4&&vx<.3))?1:0;
    animate(mm.j,t,820,easeOut);
  }
  book.addEventListener('pointerup',endDrag); book.addEventListener('pointercancel',endDrag);
  zoomB.onclick=function(){ focus=(focus==='both')?'right':'both'; layout(); };
  book.addEventListener('click',function(e){
    if(!busy && cur===0 && e.target.closest('.cvbox')){ go(1); return; }
    if(!narrow()||busy) return; if(e.target.closest('textarea,input,button,label,a')) return;
    if(focus==='both'){ var r=book.getBoundingClientRect(); focus=(e.clientX<r.left+r.width/2)?'left':'right'; layout(); }
  });
  window.addEventListener('resize',function(){ focus=narrow()?(focus==='both'?'right':focus):'both'; layout(); });
  window.addEventListener('orientationchange',function(){ setTimeout(function(){ focus=narrow()?'right':'both'; layout(); },250); });

  /* ---------- customize ---------- */
  var THEMES=[['classic','#FBF7EE'],['antique','#EBDDB8'],['midnight','#1F2A3A'],['garden','#F1F5E6'],['sea','#F3F8FB'],['notebook','#FFFFFF'],['blush','#FFF6F5'],['lavender','#F6F2FB'],['zen','#F6F6F1'],['sunrise','#FFFBEA'],['cabin','#F3EAD7']];
  var FONTS=[['auto',''],['newsreader',"'Newsreader',serif"],['garamond',"'EB Garamond',serif"],['merri',"'Merriweather',serif"],['nunito',"'Nunito',sans-serif"],['elite',"'Special Elite',monospace"],['fell',"'IM Fell English',serif"],['caveat',"'Caveat',cursive"],['kalam',"'Kalam',cursive"]];
  var FONTFACE={newsreader:'Newsreader',garamond:'EB Garamond',merri:'Merriweather',nunito:'Nunito',elite:'Special Elite',fell:'IM Fell English',caveat:'Caveat',kalam:'Kalam'};
  var THEMEFONT={antique:'fell',zen:'garamond',sunrise:'caveat'}, SZ=[['s','15px'],['m','19px'],['l','24px']];
  var curTheme='classic', fsel='auto', szSel='m';
  var tw=document.getElementById('themes'), fw=document.getElementById('fonts'), sizesEl=document.getElementById('sizes');
  function effFont(){ return fsel==='auto'?(THEMEFONT[curTheme]||'newsreader'):fsel; }
  function pressed(box,k){ Array.prototype.forEach.call(box.children,function(b){ b.setAttribute('aria-pressed',b.dataset.k===k?'true':'false'); }); }
  /* new fonts and sizes change where lines break, so the pages are laid out again once the font is ready */
  var relayTimer=null;
  function relayout(){
    clearTimeout(relayTimer);
    relayTimer=setTimeout(function(){
      var f=FONTFACE[effFont()]||'Newsreader';
      Promise.all(['400','500'].map(function(w){ return document.fonts?document.fonts.load(w+' 20px "'+f+'"'):null; })).then(function(){ rebuild(anchorNow()); },function(){ rebuild(anchorNow()); });
    },30);
  }
  function applyFont(){ root.setAttribute('data-font',effFont()); pressed(fw,fsel); }
  function setFont(k){ fsel=k; store('mw-font',k); applyFont(); relayout(); }
  function setSize(k,noRelayout){
    szSel=k; if(k==='m') root.removeAttribute('data-size'); else root.setAttribute('data-size',k); PH=PHS[k];
    document.getElementById('szName').textContent='· '+L['size_'+k]; pressed(sizesEl,k); store('mw-size',k); if(!noRelayout) relayout(); }
  function setTheme(k,noRelayout){
    var before=effFont(); if(k==='classic') root.removeAttribute('data-theme'); else root.setAttribute('data-theme',k); curTheme=k; applyFont();
    document.getElementById('thName').textContent='· '+L['theme_'+k]; pressed(tw,k); store('mw-theme',k); if(!noRelayout&&before!==effFont()) relayout(); }
  FONTS.forEach(function(f){ var b=el('button',null,(f[1]?'<b style="font-family:'+f[1]+'">Aa</b> ':'')+esc(L['font_'+f[0]])); b.type='button'; b.dataset.k=f[0]; b.onclick=function(){setFont(f[0]);}; fw.appendChild(b); });
  SZ.forEach(function(z){ var b=el('button',null,'<b style="font-size:'+z[1]+'">A</b> '+esc(L['size_'+z[0]])); b.type='button'; b.dataset.k=z[0]; b.onclick=function(){setSize(z[0]);}; sizesEl.appendChild(b); });
  THEMES.forEach(function(t){ var b=el('button',null,'<i style="background:'+t[1]+'"></i>'+esc(L['theme_'+t[0]])); b.type='button'; b.dataset.k=t[0]; b.setAttribute('aria-label',L['theme_'+t[0]]); b.title=L['theme_'+t[0]]; b.onclick=function(){setTheme(t[0]);}; tw.appendChild(b); });
  (function(){ var sh=document.getElementById('sheet'), sc=document.getElementById('scrim'), g=document.getElementById('gear');
    function open(){sh.hidden=false; sc.hidden=false; document.getElementById('shX').focus();} function close(){sh.hidden=true; sc.hidden=true; g.focus();}
    g.onclick=function(){ sh.hidden?open():close(); }; sc.onclick=close; document.getElementById('shX').onclick=close;
    document.addEventListener('keydown',function(e){ if(e.key==='Escape'&&!sh.hidden) close(); }); })();


  /* ---------- writing ---------- */
  var W={open:false,mode:'new',eid:0,files:[],mood:0,mood0:0,feel:{},feelDirty:false,busy:false};
  var wsheet=document.getElementById('wsheet'), wBody=document.getElementById('wBody'), wTitle=document.getElementById('wTitle'), wDate=document.getElementById('wDate'), wMsg=document.getElementById('wMsg'),
      wSave=document.getElementById('wSave'), wDel=document.getElementById('wDel'), wThumbs=document.getElementById('wThumbs'), wTrip=document.getElementById('wTrip');
  function $w(id){return document.getElementById(id);}
  function fmt(t,o){ return String(t||'').replace(/\{(\w+)\}/g,function(_,k){return o[k]!=null?o[k]:'';}); }
  function ymdLong(d){ try{ var p=d.split('-'); return new Date(+p[0],+p[1]-1,+p[2]).toLocaleDateString(undefined,{weekday:'long',month:'long',day:'numeric'}); }catch(e){ return d; } }
  function setMsg(t,bad){ wMsg.textContent=t||''; wMsg.className='wmsg'+(bad?' bad':''); }

  /* A small outbox: anything written is kept on the phone first, then sent. If the connection drops it waits and is sent later. */
  var DBH=null, memOut={};
  function idb(){ return new Promise(function(res){ if(DBH) return res(DBH); try{ var r=indexedDB.open('mw-book',1); r.onupgradeneeded=function(){ r.result.createObjectStore('out',{keyPath:'cid'}); }; r.onsuccess=function(){ DBH=r.result; res(DBH); }; r.onerror=function(){ res(null); }; }catch(e){ res(null); } }); }
  function oPut(rec){ memOut[rec.cid]=rec; return idb().then(function(db){ if(!db) return; return new Promise(function(res){ try{ var t=db.transaction('out','readwrite'); t.objectStore('out').put(rec); t.oncomplete=res; t.onerror=res; t.onabort=res; }catch(e){ res(); } }); }); }
  function oDel(cid){ delete memOut[cid]; return idb().then(function(db){ if(!db) return; return new Promise(function(res){ try{ var t=db.transaction('out','readwrite'); t.objectStore('out').delete(cid); t.oncomplete=res; t.onerror=res; t.onabort=res; }catch(e){ res(); } }); }); }
  function oAll(){ return idb().then(function(db){ if(!db) return Object.keys(memOut).map(function(k){return memOut[k];}); return new Promise(function(res){ try{ var r=db.transaction('out','readonly').objectStore('out').getAll(); r.onsuccess=function(){ res(r.result||[]); }; r.onerror=function(){ res([]); }; }catch(e){ res([]); } }); }); }
  var pending=[];
  function pendingHtml(){ if(!pending.length) return ''; return '<div class="pend"><b>'+esc(fmt(L.w_pending,{n:pending.length}))+'</b>'+pending.map(function(r){ return '<p>'+esc((r.body||r.title||'').slice(0,160))+'</p>'; }).join('')+'</div>'; }
  function refreshPending(){ return oAll().then(function(a){ pending=a.sort(function(x,y){return x.ts-y.ts;}); }); }
  function cid(){ return Date.now().toString(36)+Math.random().toString(36).slice(2,10); }

  function post(url,fd){ return fetch(url,{method:'POST',body:fd,credentials:'same-origin',headers:{'X-Requested-With':'fetch'}}); }
  function build(rec){
    var fd=new FormData(); fd.append('_csrf',C.csrf); fd.append('cid',rec.cid); fd.append('entry_date',rec.date); fd.append('title',rec.title||''); fd.append('body',rec.body||''); fd.append('ts',rec.ts);
    if(rec.mood) fd.append('mood',rec.mood); if(rec.feelDirty){ fd.append('feelings_set','1'); (rec.feelings||[]).forEach(function(f){ fd.append('feelings[]',f); }); }
    if(rec.trip) fd.append('event_id',rec.trip); (rec.files||[]).forEach(function(f){ fd.append('photos[]',f,f.name||'photo.jpg'); });
    return fd;
  }
  /* returns {ok,id} when saved, {retry:true} when the connection or server is down, {error} when the entry was refused */
  function sendOne(rec,again){
    return post(C.save,build(rec)).then(function(r){
      var ct=r.headers.get('content-type')||'';
      if(r.status===400&&ct.indexOf('json')<0&&!again){ return fetch(C.data,{credentials:'same-origin',cache:'no-store'}).then(function(x){return x.json();}).then(function(d){ if(d&&d.csrf) C.csrf=d.csrf; return sendOne(rec,true); }); }
      if(r.status>=500||r.status===0) return {retry:true};
      return r.json().then(function(j){ return j.ok?{ok:true,id:j.id,notes:j.notes||[]}:{error:j.error||L.w_fail}; },function(){ return {retry:true}; });
    },function(){ return {retry:true}; });
  }
  var flushing=false;
  function flush(){
    if(flushing) return Promise.resolve(); flushing=true;
    return refreshPending().then(function(){
      var chain=Promise.resolve(false), lastId=0;
      pending.slice().forEach(function(rec){ chain=chain.then(function(stop){ if(stop) return true; return sendOne(rec).then(function(r){
        if(r.retry) return true; return oDel(rec.cid).then(function(){ if(r.ok) lastId=r.id||lastId; return false; }); }); }); });
      return chain.then(function(){ return refreshPending(); }).then(function(){ flushing=false; return lastId; });
    }).catch(function(){ flushing=false; });
  }
  function reload(anchor){
    return fetch(C.data,{credentials:'same-origin',cache:'no-store'}).then(function(r){ if(!r.ok) throw 0; return r.json(); }).then(function(d){ DATA=d; if(d.csrf) C.csrf=d.csrf; return refreshPending(); }).then(function(){ rebuild(anchor||0,true); });
  }

  /* the writing page */
  function buildFaces(){
    var box=$w('wFaces'); box.innerHTML='';
    (DATA.faces||[]).forEach(function(f){ var b=el('button','wface','<span class="e" aria-hidden="true">'+esc(f.e)+'</span><span class="l">'+esc(f.l)+'</span>'); b.type='button'; b.setAttribute('role','radio'); b.dataset.v=f.v; b.setAttribute('aria-checked','false');
      b.onclick=function(){ W.mood=(W.mood===f.v&&W.mood0!==f.v)?W.mood0:f.v; paintFaces(); }; box.appendChild(b); });
    var ch=$w('wChips'); ch.innerHTML='';
    (DATA.catalog||[]).forEach(function(g){ ch.appendChild(el('p','wg',esc(g.g))); var row=el('div','wchips'); g.items.forEach(function(it){ var b=el('button','wchip',esc(it.l)); b.type='button'; b.dataset.k=it.k; b.setAttribute('aria-pressed','false');
      b.onclick=function(){ if(W.feel[it.k]) delete W.feel[it.k]; else W.feel[it.k]=1; W.feelDirty=true; paintChips(); }; row.appendChild(b); }); ch.appendChild(row); });
  }
  function paintFaces(){ Array.prototype.forEach.call($w('wFaces').children,function(b){ b.setAttribute('aria-checked',String(+b.dataset.v===W.mood)); }); }
  function paintChips(){ Array.prototype.forEach.call($w('wChips').querySelectorAll('.wchip'),function(b){ b.setAttribute('aria-pressed',W.feel[b.dataset.k]?'true':'false'); }); }
  function paintThumbs(){
    wThumbs.innerHTML='';
    W.files.forEach(function(f,i){ var u=URL.createObjectURL(f), d=el('div','wth'); var im=el('img'); im.src=u; im.alt=''; im.onload=function(){ try{URL.revokeObjectURL(u);}catch(e){} };
      var x=el('button',null,'&times;'); x.type='button'; x.setAttribute('aria-label',L.w_remove_photo); x.onclick=function(){ W.files.splice(i,1); paintThumbs(); }; d.appendChild(im); d.appendChild(x); wThumbs.appendChild(d); });
  }
  function draftKey(d){ return 'mw-bk-draft:'+d; }
  function readDraft(d){ try{ return JSON.parse(store(draftKey(d))||'null'); }catch(e){ return null; } }
  function saveDraft(){ if(W.mode!=='new') return; var b=wBody.value, t=wTitle.value; try{ if(b||t) localStorage.setItem(draftKey(wDate.value),JSON.stringify({b:b,t:t})); else localStorage.removeItem(draftKey(wDate.value)); }catch(e){} }
  var dTimer=null; function draftSoon(){ clearTimeout(dTimer); dTimer=setTimeout(saveDraft,400); }
  function loadFeel(day){
    W.mood=0; W.mood0=0; W.feel={}; W.feelDirty=false;
    var apply=function(m,fs){ W.mood0=m||0; W.mood=m||0; W.feel={}; (fs||[]).forEach(function(k){W.feel[k]=1;}); paintFaces(); paintChips(); };
    if(day===DATA.today){ apply(DATA.mood,DATA.feelings); return; }
    apply(0,[]);
    fetch(C.feel+'?day='+encodeURIComponent(day),{credentials:'same-origin',cache:'no-store'}).then(function(r){return r.json();}).then(function(j){ if(j&&j.ok&&wDate.value===day&&!W.feelDirty) apply(j.mood,j.feelings); }).catch(function(){});
  }
  function fillDay(day){
    var dr=readDraft(day); wBody.value=dr?dr.b:''; wTitle.value=dr?dr.t:'';
    var has=DATA.entries.some(function(e){return e.date===day&&!e.trip;});
    wBody.placeholder=has?L.w_ph_more:L.w_ph; wTitle.hidden=has; $w('wT').textContent=(day===DATA.today)?fmt(L.w_today,{date:ymdLong(day)}):fmt(L.w_day,{date:ymdLong(day)});
    wSave.textContent=L.w_save; loadFeel(day);
    setMsg(dr&&(dr.b||dr.t)?L.w_draft:'');
  }
  function openWrite(opt){
    if(!DATA||W.open) return; opt=opt||{}; W.open=true; W.files=[]; W.busy=false; W.eid=opt.eid||0; W.mode=W.eid?'edit':'new'; paintThumbs(); setMsg('');
    wsheet.hidden=false; document.body.style.overflow='hidden';
    var edit=W.mode==='edit';
    $w('wFeelBox').hidden=edit; $w('wDateRow').hidden=edit; $w('wTripRow').hidden=edit||!(DATA.trips&&DATA.trips.length); wDel.hidden=!edit; $w('wNote').hidden=!edit; $w('wNote').textContent=L.w_edit_hint;
    wTrip.innerHTML='<option value="">'+esc(L.w_trip_none)+'</option>'+(DATA.trips||[]).map(function(t){ return '<option value="'+t.id+'">'+esc(t.title)+'</option>'; }).join('');
    if(edit){
      var en=DATA.entries.filter(function(e){return e.id===W.eid;})[0]||{};
      wBody.value=en.body||''; wTitle.value=en.title||''; wTitle.hidden=false; wBody.placeholder=L.w_ph; $w('wT').textContent=fmt(L.w_day,{date:ymdLong(en.date||DATA.today)}); wSave.textContent=L.w_save_edit;
    } else {
      buildFaces(); wDate.max=DATA.today; wDate.value=opt.date||DATA.today; wTrip.value=''; $w('wChips').hidden=true; $w('wMore').setAttribute('aria-expanded','false'); $w('wMore').textContent=L.w_more_feel; fillDay(wDate.value);
    }
    setTimeout(function(){ try{ wBody.focus(); }catch(e){} },60);
  }
  function closeWrite(){ saveDraft(); W.open=false; wsheet.hidden=true; document.body.style.overflow=''; setMsg(''); }
  function doSave(){
    if(W.busy) return; var body=wBody.value.replace(/\s+$/,''), title=wTitle.value.trim();
    if(W.mode==='edit'){
      W.busy=true; wSave.disabled=true; setMsg(L.w_saving);
      var fd=new FormData(); fd.append('_csrf',C.csrf); fd.append('title',title); fd.append('body',body); W.files.forEach(function(f){ fd.append('photos[]',f,f.name||'photo.jpg'); });
      post(C.edit+W.eid,fd).then(function(r){return r.json();}).then(function(j){ W.busy=false; wSave.disabled=false; if(!j.ok){ setMsg(j.error||L.w_fail,true); return; } var id=W.eid; closeWrite(); reload(id); },function(){ W.busy=false; wSave.disabled=false; setMsg(L.w_fail,true); });
      return;
    }
    var feelings=Object.keys(W.feel), mood=(W.mood&&W.mood!==W.mood0)?W.mood:0;
    if(!body&&!title&&!W.files.length&&!mood&&!W.feelDirty){ setMsg(L.w_empty,true); return; }
    var rec={cid:cid(),date:wDate.value||DATA.today,title:title,body:body,ts:Math.floor(Date.now()/1000),mood:mood,feelings:feelings,feelDirty:W.feelDirty,trip:wTrip.value||'',files:W.files.slice()};
    W.busy=true; wSave.disabled=true; setMsg(L.w_saving);
    oPut(rec).then(function(){ return sendOne(rec); }).then(function(r){
      W.busy=false; wSave.disabled=false;
      if(r.ok){ return oDel(rec.cid).then(function(){ try{localStorage.removeItem(draftKey(rec.date));}catch(e){} closeWrite(); return reload(r.id); }); }
      if(r.retry){ try{localStorage.removeItem(draftKey(rec.date));}catch(e){} closeWrite(); return refreshPending().then(function(){ rebuild(0); }).then(function(){ toast(L.w_offline_saved); }); }
      return oDel(rec.cid).then(function(){ setMsg(r.error||L.w_fail,true); });
    });
  }
  var toastT=null; function toast(t){ var b=$w('wToast'); if(!b){ b=el('div','wtoast'); b.id='wToast'; b.setAttribute('role','status'); root.appendChild(b); } b.textContent=t; b.hidden=false; clearTimeout(toastT); toastT=setTimeout(function(){ b.hidden=true; },6000); }
  wSave.onclick=doSave; wDel.onclick=function(){ if(!W.eid) return; if(!confirm(L.w_delete_ask)) return; var fd=new FormData(); fd.append('_csrf',C.csrf); fd.append('delete','1');
    post(C.edit+W.eid,fd).then(function(r){return r.json();}).then(function(j){ if(j.ok){ closeWrite(); reload(0); } else setMsg(j.error||L.w_fail,true); },function(){ setMsg(L.w_fail,true); }); };
  $w('wX').onclick=closeWrite; $w('wbtn').onclick=function(){ openWrite({date:currentDay()}); };
  $w('wMore').onclick=function(){ var c=$w('wChips'); c.hidden=!c.hidden; this.setAttribute('aria-expanded',String(!c.hidden)); this.textContent=c.hidden?L.w_more_feel:L.w_less_feel; };
  wDate.addEventListener('change',function(){ if(!wDate.value) wDate.value=DATA.today; if(wDate.value>DATA.today) wDate.value=DATA.today; fillDay(wDate.value); });
  wBody.addEventListener('input',draftSoon); wTitle.addEventListener('input',draftSoon);
  function addFiles(inp){ Array.prototype.forEach.call(inp.files||[],function(f){ W.files.push(f); }); inp.value=''; paintThumbs(); }
  $w('wPhotos').onchange=function(){ addFiles(this); }; $w('wCam').onchange=function(){ addFiles(this); };
  document.addEventListener('keydown',function(e){ if(e.key==='Escape'&&W.open) closeWrite(); });
  window.addEventListener('pagehide',saveDraft);
  window.addEventListener('online',function(){ flush().then(function(id){ if(id) reload(id); else rebuild(anchorNow()); }); });
  /* which day the reader is on, so the pen writes to it (today unless a past day is showing) */
  function currentDay(){ var r=pages[2*cur], l=pages[2*cur-1], p=(r&&r.k==='day')?r:((l&&l.k==='day')?l:null); return (p&&p.e.date&&p.e.date!==DATA.today&&!p.e.trip)?p.e.date:DATA.today; }
  book.addEventListener('click',function(e){
    var b=e.target.closest('.tdyb'); if(b){ openWrite({date:b.getAttribute('data-w')}); return; }
    var pe=e.target.closest('.pedit'); if(pe){ openWrite({eid:parseInt(pe.getAttribute('data-e'),10)}); }
  });
  function afterStart(){
    var q=new URLSearchParams(location.search), w=q.get('write'), ed=parseInt(q.get('edit'),10)||0;
    refreshPending().then(function(){ if(pending.length){ rebuild(0); flush().then(function(id){ if(id) reload(id); else if(pending.length===0) rebuild(0); }); } });
    if(ed&&DATA.entries.some(function(e){return e.id===ed;})){ rebuild(ed); openWrite({eid:ed}); try{ history.replaceState(null,'',location.pathname); }catch(e){} }
    else if(w){ openWrite({date:/^\d{4}-\d{2}-\d{2}$/.test(w)?w:DATA.today}); try{ history.replaceState(null,'',location.pathname); }catch(e){} }
  }

  /* ---------- start ---------- */
  var t0=store('mw-theme'), f0=store('mw-font'), s0=store('mw-size');
  if(f0&&FONTS.some(function(f){return f[0]===f0;})) fsel=f0;
  if(s0&&PHS[s0]) szSel=s0;
  setSize(szSel,true); setTheme(t0&&THEMES.some(function(t){return t[0]===t0;})?t0:'classic',true);
  fetch(C.data,{credentials:'same-origin',cache:'no-store'}).then(function(r){ if(!r.ok) throw 0; return r.json(); }).then(function(d){
    DATA=d;
    var f=FONTFACE[effFont()]||'Newsreader';
    var ready=document.fonts?Promise.all(['400','500'].map(function(w){ return document.fonts.load(w+' 20px "'+f+'"'); })):Promise.resolve();
    ready.then(function(){ rebuild(0); afterStart(); },function(){ rebuild(0); afterStart(); });
  }).catch(function(){ root.innerHTML='<p class="nobook"><a href="'+esc(C.home)+'">'+esc(L.home)+'</a></p>'; });
  window.__book={setCur:function(c){setCur(c);},go:go,pages:function(){return pages;},cur:function(){return cur;},open:openWrite,pending:function(){return pending;},flush:flush};
})();
