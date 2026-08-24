// ===== Данные =====

const tovary = [
    { id: 1, nazvanie: 'Тормозные колодки Bosch',  artikul: '0986424815', cena: 250, ostatok: 7,  brend: 'Bosch' },
    { id: 2, nazvanie: 'Масляный фильтр Mann',     artikul: 'W71280',     cena: 45,  ostatok: 23, brend: 'Mann' },
    { id: 3, nazvanie: 'Свечи зажигания Denso',    artikul: 'IK20',       cena: 120, ostatok: 0,  brend: 'Denso' },
    { id: 4, nazvanie: 'Тормозные диски Brembo',   artikul: '09.9468.11', cena: 780, ostatok: 2,  brend: 'Brembo' },
    { id: 5, nazvanie: 'Воздушный фильтр Mann',    artikul: 'C25114',     cena: 65,  ostatok: 14, brend: 'Mann' },
    { id: 6, nazvanie: 'Аккумулятор Bosch S4',     artikul: '0092S40050', cena: 950, ostatok: 4,  brend: 'Bosch' },
    { id: 7, nazvanie: 'Салонный фильтр Mann',     artikul: 'CU2545',     cena: 55,  ostatok: 9,  brend: 'Mann' },
    { id: 8, nazvanie: 'Ремень ГРМ Bosch',         artikul: '1987949095', cena: 310, ostatok: 3,  brend: 'Bosch' }
];

// ===== Корзина: только id и количество =====
// Цену не храним осознанно: её должен считать сервер, иначе покупатель
// поменяет число в браузере и оформит заказ по своей цене.

let korzina = zagruzitKorzinu();

function zagruzitKorzinu() {
    try {
        const dannye = localStorage.getItem('korzina');
        return dannye ? JSON.parse(dannye) : [];
    } catch (e) {
        // Данные могли испортиться — начинаем с пустой, а не падаем
        return [];
    }
}

function sohranitKorzinu() {
    localStorage.setItem('korzina', JSON.stringify(korzina));
}

// ===== Вспомогательное =====

function ekranirovat(tekst) {
    return String(tekst)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function podsvetit(tekst, zapros) {
    const bezopasnyi = ekranirovat(tekst);
    if (!zapros) return bezopasnyi;
    const shablon = ekranirovat(zapros).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    return bezopasnyi.replace(new RegExp(`(${shablon})`, 'gi'), '<mark>$1</mark>');
}

function debounce(funkciya, zaderzhka = 300) {
    let tajmer;
    return (...argumenty) => {
        clearTimeout(tajmer);
        tajmer = setTimeout(() => funkciya(...argumenty), zaderzhka);
    };
}

// ===== Отрисовка =====

function kartochka(t, zapros) {
    const est = t.ostatok > 0;
    const vKorzine = korzina.find(p => p.id === t.id);

    return `
        <article class="tovar">
            <h3>${podsvetit(t.nazvanie, zapros)}</h3>
            <p class="artikul">Артикул: ${podsvetit(t.artikul, zapros)}</p>
            <p class="cena">${t.cena} <span>сомони</span></p>
            <p class="nalichie ${est ? '' : 'nety'}">${
                est ? `В наличии: ${t.ostatok} шт.` : 'Под заказ, 5 дней'
            }</p>
            <button data-id="${t.id}" ${est ? '' : 'disabled'}>
                ${vKorzine ? `В корзине: ${vKorzine.kolichestvo}` : 'В корзину'}
            </button>
        </article>`;
}

function narisovat(spisok, zapros) {
    const katalog = document.querySelector('.katalog');
    document.querySelector('.schetchik').textContent =
        spisok.length ? `Найдено товаров: ${spisok.length}` : '';

    if (spisok.length === 0) {
        katalog.innerHTML = `
            <div class="pusto">
                <h3>Ничего не нашлось</h3>
                <p>Попробуйте другой запрос или сбросьте фильтры.</p>
                <button class="sbros">Показать все товары</button>
            </div>`;
        return;
    }

    katalog.innerHTML = spisok.map(t => kartochka(t, zapros)).join('');
}

// ===== Фильтры =====

function primenitFiltry() {
    const zapros  = document.querySelector('#poisk').value.trim();
    const nizhnij = zapros.toLowerCase();
    const brend   = document.querySelector('#brend').value;
    const tolkoEst = document.querySelector('#v-nalichii').checked;
    const sortirovka = document.querySelector('#sort').value;

    let spisok = [...tovary];

    if (nizhnij) {
        spisok = spisok.filter(t =>
            t.nazvanie.toLowerCase().includes(nizhnij) ||
            t.artikul.toLowerCase().includes(nizhnij)
        );
    }
    if (brend)     spisok = spisok.filter(t => t.brend === brend);
    if (tolkoEst)  spisok = spisok.filter(t => t.ostatok > 0);

    if (sortirovka === 'cena-vozr')  spisok.sort((a, b) => a.cena - b.cena);
    if (sortirovka === 'cena-ubyv')  spisok.sort((a, b) => b.cena - a.cena);
    if (sortirovka === 'nazvanie')   spisok.sort((a, b) => a.nazvanie.localeCompare(b.nazvanie));

    narisovat(spisok, zapros);
}

// ===== Корзина =====

function obnovitKorzinu() {
    const shtuk = korzina.reduce((s, p) => s + p.kolichestvo, 0);
    const summa = korzina.reduce((s, p) => {
        const t = tovary.find(x => x.id === p.id);
        return s + (t ? t.cena * p.kolichestvo : 0);
    }, 0);

    document.querySelector('.korzina-shtuk').textContent = shtuk;
    document.querySelector('.korzina-summa').textContent = summa;
    document.querySelector('.korzina-ochistit').hidden = shtuk === 0;
}

function dobavit(id) {
    const tovar = tovary.find(t => t.id === id);
    if (!tovar || tovar.ostatok === 0) return;

    const est = korzina.find(p => p.id === id);
    if (est) {
        if (est.kolichestvo >= tovar.ostatok) return;   // больше, чем на складе, нельзя
        est.kolichestvo++;
    } else {
        korzina.push({ id, kolichestvo: 1 });
    }

    sohranitKorzinu();
    obnovitKorzinu();
}

function ochistit() {
    korzina = [];
    sohranitKorzinu();
    obnovitKorzinu();
    primenitFiltry();
}

// ===== События =====

document.querySelector('#poisk').addEventListener('input', debounce(primenitFiltry, 300));
document.querySelector('#brend').addEventListener('change', primenitFiltry);
document.querySelector('#v-nalichii').addEventListener('change', primenitFiltry);
document.querySelector('#sort').addEventListener('change', primenitFiltry);

document.querySelector('.filtry').addEventListener('submit', (e) => {
    e.preventDefault();
    primenitFiltry();
});

// Одно делегирование на весь каталог
document.querySelector('.katalog').addEventListener('click', (e) => {
    const sbros = e.target.closest('.sbros');
    if (sbros) {
        document.querySelector('#poisk').value = '';
        document.querySelector('#brend').value = '';
        document.querySelector('#v-nalichii').checked = false;
        primenitFiltry();
        return;
    }

    const knopka = e.target.closest('button[data-id]');
    if (!knopka || knopka.disabled) return;

    dobavit(Number(knopka.dataset.id));
    primenitFiltry();      // перерисуем, чтобы кнопка показала количество
});

document.querySelector('.korzina-ochistit').addEventListener('click', () => {
    if (confirm('Очистить корзину?')) ochistit();
});

// ===== Старт =====

primenitFiltry();
obnovitKorzinu();
