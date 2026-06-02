CREATE TABLE `vkvpa_config`
(
    `cfg_key`   varchar(50) NOT NULL,
    `cfg_value` text DEFAULT NULL,
    PRIMARY KEY (`cfg_key`)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_unicode_ci;


INSERT INTO `vkvpa_config`
VALUES ('debug', 'true'),
       ('debug_EDI', 'true'),
       ('strictdate', 'false'),
       ('V_ADMIN_MAIL', 'mail@hcsradio.cz'),
       ('V_DEBUG_MAIL', 'false'),
       ('V_MAIL_TESTER', 'bartovsky@bbhosting.cz'),
       ('V_SMTP_HOST', 'kevin.bbhosting.cz'),
       ('V_SMTP_PASS', 'qkG7H5268ZT1'),
       ('V_SMTP_USER', 'vkvpa@hamradio.cz');
