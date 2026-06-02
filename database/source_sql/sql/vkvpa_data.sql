CREATE TABLE `vkvpa_data`
(
    `id`           int(11)     NOT NULL AUTO_INCREMENT,
    `id_kola`      int(11)     NOT NULL,
    `id_kategorie` int(11)     NOT NULL,
    `qrp`          tinyint(1)           DEFAULT 0,
    `lp`           tinyint(1)           DEFAULT 0,
    `znacka`       varchar(10) NOT NULL,
    `locator`      varchar(6)           DEFAULT '',
    `pocet`        int(11)              DEFAULT 0,
    `bodu_za_qso`  int(11)              DEFAULT 1,
    `nasobice`     int(11)              DEFAULT 0,
    `body`         int(11)              DEFAULT 0,
    `jmeno`        varchar(60)          DEFAULT '',
    `mail`         varchar(250)         DEFAULT '',
    `telefon`      varchar(20)          DEFAULT '',
    `poznamka`     varchar(250)         DEFAULT '',
    `soapbox`      varchar(250)         DEFAULT '',
    `ip`           varchar(64)          DEFAULT '',
    `EDI`          tinyint(1)           DEFAULT 0,
    `EDI_ID`       int(11)              DEFAULT 0,
    `poradi`       int(11)              DEFAULT 0,
    `schvaleno`    tinyint(1)           DEFAULT 0,
    `odeslano`     tinyint(1)  NOT NULL DEFAULT 0,
    `session_id`   varchar(255)         DEFAULT '',
    `timestamp`    timestamp   NULL     DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    KEY `data1` (`id_kola`, `znacka`, `schvaleno`)
) ENGINE = InnoDB
  AUTO_INCREMENT = 28747
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;


INSERT INTO `vkvpa_data`
VALUES (26, 11, 1, 0, 0, 'OK1VOF', 'JO60WQ', 35, 111, 7, 777, 'Alex OK1VOF', 'ok1vof@gmail.com', '',
        'FT-817+4elY (brezen) ', '', '82.144.143.27', 0, 0, 0, 0, 1, '', '2026-02-04 12:29:13'),
       (29, 4, 1, 0, 0, 'OK2BMJ', 'JN89VC', 69, 216, 16, 3456, 'Milan', 'ok2bmj@email.cz', '',
        'R2CW 100W 9el. 500m ASL', '', '5.104.19.177', 0, 0, 15, 1, 1, '', '2026-02-04 12:29:13'),
       (30, 1, 1, 0, 0, 'OK1VOF', 'JN89EX', 31, 86, 7, 602, 'Alex OK1VOF', 'ok1vof@gmail.com', '', 'FT-857+7elY', '',
        '82.144.143.27', 0, 0, 45, 1, 1, '', '2026-02-04 12:29:13'),
       (33, 3, 1, 0, 0, 'OK1VOF', 'JO60WQ', 35, 111, 7, 777, 'Alex OK1VOF', 'ok1vof@gmail.com', '', 'FT-817+4elY', '',
        '82.144.143.27', 0, 0, 30, 1, 1, '', '2026-02-04 12:29:13'),
       (34, 3, 3, 0, 0, 'OK1VOF', 'JO60WQ', 13, 35, 4, 140, 'Alex OK1VOF', 'ok1vof@gmail.com', '', 'FT-817+6elY', '',
        '82.144.143.27', 0, 0, 9, 1, 1, '', '2026-02-04 12:29:13'),
       (35, 4, 1, 0, 0, 'OK1VOF', 'JN89DW', 54, 180, 14, 2520, 'Alex OK1VOF', 'ok1vof@gmail.com', '', 'FT-857+7elY', '',
        '82.144.143.27', 0, 0, 21, 1, 1, '', '2026-02-04 12:29:13'),
       (36, 4, 3, 0, 0, 'OK1VOF', 'JN89DW', 11, 34, 6, 204, 'Alex OK1VOF', 'ok1vof@gmail.com', '', 'FT-857+14elY', '',
        '82.144.143.27', 0, 0, 11, 1, 1, '', '2026-02-04 12:29:13');