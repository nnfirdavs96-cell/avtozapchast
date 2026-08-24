// Данные о товаре
const nazvanie = 'Тормозные колодки Bosch';
const cenaZaShtuku = 250;
const ostatokNaSklade = 7;
const skidkaProcent = 10;

// Сколько хочет покупатель
const hochetKupit = 3;

console.log(`Товар: ${nazvanie}`);
console.log(`Цена: ${cenaZaShtuku} сомони`);

// Проверяем наличие
if (ostatokNaSklade === 0) {
    console.log('Нет в наличии, можно заказать');
} else if (hochetKupit > ostatokNaSklade) {
    console.log(`Столько нет. Доступно только ${ostatokNaSklade} шт.`);
} else {
    // Считаем стоимость
    let itogo = cenaZaShtuku * hochetKupit;
    console.log(`${hochetKupit} шт. = ${itogo} сомони`);

    // Скидка от трёх штук
    if (hochetKupit >= 3) {
        const skidka = itogo * skidkaProcent / 100;
        itogo = itogo - skidka;
        console.log(`Скидка ${skidkaProcent}%: минус ${skidka} сомони`);
    }

    console.log(`К оплате: ${itogo} сомони`);
}

// Покажем весь остаток по одной штуке
console.log('--- Остаток на складе ---');
for (let i = 1; i <= ostatokNaSklade; i++) {
    console.log(`Комплект №${i}`);
}
