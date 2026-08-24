const tovary = [
    { id: 1, nazvanie: 'Тормозные колодки Bosch', cena: 250, ostatok: 7,  brend: 'Bosch' },
    { id: 2, nazvanie: 'Масляный фильтр Mann',    cena: 45,  ostatok: 23, brend: 'Mann' },
    { id: 3, nazvanie: 'Свечи зажигания Denso',   cena: 120, ostatok: 0,  brend: 'Denso' },
    { id: 4, nazvanie: 'Тормозные диски Brembo',  cena: 780, ostatok: 2,  brend: 'Brembo' },
    { id: 5, nazvanie: 'Воздушный фильтр Mann',   cena: 65,  ostatok: 14, brend: 'Mann' },
    { id: 6, nazvanie: 'Аккумулятор Bosch S4',    cena: 950, ostatok: 4,  brend: 'Bosch' }
];

function kartochka(t) {
    const estOstatok = t.ostatok > 0;
    return `
        <article class="tovar">
            <h3>${t.nazvanie}</h3>
            <p class="artikul">${t.brend}</p>
            <p class="cena">${t.cena} <span>сомони</span></p>
            <p class="nalichie ${estOstatok ? '' : 'nety'}">${
                estOstatok ? `В наличии: ${t.ostatok} шт.` : 'Под заказ, 5 дней'
            }</p>
            <button data-id="${t.id}" ${estOstatok ? '' : 'disabled'}>В корзину</button>
        </article>
    `;
}

function narisovat(spisok) {
    const katalog = document.querySelector('.katalog');
    const schetchik = document.querySelector('.schetchik');

    schetchik.textContent = `Найдено товаров: ${spisok.length}`;

    if (spisok.length === 0) {
        katalog.innerHTML = '<p class="pusto">Ничего не нашлось</p>';
        return;
    }

    katalog.innerHTML = spisok.map(kartochka).join('');
}

narisovat(tovary);
