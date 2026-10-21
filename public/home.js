'use strict';
(() => {
  const question = document.getElementById('practice-question');
  if (!question) return;
  const picture = document.getElementById('practice-picture');
  const options = document.getElementById('answer-options');
  const feedback = document.getElementById('practice-feedback');
  const next = document.getElementById('next-question');
  const title = document.getElementById('demo-topic');
  const lessons = [
    {topic:'Bilangan sampai 10', question:'Berapa banyak bintang ini?', options:['4','5','6'], answer:'5', why:'Ada 5 bintang. Kamu sudah menghitung semuanya!', visual:'count'},
    {topic:'Penjumlahan sampai 10', question:'3 + 2 = berapa?', options:['4','5','6'], answer:'5', why:'3 bintang ditambah 2 bintang menjadi 5.', visual:'add'},
    {topic:'Pengurangan sampai 10', question:'Ada 6 apel. 2 dimakan. Berapa sisanya?', options:['2','3','4'], answer:'4', why:'6 dikurangi 2, tersisa 4 apel.', visual:'subtract'},
    {topic:'Mengenal bentuk', question:'Mana yang berbentuk segitiga?', options:['A','B','C'], answer:'C', why:'Bentuk C adalah segitiga. Segitiga punya 3 sisi.', visual:'shapes'},
    {topic:'Bilangan sampai 20', question:'12 terdiri dari berapa puluhan dan satuan?', options:['1 puluhan, 2 satuan','2 puluhan, 1 satuan','1 puluhan, 1 satuan'], answer:'1 puluhan, 2 satuan', why:'10 dan 2 menjadi 12: 1 puluhan dan 2 satuan.', visual:'tens'},
    {topic:'Operasi sampai 20', question:'13 + 4 = berapa?', options:['16','17','18'], answer:'17', why:'Mulai dari 13, maju 4 langkah: 14, 15, 16, 17.', visual:'equation'},
    {topic:'Mengukur panjang', question:'Pensil mana yang lebih panjang?', options:['Pensil A','Pensil B','Sama panjang'], answer:'Pensil B', why:'Pensil A panjangnya 3 kotak, pensil B 5 kotak. Pensil B lebih panjang.', visual:'measure'},
    {topic:'Mengenal diagram', question:'Buah mana yang paling banyak?', options:['Apel','Pir','Sama banyak'], answer:'Apel', why:'Ada 4 apel dan 2 pir. Apel lebih banyak.', visual:'chart'}
  ];
  let active = 0;
  let done = false;
  const NS = 'http://www.w3.org/2000/svg';
  function svgShape(kind) {
    const svg = document.createElementNS(NS, 'svg');
    svg.setAttribute('viewBox','0 0 50 50'); svg.setAttribute('aria-hidden','true'); svg.classList.add('count-object');
    let el;
    if (kind === 'star') { el=document.createElementNS(NS,'path'); el.setAttribute('d','m25 3 7 14 15 2-11 11 3 15-14-7-14 7 3-15L3 19l15-2Z'); el.setAttribute('fill','#e8c56e'); el.setAttribute('stroke','#b28b39'); el.setAttribute('stroke-width','1.5'); }
    else if (kind === 'apple' || kind === 'pear') { el=document.createElementNS(NS,'path'); el.setAttribute('d',kind==='apple'?'M25 14C12 5 3 18 8 33c3 11 12 12 17 9 9 4 17-2 19-14 2-13-8-20-19-14Z':'M20 12c-2 10-3 12-10 21-6 12 8 15 16 14 16-1 20-10 11-19-6-6-5-11-7-16-2-6-9-6-10 0Z'); el.setAttribute('fill',kind==='apple'?'#dc9780':'#b4c58b'); const leaf=document.createElementNS(NS,'path'); leaf.setAttribute('d','M25 13c-2-6-1-9 2-11m0 6c7-7 12-5 12-5-2 7-8 9-12 5'); leaf.setAttribute('stroke','#5d7e52'); leaf.setAttribute('stroke-width','2.5'); svg.append(leaf); }
    else if(kind==='triangle') { el=document.createElementNS(NS,'path'); el.setAttribute('d','M25 4 47 45H3Z'); el.setAttribute('fill','#b4cbb9'); }
    else if(kind==='square') { el=document.createElementNS(NS,'rect'); el.setAttribute('x','6'); el.setAttribute('y','6'); el.setAttribute('width','38'); el.setAttribute('height','38'); el.setAttribute('rx','5'); el.setAttribute('fill','#e4baa8'); }
    else { el=document.createElementNS(NS,'circle'); el.setAttribute('cx','25'); el.setAttribute('cy','25'); el.setAttribute('r','21'); el.setAttribute('fill','#cdb9dc'); }
    svg.append(el); return svg;
  }
  function objects(kind, count, crossed=0) { const group=document.createElement('div'); group.className='object-group'; for(let i=0;i<count;i++){const icon=svgShape(kind); if(i>=count-crossed)icon.classList.add('crossed-object'); group.append(icon);} return group; }
  function word(text) {const span=document.createElement('span');span.className='visual-sign';span.textContent=text;return span;}
  function draw(lesson) {
    picture.replaceChildren();
    if(lesson.visual==='count'){picture.append(objects('star',5));picture.setAttribute('aria-label','5 bintang');}
    else if(lesson.visual==='add'){picture.append(objects('star',3),word('+'),objects('star',2));picture.setAttribute('aria-label','3 bintang ditambah 2 bintang');}
    else if(lesson.visual==='subtract'){picture.append(objects('apple',6,2));picture.setAttribute('aria-label','6 apel, 2 di antaranya berwarna pudar karena dimakan');}
    else if(lesson.visual==='shapes'){picture.append(svgShape('circle'),svgShape('square'),svgShape('triangle'));picture.setAttribute('aria-label','Bentuk A melengkung, bentuk B memiliki 4 sisi, bentuk C memiliki 3 sisi');}
    else if(lesson.visual==='tens'){const frame=document.createElement('div');frame.className='ten-frame';for(let i=0;i<10;i++)frame.append(document.createElement('span'));picture.append(frame);for(let i=0;i<2;i++){const d=document.createElement('span');d.className='single-dot';picture.append(d);}picture.setAttribute('aria-label','Satu kelompok berisi 10 bulatan, dan 2 bulatan terpisah');}
    else if(lesson.visual==='equation'){picture.append(word('13'),word('+'),word('4'),word('= ?'));picture.setAttribute('aria-label','13 ditambah 4');}
    else if(lesson.visual==='measure'){const area=document.createElement('div');area.className='ruler-demo';[3,5].forEach((n,i)=>{const row=document.createElement('div');row.className='ruler-row';const label=document.createElement('span');label.textContent='Pensil '+(i?'B':'A');const cells=document.createElement('div');cells.className='ruler-cells';for(let j=0;j<n;j++)cells.append(document.createElement('i'));row.append(label,cells);area.append(row);});picture.append(area);picture.setAttribute('aria-label','Pensil A sepanjang 3 kotak dan pensil B sepanjang 5 kotak, ukuran setiap kotak sama');}
    else {const area=document.createElement('div');area.className='chart-demo';['Apel','Pir'].forEach((name,i)=>{const label=document.createElement('span');label.textContent=name;area.append(label,objects(i?'pear':'apple',i?2:4));});picture.append(area);picture.setAttribute('aria-label','Diagram: 4 apel dan 2 pir. Satu gambar mewakili satu buah.');}
  }
  function load(index) {
    active=index;done=false;const lesson=lessons[index]; title.textContent=lesson.topic;question.textContent=lesson.question;draw(lesson);options.replaceChildren();options.classList.toggle('long-answers',lesson.options.some(value=>value.length>10));
    lesson.options.forEach((value,i)=>{const b=document.createElement('button');b.type='button';b.setAttribute('aria-pressed','false');if(value.length>3)b.classList.add('compact');if(lesson.visual==='shapes'){b.append(svgShape(['circle','square','triangle'][i]),document.createTextNode('Bentuk '+value));}else b.textContent=value;
      b.addEventListener('click',()=>{if(done)return;options.querySelectorAll('button').forEach(el=>{el.classList.remove('is-wrong');el.setAttribute('aria-pressed','false');});b.setAttribute('aria-pressed','true');if(value===lesson.answer){done=true;b.classList.add('is-right');feedback.textContent='Hebat! '+lesson.why;feedback.className='practice-feedback success';options.querySelectorAll('button').forEach(el=>{el.disabled=true;});}else{b.classList.add('is-wrong');feedback.textContent='Belum pas. Amati gambarnya, lalu coba sekali lagi, ya.';feedback.className='practice-feedback';}});options.append(b);});
    options.hidden=false;next.hidden=false;feedback.textContent='Pilih jawaban yang menurutmu tepat.';feedback.className='practice-feedback';
    document.querySelectorAll('[data-topic]').forEach(el=>el.setAttribute('aria-pressed',String(Number(el.dataset.topic)===index)));
  }
  document.querySelectorAll('[data-topic]').forEach(button=>button.addEventListener('click',()=>{const n=Number(button.dataset.topic);if(!Number.isInteger(n)||n<0||n>=lessons.length)return;load(n);document.getElementById('coba').scrollIntoView({behavior:window.matchMedia('(prefers-reduced-motion: reduce)').matches?'auto':'smooth',block:'start'});question.setAttribute('tabindex','-1');question.focus({preventScroll:true});}));
  next.addEventListener('click',()=>load((active+1)%lessons.length));load(0);
})();
