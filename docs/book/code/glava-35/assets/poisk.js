function debounce(f, z = 250) {
    let t;
    return (...a) => { clearTimeout(t); t = setTimeout(() => f(...a), z); };
}
function ekranirovat(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

const pole = document.querySelector('#poisk');
const spisok = document.querySelector('.podskazki');

const iskat = debounce(async () => {
    const zapros = pole.value.trim();
    if (zapros.length < 2) { spisok.innerHTML = ''; spisok.hidden = true; return; }
    try {
        const otvet = await fetch('api/podskazki.php?q=' + encodeURIComponent(zapros));
        const tovary = await otvet.json();
        spisok.innerHTML = tovary.length
            ? tovary.map(t => `
                <a class="podskazka" href="index.php?q=${encodeURIComponent(t.artikul)}">
                    <span class="p-nazvanie">${ekranirovat(t.nazvanie)}</span>
                    <span class="p-artikul">${ekranirovat(t.artikul)}</span>
                    <span class="p-cena">${(t.cena / 100).toFixed(2)} сомони</span>
                </a>`).join('')
            : '<div class="p-pusto">Ничего не нашлось</div>';
        spisok.hidden = false;
    } catch (e) { spisok.hidden = true; }
}, 250);

pole.addEventListener('input', iskat);
document.addEventListener('click', (e) => {
    if (!e.target.closest('.poisk-bolshoy')) spisok.hidden = true;
});
