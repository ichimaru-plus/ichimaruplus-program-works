(function(){
  function ready(fn){
    if (document.readyState != 'loading'){
      fn();
    } else {
      document.addEventListener('DOMContentLoaded', fn);
    }
  }

  ready(function(){
    document.body.addEventListener('click', function(e){
      var btn = e.target.closest('[data-copy]');
      if(!btn) return;

      var text = btn.getAttribute('data-copy') || '';
      if(!text) return;

      function feedback(msg){
        var old = btn.innerText;
        btn.innerText = msg;
        btn.dataset.copied = '1';
        setTimeout(function(){
          btn.innerText = old;
          btn.dataset.copied = '';
        }, 1500);
      }

      if (navigator.clipboard && navigator.clipboard.writeText){
        navigator.clipboard.writeText(text).then(function(){
          feedback('コピーしました');
        }).catch(function(){
          feedback('コピー失敗');
        });
      } else {
        // Fallback for old browsers
        var ta = document.createElement('textarea');
        ta.value = text;
        document.body.appendChild(ta);
        ta.select();
        try {
          document.execCommand('copy');
          feedback('コピーしました');
        } catch(err){
          feedback('コピー失敗');
        }
        document.body.removeChild(ta);
      }
    });
  });
})();