(function(){
  function ready(fn){ if(document.readyState!='loading'){ fn(); } else { document.addEventListener('DOMContentLoaded',fn); } }
  ready(function(){
    document.body.addEventListener('click', function(e){
      var btn = e.target.closest('[data-copy]');
      if(!btn) return;
      var text = btn.getAttribute('data-copy') || '';
      if(!text) return;
      if(navigator.clipboard && navigator.clipboard.writeText){
        navigator.clipboard.writeText(text).then(function(){ btn.dataset.copied='1'; btn.innerText='Copied!'; setTimeout(function(){ btn.innerText='コピー'; btn.dataset.copied=''; },1200); });
      } else {
        var ta=document.createElement('textarea'); ta.value=text; document.body.appendChild(ta); ta.select();
        try{ document.execCommand('copy'); btn.innerText='Copied!'; setTimeout(function(){ btn.innerText='コピー'; },1200); }catch(err){}
        document.body.removeChild(ta);
      }
    });
  });
})();