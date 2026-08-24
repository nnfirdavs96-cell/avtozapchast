const tovary = [
    { id: 1, nazvanie: 'Тормозные колодки Bosch', cena: 250, ostatok: 7,  brend: 'Bosch' },
    { id: 2, nazvanie: 'Масляный фильтр Mann',    cena: 45,  ostatok: 23, brend: 'Mann' },
    { id: 3, nazvanie: 'Свечи зажигания Denso',   cena: 120, ostatok: 0,  brend: 'Denso' },
    { id: 4, nazvanie: 'Тормозные диски Brembo',  cena: 780, ostatok: 2,  brend: 'Brembo' },
    { id: 5, nazvanie: 'Воздушный фильтр Mann',   cena: 65,  ostatok: 14, brend: 'Mann' },
    { id: 6, nazvanie: 'Аккумулятор Bosch S4',    cena: 950, ostatok: 4,  brend: 'Bosch' }
];

// Показать товар одной строкой
function opisat(t) {
    const status = t.ostatok > 0 ? `в наличии ${t.ostatok} шт.` : 'под заказ';
    return `${t.nazvanie} — ${t.cena} сомони (${status})`;
}

console.log('=== ВЕСЬ КАТАЛОГ ===');
for (const t of tovary) {
    console.log(opisat(t));
}

console.log('=== ТОЛЬКО В НАЛИЧИИ ===');
tovary.filter(t => t.ostatok > 0).forEach(t => console.log(opisat(t)));

console.log('=== ДЕШЕВЛЕ 200 СОМОНИ ===');
tovary.filter(t => t.cena < 200).forEach(t => console.log(opisat(t)));

// Считаем итоги
const vsegoPozicii = tovary.length;
const naSklade = tovary.reduce((s, t) => s + t.ostatok, 0);
const stoimostSklada = tovary.reduce((s, t) => s + t.cena * t.ostatok, 0);
const samyiDorogoy = tovary.reduce((max, t) => t.cena > max.cena ? t : max);

console.log('=== ИТОГИ ===');
console.log(`Позиций в каталоге: ${vsegoPozicii}`);
console.log(`Всего штук на складе: ${naSklade}`);
console.log(`Склад стоит: ${stoimostSklada} сомони`);
console.log(`Самый дорогой: ${samyiDorogoy.nazvanie}`);

// Корзина
const korzina = [];
function dobavitVKorzinu(id, kolichestvo) {
    const tovar = tovary.find(t => t.id === id);
    if (!tovar) return 'Товар не найден';
    if (tovar.ostatok < kolichestvo) return `Не хватает: есть только ${tovar.ostatok}`;
    korzina.push({ tovar, kolichestvo });
    return `Добавлено: ${tovar.nazvanie} × ${kolichestvo}`;
}

console.log('=== КОРЗИНА ===');
console.log(dobavitVKorzinu(1, 2));
console.log(dobavitVKorzinu(3, 1));
console.log(dobavitVKorzinu(99, 1));

const summaKorziny = korzina.reduce((s, p) => s + p.tovar.cena * p.kolichestvo, 0);
console.log(`Итого в корзине: ${summaKorziny} сомони`);
