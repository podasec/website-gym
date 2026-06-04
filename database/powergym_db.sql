-- =============================================================
-- Power Gym – Schema MySQL completo
-- Charset: utf8mb4 | Collation: utf8mb4_unicode_ci
-- Importabile via phpMyAdmin o CLI: mysql -u root < powergym_db.sql
-- =============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';

-- -------------------------------------------------------------
-- 1. DATABASE
-- -------------------------------------------------------------
CREATE DATABASE IF NOT EXISTS `powergym_db`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `powergym_db`;

-- -------------------------------------------------------------
-- 2. TABELLA utenti
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `utenti` (
  `id`            INT          NOT NULL AUTO_INCREMENT,
  `nome`          VARCHAR(100) NOT NULL,
  `cognome`       VARCHAR(100) NOT NULL,
  `email`         VARCHAR(255) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `telefono`      VARCHAR(20)  DEFAULT NULL,
  `data_nascita`  DATE         DEFAULT NULL,
  `ruolo`         ENUM('admin','istruttore','cliente') NOT NULL DEFAULT 'cliente',
  `stato`         ENUM('attivo','inattivo','sospeso')  NOT NULL DEFAULT 'attivo',
  `avatar_url`    VARCHAR(500) DEFAULT NULL,
  `note`          TEXT         DEFAULT NULL,
  `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_utenti_email` (`email`),
  KEY `idx_utenti_ruolo`  (`ruolo`),
  KEY `idx_utenti_stato`  (`stato`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 3. TABELLA remember_tokens
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `remember_tokens` (
  `id`         INT          NOT NULL AUTO_INCREMENT,
  `user_id`    INT          NOT NULL,
  `token`      VARCHAR(64)  NOT NULL,
  `expires_at` DATETIME     NOT NULL,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_remember_token` (`token`),
  KEY `idx_remember_user` (`user_id`),
  CONSTRAINT `fk_remember_user`
    FOREIGN KEY (`user_id`) REFERENCES `utenti` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 4. TABELLA login_attempts
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id`           INT          NOT NULL AUTO_INCREMENT,
  `ip_address`   VARCHAR(45)  NOT NULL,
  `email`        VARCHAR(255) DEFAULT NULL,
  `success`      TINYINT(1)   NOT NULL DEFAULT 0,
  `attempted_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_login_ip`   (`ip_address`),
  KEY `idx_login_time` (`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 5. TABELLA access_log
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `access_log` (
  `id`         INT          NOT NULL AUTO_INCREMENT,
  `user_id`    INT          DEFAULT NULL,
  `ip_address` VARCHAR(45)  DEFAULT NULL,
  `user_agent` VARCHAR(500) DEFAULT NULL,
  `action`     VARCHAR(100) DEFAULT NULL,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_access_user` (`user_id`),
  KEY `idx_access_time` (`created_at`),
  CONSTRAINT `fk_access_user`
    FOREIGN KEY (`user_id`) REFERENCES `utenti` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 6. TABELLA discipline
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `discipline` (
  `id`          INT          NOT NULL AUTO_INCREMENT,
  `nome`        VARCHAR(100) NOT NULL,
  `icona_svg`   TEXT         DEFAULT NULL,
  `descrizione` TEXT         DEFAULT NULL,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_disciplina_nome` (`nome`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 7. TABELLA corsi
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `corsi` (
  `id`                INT            NOT NULL AUTO_INCREMENT,
  `nome`              VARCHAR(200)   NOT NULL,
  `descrizione`       TEXT           DEFAULT NULL,
  `descrizione_breve` VARCHAR(500)   DEFAULT NULL,
  `disciplina_id`     INT            DEFAULT NULL,
  `istruttore_id`     INT            DEFAULT NULL,
  `livello`           ENUM('principiante','intermedio','avanzato','tutti') NOT NULL DEFAULT 'tutti',
  `durata_minuti`     INT            NOT NULL DEFAULT 60,
  `max_partecipanti`  INT            NOT NULL DEFAULT 20,
  `prezzo_mensile`    DECIMAL(8,2)   NOT NULL DEFAULT 0.00,
  `stato`             ENUM('attivo','sospeso','terminato') NOT NULL DEFAULT 'attivo',
  `immagine_url`      VARCHAR(500)   DEFAULT NULL,
  `created_at`        TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_corsi_disciplina`  (`disciplina_id`),
  KEY `idx_corsi_istruttore`  (`istruttore_id`),
  KEY `idx_corsi_livello`     (`livello`),
  KEY `idx_corsi_stato`       (`stato`),
  CONSTRAINT `fk_corsi_disciplina`
    FOREIGN KEY (`disciplina_id`) REFERENCES `discipline` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_corsi_istruttore`
    FOREIGN KEY (`istruttore_id`) REFERENCES `utenti` (`id`)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 8. TABELLA orari_corsi
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `orari_corsi` (
  `id`              INT         NOT NULL AUTO_INCREMENT,
  `corso_id`        INT         NOT NULL,
  `giorno_settimana` ENUM('lunedi','martedi','mercoledi','giovedi','venerdi','sabato','domenica') NOT NULL,
  `ora_inizio`      TIME        NOT NULL,
  `ora_fine`        TIME        NOT NULL,
  `sala`            VARCHAR(100) DEFAULT NULL,
  `attivo`          TINYINT(1)  NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_orari_corso` (`corso_id`),
  CONSTRAINT `fk_orari_corso`
    FOREIGN KEY (`corso_id`) REFERENCES `corsi` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 9. TABELLA iscrizioni
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `iscrizioni` (
  `id`               INT           NOT NULL AUTO_INCREMENT,
  `utente_id`        INT           NOT NULL,
  `corso_id`         INT           NOT NULL,
  `data_iscrizione`  DATE          NOT NULL DEFAULT (CURDATE()),
  `data_scadenza`    DATE          DEFAULT NULL,
  `stato`            ENUM('attiva','scaduta','cancellata','sospesa') NOT NULL DEFAULT 'attiva',
  `prezzo_pagato`    DECIMAL(8,2)  DEFAULT NULL,
  `metodo_pagamento` ENUM('contante','carta','bonifico','online') DEFAULT NULL,
  `note`             TEXT          DEFAULT NULL,
  `created_at`       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_iscrizione_utente_corso` (`utente_id`, `corso_id`),
  KEY `idx_iscrizioni_utente` (`utente_id`),
  KEY `idx_iscrizioni_corso`  (`corso_id`),
  KEY `idx_iscrizioni_stato`  (`stato`),
  CONSTRAINT `fk_iscrizioni_utente`
    FOREIGN KEY (`utente_id`) REFERENCES `utenti` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_iscrizioni_corso`
    FOREIGN KEY (`corso_id`) REFERENCES `corsi` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 10. TABELLA pagamenti
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `pagamenti` (
  `id`                     INT           NOT NULL AUTO_INCREMENT,
  `iscrizione_id`          INT           NOT NULL,
  `utente_id`              INT           NOT NULL,
  `importo`                DECIMAL(8,2)  NOT NULL,
  `valuta`                 CHAR(3)       NOT NULL DEFAULT 'EUR',
  `metodo`                 ENUM('contante','carta','bonifico','online') NOT NULL DEFAULT 'contante',
  `stato`                  ENUM('completato','in_attesa','fallito','rimborsato') NOT NULL DEFAULT 'in_attesa',
  `riferimento_transazione` VARCHAR(255) DEFAULT NULL,
  `data_pagamento`         TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `note`                   TEXT          DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_pagamenti_iscrizione` (`iscrizione_id`),
  KEY `idx_pagamenti_utente`     (`utente_id`),
  KEY `idx_pagamenti_stato`      (`stato`),
  CONSTRAINT `fk_pagamenti_iscrizione`
    FOREIGN KEY (`iscrizione_id`) REFERENCES `iscrizioni` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_pagamenti_utente`
    FOREIGN KEY (`utente_id`) REFERENCES `utenti` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 11. TABELLA istruttori_profili
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `istruttori_profili` (
  `id`               INT          NOT NULL AUTO_INCREMENT,
  `utente_id`        INT          NOT NULL,
  `bio`              TEXT         DEFAULT NULL,
  `specializzazioni` TEXT         DEFAULT NULL COMMENT 'JSON array di stringhe',
  `anni_esperienza`  INT          DEFAULT NULL,
  `certificazioni`   TEXT         DEFAULT NULL,
  `social_instagram` VARCHAR(255) DEFAULT NULL,
  `social_facebook`  VARCHAR(255) DEFAULT NULL,
  `visibile_sito`    TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_profilo_utente` (`utente_id`),
  CONSTRAINT `fk_profilo_utente`
    FOREIGN KEY (`utente_id`) REFERENCES `utenti` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 12. TABELLA contatti
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `contatti` (
  `id`         INT          NOT NULL AUTO_INCREMENT,
  `nome`       VARCHAR(100) NOT NULL,
  `cognome`    VARCHAR(100) NOT NULL,
  `email`      VARCHAR(255) NOT NULL,
  `telefono`   VARCHAR(20)  DEFAULT NULL,
  `oggetto`    VARCHAR(300) NOT NULL,
  `messaggio`  TEXT         NOT NULL,
  `stato`      ENUM('nuovo','letto','risposto','archiviato') NOT NULL DEFAULT 'nuovo',
  `ip_address` VARCHAR(45)  DEFAULT NULL,
  `user_agent` VARCHAR(500) DEFAULT NULL,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_contatti_stato` (`stato`),
  KEY `idx_contatti_email` (`email`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 13. TABELLA newsletter
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `newsletter` (
  `id`                  INT          NOT NULL AUTO_INCREMENT,
  `email`               VARCHAR(255) NOT NULL,
  `nome`                VARCHAR(100) DEFAULT NULL,
  `stato`               ENUM('attivo','disiscritto') NOT NULL DEFAULT 'attivo',
  `token_disiscrizione` VARCHAR(64)  DEFAULT NULL,
  `created_at`          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_newsletter_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 14. TABELLA abbonamenti_tipi
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `abbonamenti_tipi` (
  `id`              INT           NOT NULL AUTO_INCREMENT,
  `nome`            VARCHAR(100)  NOT NULL,
  `prezzo`          DECIMAL(8,2)  NOT NULL,
  `durata_giorni`   INT           NOT NULL DEFAULT 30,
  `accesso_corsi`   INT           NOT NULL DEFAULT 1 COMMENT '0 = illimitati',
  `descrizione`     TEXT          DEFAULT NULL,
  `funzionalita`    TEXT          DEFAULT NULL COMMENT 'JSON array',
  `attivo`          TINYINT(1)    NOT NULL DEFAULT 1,
  `ordine_display`  INT           NOT NULL DEFAULT 0,
  `created_at`      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_abbonamenti_attivo` (`attivo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- 15. TABELLA csrf_tokens (alternativa alle sessioni)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `csrf_tokens` (
  `token`      VARCHAR(64)  NOT NULL,
  `user_ip`    VARCHAR(45)  DEFAULT NULL,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` DATETIME     NOT NULL,
  PRIMARY KEY (`token`),
  KEY `idx_csrf_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- DATI DI ESEMPIO
-- =============================================================

-- -------------------------------------------------------------
-- Discipline (6)
-- -------------------------------------------------------------
INSERT INTO `discipline` (`nome`, `descrizione`) VALUES
  ('Boxe',               'L''arte nobile per eccellenza. Pugni, guardia e footwork.'),
  ('Muay Thai',          'L''arte degli otto arti. Pugni, calci, gomitate e ginocchiate.'),
  ('Brazilian Jiu-Jitsu','Lotta a terra, leve, strangolamenti e submission.'),
  ('MMA',                'Mixed Martial Arts: la sintesi di tutte le arti marziali.'),
  ('Fitness Funzionale', 'Allenamento ad alta intensità ispirato ai movimenti marziali.'),
  ('Yoga Combattivo',    'Yoga adattato alle esigenze degli atleti da combattimento.');

-- -------------------------------------------------------------
-- Utenti – 1 admin + 4 istruttori + 10 clienti
-- Tutte le password sono bcrypt di "Admin123!" (cost 12)
-- Admin: Admin123!
-- Istruttori e clienti: User1234!
-- -------------------------------------------------------------

-- Admin
INSERT INTO `utenti` (`nome`,`cognome`,`email`,`password_hash`,`telefono`,`ruolo`,`stato`) VALUES
(
  'Mario','Bianchi',
  'admin@powergym.it',
  '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
  '+39 02 1234567',
  'admin','attivo'
);

-- Istruttori
INSERT INTO `utenti` (`nome`,`cognome`,`email`,`password_hash`,`telefono`,`data_nascita`,`ruolo`,`stato`) VALUES
(
  'Marco','Rossi',
  'marco.rossi@powergym.it',
  '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
  '+39 333 1111111','1985-03-15',
  'istruttore','attivo'
),
(
  'Sofia','Greco',
  'sofia.greco@powergym.it',
  '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
  '+39 333 2222222','1990-07-22',
  'istruttore','attivo'
),
(
  'Luca','Ferrari',
  'luca.ferrari@powergym.it',
  '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
  '+39 333 3333333','1988-11-08',
  'istruttore','attivo'
),
(
  'Chiara','Marino',
  'chiara.marino@powergym.it',
  '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
  '+39 333 4444444','1992-05-30',
  'istruttore','attivo'
);

-- 10 clienti di esempio
INSERT INTO `utenti` (`nome`,`cognome`,`email`,`password_hash`,`telefono`,`data_nascita`,`ruolo`,`stato`) VALUES
('Alessio','Conti',      'alessio.conti@email.it',      '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+39 340 1001001','1995-02-14','cliente','attivo'),
('Giulia','Ricci',       'giulia.ricci@email.it',        '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+39 340 2002002','1998-09-03','cliente','attivo'),
('Davide','Lombardi',    'davide.lombardi@email.it',     '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+39 340 3003003','2000-12-25','cliente','attivo'),
('Elena','Costa',        'elena.costa@email.it',         '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+39 340 4004004','1993-06-18','cliente','attivo'),
('Francesco','Mancini',  'francesco.mancini@email.it',   '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL,            '1987-04-01','cliente','attivo'),
('Valentina','Bruno',    'valentina.bruno@email.it',     '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+39 340 6006006','2001-08-20','cliente','attivo'),
('Matteo','Gallo',       'matteo.gallo@email.it',        '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+39 340 7007007','1996-01-11','cliente','attivo'),
('Sara','Esposito',      'sara.esposito@email.it',       '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL,            '1999-10-07','cliente','attivo'),
('Roberto','De Luca',    'roberto.deluca@email.it',      '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+39 340 9009009','1984-03-29','cliente','attivo'),
('Irene','Barbieri',     'irene.barbieri@email.it',      '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '+39 340 0000010','2003-07-15','cliente','attivo');

-- -------------------------------------------------------------
-- Profili istruttori
-- -------------------------------------------------------------
INSERT INTO `istruttori_profili` (`utente_id`,`bio`,`specializzazioni`,`anni_esperienza`,`certificazioni`,`social_instagram`,`visibile_sito`) VALUES
(2, 'Marco Rossi è cintura nera di Boxe con 18 anni di esperienza. Campione regionale 2016 e 2018. Docente FIJLKAM certificato.', '["Boxe","Fitness Funzionale"]', 18, 'FIJLKAM Livello 2, CONI Istruttore', '@marcorossiboxe', 1),
(3, 'Sofia Greco è maestra di Muay Thai e Yoga, 12 anni di allenamento in Thailandia. Specializzata in corsi per donne e principianti.', '["Muay Thai","Yoga Combattivo"]', 12, 'IFMA Certified Coach, Yoga Alliance RYT-200', '@sofiagrecomt', 1),
(4, 'Luca Ferrari è cintura viola di BJJ sotto la supervisione della Federazione FIJLKAM. Specializzato in Brazilian Jiu-Jitsu e MMA.', '["BJJ","MMA"]', 10, 'FIJLKAM BJJ, ACS Combat Sports', '@lucaferraribjj', 1),
(5, 'Chiara Marino è campionessa italiana di Muay Thai juniores 2019 e istruttrice certificata di Fitness Funzionale e Kickboxing.', '["Muay Thai","Fitness Funzionale","Kickboxing"]', 8, 'IFMA Level 1, NASM-CPT', '@chiaramarinofit', 1);

-- -------------------------------------------------------------
-- Corsi (6)
-- istruttori ID: Marco=2, Sofia=3, Luca=4, Chiara=5
-- discipline:   Boxe=1, Muay Thai=2, BJJ=3, MMA=4, Fitness=5, Yoga=6
-- -------------------------------------------------------------
INSERT INTO `corsi`
  (`nome`,`descrizione`,`descrizione_breve`,`disciplina_id`,`istruttore_id`,`livello`,`durata_minuti`,`max_partecipanti`,`prezzo_mensile`,`stato`) VALUES
(
  'Boxe – Tutti i Livelli',
  'Corso completo di Boxe che copre guardia, footwork, jab, cross, hook e uppercut. Sessioni di sparring supervisionate per i livelli intermedio e avanzato. Perfetto per chi vuole iniziare e per chi vuole migliorare la propria tecnica.',
  'L''arte nobile per eccellenza. Dalla guardia base agli schemi avanzati.',
  1, 2, 'tutti', 90, 18, 59.00, 'attivo'
),
(
  'Muay Thai – Principianti',
  'Introduzione all''arte degli otto arti. Imparare le basi di pugni, calci, gomitate e ginocchiate in un ambiente sicuro e accogliente. Nessuna esperienza richiesta.',
  'Scopri la disciplina thailandese partendo dalle fondamenta.',
  2, 3, 'principiante', 75, 16, 54.00, 'attivo'
),
(
  'Muay Thai – Avanzato',
  'Corso avanzato di Muay Thai rivolto ad atleti con almeno 1 anno di esperienza. Focus su combinazioni avanzate, clinch, lavoro con i pad e sparring controllato.',
  'Allenamento intensivo per chi vuole portare il Muay Thai al livello successivo.',
  2, 3, 'avanzato', 90, 12, 69.00, 'attivo'
),
(
  'BJJ – Grappling & Submission',
  'Brazilian Jiu-Jitsu: la lotta a terra che ha rivoluzionato il combat sport. Imparare leve articolari, strangolamenti, takedown e la filosofia del BJJ. Aperti ai principianti come ai cinture blu/viola.',
  'Grappling, submission e lotta a terra per tutti i livelli.',
  3, 4, 'tutti', 90, 15, 64.00, 'attivo'
),
(
  'MMA – Percorso Completo',
  'Mixed Martial Arts: il percorso strutturato che integra striking (Boxe + Muay Thai), wrestling, BJJ e clinch work. Rivolto ad atleti con basi nelle discipline singole.',
  'La sintesi di tutte le arti marziali in un unico percorso.',
  4, 4, 'intermedio', 105, 12, 79.00, 'attivo'
),
(
  'Yoga Combattivo',
  'Yoga adattato alle esigenze degli atleti da combattimento. Focus su flessibilità, mobilità articolare, recupero attivo e concentrazione mentale. Ideale come complemento a qualsiasi disciplina marziale.',
  'Flessibilità, recupero e mente per l''atleta di combattimento.',
  6, 3, 'principiante', 60, 20, 44.00, 'attivo'
);

-- -------------------------------------------------------------
-- Orari corsi (lunedi=1, martedi=2 … domenica=7)
-- corso_id: Boxe=1, MT-Princ=2, MT-Av=3, BJJ=4, MMA=5, Yoga=6
-- -------------------------------------------------------------
INSERT INTO `orari_corsi` (`corso_id`,`giorno_settimana`,`ora_inizio`,`ora_fine`,`sala`) VALUES
-- Boxe
(1,'lunedi',    '07:00','08:30','Ring Boxe'),
(1,'mercoledi', '07:00','08:30','Ring Boxe'),
(1,'lunedi',    '19:00','20:30','Ring Boxe'),
(1,'mercoledi', '19:00','20:30','Ring Boxe'),
(1,'venerdi',   '19:00','20:30','Ring Boxe'),
-- Muay Thai Principianti
(2,'martedi',   '17:30','18:45','Sala Arti Marziali'),
(2,'giovedi',   '17:30','18:45','Sala Arti Marziali'),
(2,'sabato',    '10:00','11:15','Sala Arti Marziali'),
-- Muay Thai Avanzato
(3,'martedi',   '20:30','22:00','Sala Arti Marziali'),
(3,'giovedi',   '20:30','22:00','Sala Arti Marziali'),
-- BJJ
(4,'martedi',   '19:00','20:30','Tatami BJJ'),
(4,'giovedi',   '19:00','20:30','Tatami BJJ'),
(4,'sabato',    '09:30','11:00','Tatami BJJ'),
(4,'domenica',  '10:00','12:00','Tatami BJJ'),
-- MMA
(5,'lunedi',    '20:30','22:15','Sala Polivalente'),
(5,'mercoledi', '20:30','22:15','Sala Polivalente'),
(5,'venerdi',   '20:30','22:15','Sala Polivalente'),
-- Yoga
(6,'martedi',   '09:30','10:30','Sala Yoga'),
(6,'giovedi',   '09:30','10:30','Sala Yoga');

-- -------------------------------------------------------------
-- Abbonamenti tipo (3)
-- -------------------------------------------------------------
INSERT INTO `abbonamenti_tipi` (`nome`,`prezzo`,`durata_giorni`,`accesso_corsi`,`descrizione`,`funzionalita`,`attivo`,`ordine_display`) VALUES
(
  'Base', 49.00, 30, 1,
  'Accesso a 1 corso a scelta per 30 giorni.',
  '["1 corso incluso","Orari standard","Spogliatoi inclusi","Consulenza iniziale gratuita"]',
  1, 1
),
(
  'Premium', 79.00, 30, 2,
  'Accesso a 2 corsi a scelta per 30 giorni. La scelta più popolare.',
  '["2 corsi inclusi","Priorità prenotazione","Accesso Open Mat","Analisi performance mensile","Sconto merchandise 10%"]',
  1, 2
),
(
  'Elite', 99.00, 30, 0,
  'Accesso illimitato a tutti i corsi. Per chi vuole il massimo.',
  '["Corsi illimitati","Sessioni personal training (2/mese)","Nutrizione sportiva base","Accesso 7 giorni su 7","Sconto merchandise 20%","Badge agonista"]',
  1, 3
);

-- -------------------------------------------------------------
-- Iscrizioni di esempio (5)
-- clienti: id 6-15, corsi: id 1-6
-- -------------------------------------------------------------
INSERT INTO `iscrizioni` (`utente_id`,`corso_id`,`data_iscrizione`,`data_scadenza`,`stato`,`prezzo_pagato`,`metodo_pagamento`) VALUES
(6,  1, '2026-05-01', '2026-05-31', 'attiva',   59.00, 'carta'),
(7,  6, '2026-05-03', '2026-06-02', 'attiva',   44.00, 'contante'),
(8,  4, '2026-04-15', '2026-05-15', 'scaduta',  64.00, 'carta'),
(9,  2, '2026-05-10', '2026-06-09', 'attiva',   54.00, 'online'),
(10, 5, '2026-05-20', '2026-06-19', 'attiva',   79.00, 'bonifico');

-- -------------------------------------------------------------
-- Messaggi contatto di esempio (3)
-- -------------------------------------------------------------
INSERT INTO `contatti` (`nome`,`cognome`,`email`,`telefono`,`oggetto`,`messaggio`,`stato`,`ip_address`) VALUES
(
  'Giovanni','Sala',
  'giovanni.sala@email.it',
  '+39 345 1234567',
  'prova-gratuita',
  'Salve, sono interessato a provare il corso di Boxe per principianti. Potreste indicarmi come prenotare la prima lezione gratuita e quale attrezzatura portare? Grazie mille.',
  'nuovo',
  '192.168.1.100'
),
(
  'Francesca','Neri',
  'francesca.neri@email.it',
  NULL,
  'abbonamento',
  'Buongiorno, vorrei sapere se è possibile frequentare sia il corso di Muay Thai che quello di Yoga con un singolo abbonamento, e qual è il costo. Grazie.',
  'letto',
  '192.168.1.101'
),
(
  'Andrea','Vitali',
  'andrea.vitali@email.it',
  '+39 389 9876543',
  'info-corsi',
  'Ciao! Ho 16 anni e vorrei iniziare il BJJ. C''è un corso junior o posso partecipare agli stessi corsi degli adulti? Aspetto una vostra risposta, grazie.',
  'risposto',
  '192.168.1.102'
);

-- -------------------------------------------------------------
-- Iscritti newsletter di esempio
-- -------------------------------------------------------------
INSERT INTO `newsletter` (`email`,`nome`,`stato`,`token_disiscrizione`) VALUES
('newsletter1@email.it', 'Paolo',     'attivo', 'tok1234567890abcdef1234567890abcdef1234567890abcdef12345678901234'),
('newsletter2@email.it', 'Martina',   'attivo', 'tok2234567890abcdef1234567890abcdef1234567890abcdef12345678901234'),
('newsletter3@email.it', NULL,        'attivo', 'tok3234567890abcdef1234567890abcdef1234567890abcdef12345678901234');

-- =============================================================
SET FOREIGN_KEY_CHECKS = 1;
-- Fine schema powergym_db.sql
-- =============================================================
