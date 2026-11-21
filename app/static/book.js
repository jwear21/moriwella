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
    '<button class="gear" id="gear" type="button" aria-haspopup="dialog" aria-controls="sheet">'+esc(L.customize)+'</button><div class="scrim" id="scrim" hidden></div>'+
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
    if(pg.k==='today') return '<div class="page"><div class="head"><div class="date">'+esc(DATA.todayMd)+'<small>'+esc(DATA.todayDow)+' &middot; '+esc(L.today)+'</small></div></div><div class="tdy"><p>'+esc(L.today_hint)+'</p><a href="'+esc(C.day+DATA.today)+'">'+esc(L.write_today)+'</a></div>'+no+'</div>';
    var e=pg.e;
    return '<div class="page"><div class="head"><div class="date">'+esc(e.md)+'<small>'+esc(e.dow)+(pg.first?'':' &middot; '+esc(L.continued))+'</small></div><div class="mood">'+(pg.first?esc(e.mood):'')+'</div></div>'+
      (pg.first?'<h2 class="etitle">'+esc(e.title)+'</h2>':'')+
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
  function rebuild(anchorId){
    pages=[{k:'cover'},{k:'endpaper'},{k:'title'}].concat(paginate());
    if(pages.length%2===1) pages.push({k:'blank'});
    pages.push({k:'today'}); pages.push({k:'blank'});
    nLeaf=pages.length/2; MAXCUR=nLeaf-1; leaves=[]; book.innerHTML='';
    book.appendChild(el('div','cover')); book.appendChild(el('div','blockL')); book.appendChild(el('div','blockR')); book.appendChild(castL); book.appendChild(castR);
    var c=MAXCUR;
    if(anchorId){ for(var i=3;i<pages.length;i++){ if(pages[i].k==='day'&&pages[i].e.id===anchorId&&pages[i].first){ c=Math.max(1,Math.min(MAXCUR,Math.ceil(i/2))); break; } } }
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

  /* ---------- start ---------- */
  var t0=store('mw-theme'), f0=store('mw-font'), s0=store('mw-size');
  if(f0&&FONTS.some(function(f){return f[0]===f0;})) fsel=f0;
  if(s0&&PHS[s0]) szSel=s0;
  setSize(szSel,true); setTheme(t0&&THEMES.some(function(t){return t[0]===t0;})?t0:'classic',true);
  fetch(C.data,{credentials:'same-origin',cache:'no-store'}).then(function(r){ if(!r.ok) throw 0; return r.json(); }).then(function(d){
    DATA=d;
    if(!d.entries.length){ root.innerHTML='<p class="nobook">'+esc(L.empty)+' <a href="'+esc(C.home)+'">'+esc(L.empty_link)+'</a></p>'; return; }
    var f=FONTFACE[effFont()]||'Newsreader';
    var ready=document.fonts?Promise.all(['400','500'].map(function(w){ return document.fonts.load(w+' 20px "'+f+'"'); })):Promise.resolve();
    ready.then(function(){ rebuild(0); },function(){ rebuild(0); });
  }).catch(function(){ root.innerHTML='<p class="nobook"><a href="'+esc(C.home)+'">'+esc(L.home)+'</a></p>'; });
  window.__book={setCur:function(c){setCur(c);},go:go,pages:function(){return pages;},cur:function(){return cur;}};
})();
