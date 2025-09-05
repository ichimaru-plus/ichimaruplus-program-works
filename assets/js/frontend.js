(function () {
  // DOM Ready helper
  function ready(fn) {
    if (document.readyState !== 'loading') { fn(); }
    else { document.addEventListener('DOMContentLoaded', fn); }
  }

  ready(function () {
    // クリック委譲：data-copy を持つボタンでコピー
    document.body.addEventListener('click', function (e) {
      var btn = e.target.closest('[data-copy]');
      if (!btn) return;

      var text = btn.getAttribute('data-copy') || '';
      if (!text) return;

      var done = function () {
        btn.dataset.copied = '1';
        var original = btn.innerText;
        btn.innerText = 'コピー済み';
        // アクセシビリティ通知（スクリーンリーダー）
        btn.setAttribute('aria-live', 'polite');
        setTimeout(function () {
          btn.innerText = original || 'コピー';
          btn.dataset.copied = '';
        }, 1200);
      };

      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(done).catch(function () {
          // フォールバック
          var ta = document.createElement('textarea');
          ta.value = text;
          ta.style.position = 'fixed';
          ta.style.opacity = '0';
          document.body.appendChild(ta);
          ta.select();
          try { document.execCommand('copy'); } catch (err) {}
          document.body.removeChild(ta);
          done();
        });
      } else {
        // 古い環境向けフォールバック
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); } catch (err) {}
        document.body.removeChild(ta);
        done();
      }
    });
  });
})();