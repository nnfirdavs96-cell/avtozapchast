<?php
/**
 * Общий JS-блок рендера полей характеристик для форм товара (админ/менеджер).
 * Ожидает в области видимости: $specSchemasJson, $catSpecMap, $specValues.
 * Разметка должна содержать: #catSelect (категория), #specBlock, #specFields, #specTitle.
 */
?>
<script>
(function () {
  var SCHEMAS  = <?= $specSchemasJson ?: '{}' ?>;
  var CAT_SPEC = <?= json_encode($catSpecMap ?? [], JSON_UNESCAPED_UNICODE) ?: '{}' ?>;
  var VALUES   = <?= json_encode($specValues ?? [], JSON_UNESCAPED_UNICODE) ?: '{}' ?>;
  var sel = document.getElementById('catSelect');
  var block = document.getElementById('specBlock');
  var fields = document.getElementById('specFields');
  var title = document.getElementById('specTitle');
  if (!sel || !block || !fields) return;

  function esc(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

  function render() {
    var key = CAT_SPEC[sel.value];
    var sc = key && SCHEMAS[key];
    if (!sc) { block.style.display = 'none'; fields.innerHTML = ''; return; }
    if (title) title.textContent = 'Характеристики: ' + sc.label;
    var html = '';
    sc.fields.forEach(function (f) {
      var cur = VALUES[f.name] || '';
      var lbl = f.name + (f.unit ? (', ' + f.unit) : '');
      html += '<label style="display:block;font-size:.9rem;">' +
              '<span style="display:block;color:#555;margin-bottom:3px;">' + esc(lbl) + '</span>';
      if (f.options && f.options.length) {
        html += '<select name="spec[' + esc(f.name) + ']" style="width:100%;padding:8px;border:1px solid #d9dce1;border-radius:7px;">';
        html += '<option value="">—</option>';
        f.options.forEach(function (o) {
          var os = String(o);
          html += '<option value="' + esc(os) + '"' + (os === String(cur) ? ' selected' : '') + '>' + esc(os) + '</option>';
        });
        html += '</select>';
      } else {
        html += '<input type="text" name="spec[' + esc(f.name) + ']" value="' + esc(cur) + '" style="width:100%;padding:8px;border:1px solid #d9dce1;border-radius:7px;">';
      }
      html += '</label>';
    });
    fields.innerHTML = html;
    block.style.display = '';
  }

  sel.addEventListener('change', render);
  render();
})();
</script>
