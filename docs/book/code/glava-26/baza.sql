CREATE TABLE tovary (
    id INTEGER PRIMARY KEY,
    nazvanie TEXT NOT NULL,
    artikul TEXT NOT NULL,
    brend TEXT NOT NULL,
    kategoriya TEXT NOT NULL,
    cena INTEGER NOT NULL,
    ostatok INTEGER NOT NULL DEFAULT 0,
    aktivnyi INTEGER NOT NULL DEFAULT 1
);

INSERT INTO tovary (id, nazvanie, artikul, brend, kategoriya, cena, ostatok, aktivnyi) VALUES
(1, 'Тормозные колодки Bosch', '0986424815', 'Bosch',  'Тормоза',   25000, 7,  1),
(2, 'Масляный фильтр Mann',    'W71280',     'Mann',   'Фильтры',    4500, 23, 1),
(3, 'Свечи зажигания Denso',   'IK20',       'Denso',  'Зажигание', 12000, 0,  1),
(4, 'Тормозные диски Brembo',  '09.9468.11', 'Brembo', 'Тормоза',   78000, 2,  1),
(5, 'Воздушный фильтр Mann',   'C25114',     'Mann',   'Фильтры',    6500, 14, 1),
(6, 'Аккумулятор Bosch S4',    '0092S40050', 'Bosch',  'Электрика', 95000, 4,  1),
(7, 'Салонный фильтр Mann',    'CU2545',     'Mann',   'Фильтры',    5500, 9,  1),
(8, 'Ремень ГРМ Bosch',        '1987949095', 'Bosch',  'Двигатель', 31000, 3,  1),
(9, 'Тормозная жидкость DOT4', '1987479107', 'Bosch',  'Тормоза',    3900, 31, 1),
(10,'Свечи NGK Iridium',       'ILKAR7B11',  'NGK',    'Зажигание', 15000, 6,  1),
(11,'Стойка амортизатора KYB', '341341',     'KYB',    'Подвеска',  42000, 0,  1),
(12,'Ремкомплект суппорта',    'D4-1234',    'Frenkit','Тормоза',    8900, 5,  0);
