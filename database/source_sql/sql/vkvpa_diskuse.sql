CREATE TABLE `vkvpa_diskuse`
(
    `id`      int(11)     NOT NULL AUTO_INCREMENT,
    `id_kola` int(11)     NOT NULL,
    `cas`     datetime    NOT NULL,
    `znacka`  varchar(20) NOT NULL,
    `jmeno`   varchar(50)  DEFAULT NULL,
    `text`    text        NOT NULL,
    `foto`    varchar(100) DEFAULT NULL,
    `ip`      varchar(45)  DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `id_kola` (`id_kola`)
) ENGINE = InnoDB
  AUTO_INCREMENT = 31
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_general_ci;

INSERT INTO `vkvpa_diskuse`
VALUES (20, 130, '2026-04-22 09:11:23', 'OK1KZE', 'OK1VUM',
        'Sem můžete psát své komentáře a zážitky a doplnit je fotografií', '130/1776841883_OK1KZE.jpg',
        '151.249.104.130'),
       (21, 130, '2026-04-22 12:59:25', 'OK1DWF', 'Karel', 'Test', '130/1776855565_OK1DWF.jpg', '78.80.107.86'),
       (26, 131, '2026-05-17 20:32:27', 'OK2XKO', 'Jirka', 'Dík za QSO !', '131/1779042747_OK2XKO.jpg',
        '213.194.199.173'),
       (28, 131, '2026-05-18 13:31:16', 'OK1IO', 'Jiří Knejfl',
        'Zdravím,dnes velmi kvalitní contest.Podmínky OK. Contest jsem jel z kopce.WX krásné i když venku chladno.Dík za milá QSO fungovalo to velmi hezky,NSL v dalším kole. 73 Jirka.',
        NULL, '109.164.55.70'),
       (30, 131, '2026-05-18 15:13:16', 'OK1KZE', 'ok1vum',
        'Tentokrát dobrá účast, dokonce zavolal SSB bez domluvy 9A6A ze Hvaru JN83GE. Na slyšenou příště.',
        '131/1779109995_OK1KZE.jpg', '151.249.105.55');
