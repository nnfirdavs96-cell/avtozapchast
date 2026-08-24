const tovary = [
    { id: 1, nazvanie: 'Тормозные колодки Bosch', cena: 250, ostatok: 7,  brend: 'Bosch' },
    { id: 2, nazvanie: 'Масляный фильтр Mann',    cena: 45,  ostatok: 23, brend: 'Mann' },
    { id: 3, nazvanie: 'Свечи зажигания Denso',   cena: 120, ostatok: 0,  brend: 'Denso' },
    { id: 4, nazvanie: 'Тормозные диски Brembo',  cena: 780, ostatok: 2,  brend: 'Brembo' },
    { id: 5, nazvanie: 'Воздушный фильтр Mann',   cena: 65,  ostatok: 14, brend: 'Mann' },
    { id: 6, nazvanie: 'Аккумулятор Bosch S4',    cena: 950, ostatok: 4,  brend: 'Bosch' }
];

// Корзина живёт здесь
const korzina = [];

// --- Отрисовка ---

function kartochka(t) {
    const est = t.ostatok > 0;
    return `
        <article class="tovar">
            <h3>${t.nazvanie}</h3>
            <p class="artikul">${t.brend}</p>
            <p class="cena">${t.cena} <span>сомони</span></p>
            <p class="nalichie ${est ? '' : 'nety'}">${
                est ? `В наличии: ${t.ostatok} шт.` : 'Под заказ'
            }</p>
            <button data-id="${t.id}" ${est ? '' : 'disabled'}>В корзину</button>
        </article>`;
}

function narisovat(spisok) {
    const katalog = document.querySelector('.katalog');
    document.querySelector('.schetchik').textContent = `Найдено: ${spisok.length}`;

    katalog.innerHTML = spisok.length
        ? spisok.map(kartochka).join('')
        : '<p class="pusto">Ничего не нашлось. Попробуйте другой запрос.</p>';
}

// --- Фильтрация ---

function primenitFiltry() {
    const zapros = document.querySelector('#poisk').value.trim().toLowerCase();
    const brend = document.querySelector('#brend').value;
    const tolkoVNalichii = document.querySelector('#v-nalichii').checked;

    let spisok = tovary;

    if (zapros) {
        spisok = spisok.filter(t => t.nazvanie.toLowerCase().includes(zapros));
    }
    if (brend) {
        spisok = spisok.filter(t => t.brend === brend);
    }
    if (tolkoVNalichii) {
        spisok = spisok.filter(t => t.ostatok > 0);
    }

    narisovat(spisok);
}

// --- Корзина ---

function obnovitKorzinu() {
    const shtuk = korzina.reduce((s, p) => s + p.kolichestvo, 0);
    const summa = korzina.reduce((s, p) => s + p.tovar.cena * p.kolichestvo, 0);

    document.querySelector('.korzina-shtuk').textContent = shtuk;
    document.querySelector('.korzina-summa').textContent = summa;
}

function dobavitVKorzinu(id) {
    const tovar = tovary.find(t => t.id === id);
    if (!tovar) return;

    const uzheEst = korzina.find(p => p.tovar.id === id);
    if (uzheEst) {
        uzheEst.kolichestvo = uzheEst.kolichestvo + 1;
    } else {
        korzina.push({ tovar, kolichestvo: 1 });
    }

    obnovitKorzinu();
}

// --- События ---

document.querySelector('#poisk').addEventListener('input', primenitFiltry);
document.querySelector('#brend').addEventListener('change', primenitFiltry);
document.querySelector('#v-nalichii').addEventListener('change', primenitFiltry);

// Делегирование: один слушатель на весь каталог
document.querySelector('.katalog').addEventListener('click', (e) => {
    const knopka = e.target.closest('button');
    if (!knopka || knopka.disabled) return;

    dobavitVKorzinu(Number(knopka.dataset.id));

    // Короткая обратная связь — человек должен видеть, что нажатие сработало
    knopka.textContent = 'Добавлено';
    setTimeout(() => { knopka.textContent = 'В корзину'; }, 900);
});

// Форма поиска не должна перезагружать страницу
document.querySelector('.filtry').addEventListener('submit', (e) => {
    e.preventDefault();
    primenitFiltry();
});

// Первый показ
narisovat(tovary);
obnovitKorzinu();
