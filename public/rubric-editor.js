(() => {
  const editor = document.getElementById('rubric-editor');
  if (!editor) return;
  const counts = {};
  for (const type of ['rule', 'criterion']) {
    const container = document.getElementById(`${type}-rows`);
    counts[type] = Math.max(-1, ...Array.from(container.querySelectorAll('[name]')).map(el => Number(el.name.match(/\[(\d+)\]/)?.[1] ?? -1))) + 1;
    document.querySelector(`[data-add-row="${type}"]`).addEventListener('click', () => {
      if (container.children.length >= 20) { alert('Maksimal 20 baris.'); return; }
      const index = counts[type]++;
      const fragment = document.getElementById(`${type}-template`).content.cloneNode(true);
      for (const el of fragment.querySelectorAll('[name]')) el.name = el.name.replace('__INDEX__', String(index));
      const id = fragment.querySelector(`[name$="[${type === 'rule' ? 'rule' : 'criterion'}_id]"]`);
      const usedIds = Array.from(container.querySelectorAll(`[name$="[${type === 'rule' ? 'rule' : 'criterion'}_id]"]`)).map(el => el.value);
      let number = index + 1;
      while (usedIds.includes(`${type === 'rule' ? 'R' : 'C'}${number}`)) number++;
      id.value = `${type === 'rule' ? 'R' : 'C'}${number}`;
      container.append(fragment); id.focus();
    });
  }
  editor.addEventListener('click', event => {
    if (event.target.matches('[data-remove-row]')) event.target.closest('fieldset').remove();
  });
  let dirty = false;
  editor.addEventListener('input', () => { dirty = true; });
  editor.addEventListener('change', () => { dirty = true; });
  editor.addEventListener('click', event => { if (event.target.matches('[data-add-row],[data-remove-row]')) dirty = true; });
  const approve = document.querySelector('form[action$="/approve"]');
  approve?.addEventListener('submit', event => {
    if (dirty) { event.preventDefault(); alert('Simpan perubahan draf terlebih dahulu sebelum pengesahan.'); }
  });
})();
