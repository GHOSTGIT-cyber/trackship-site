-- init-database.sql
-- Script d'initialisation de la base de données pour le compteur journalier TrackShip
-- À exécuter via phpMyAdmin sur Hostinger

-- ============================================
-- Table principale : jours de comptage
-- ============================================
CREATE TABLE IF NOT EXISTS compteur_jours (
    numero_jour INT PRIMARY KEY AUTO_INCREMENT,
    date_jour DATE NOT NULL UNIQUE,
    compteur_passages INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_date (date_jour)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Compteur de passages quotidien par jour';

-- ============================================
-- Table : bateaux vus par jour
-- ============================================
CREATE TABLE IF NOT EXISTS bateaux_vus (
    id INT PRIMARY KEY AUTO_INCREMENT,
    numero_jour INT NOT NULL,
    track_id VARCHAR(50) NOT NULL,
    premiere_detection TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (numero_jour) REFERENCES compteur_jours(numero_jour) ON DELETE CASCADE,
    UNIQUE KEY unique_track_par_jour (numero_jour, track_id),
    INDEX idx_track (track_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Historique des bateaux détectés par jour';

-- ============================================
-- Table : bateaux actuellement en zone rouge (état temps réel)
-- ============================================
CREATE TABLE IF NOT EXISTS bateaux_zone_rouge_actifs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    numero_jour INT NOT NULL,
    track_id VARCHAR(50) NOT NULL,
    entree_zone TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    derniere_maj TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (numero_jour) REFERENCES compteur_jours(numero_jour) ON DELETE CASCADE,
    UNIQUE KEY unique_track_actif (numero_jour, track_id),
    INDEX idx_track_actif (track_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bateaux actuellement en zone rouge (≤1km)';

-- ============================================
-- Insertion des données initiales
-- ============================================
-- Jour 1 (hier) : 8 passages
-- Jour 2 (aujourd'hui) : 6 passages

INSERT INTO compteur_jours (numero_jour, date_jour, compteur_passages)
VALUES
    (1, DATE_SUB(CURDATE(), INTERVAL 1 DAY), 8),
    (2, CURDATE(), 6)
ON DUPLICATE KEY UPDATE compteur_passages = VALUES(compteur_passages);

-- ============================================
-- Vérification de l'installation
-- ============================================
SELECT
    'Installation réussie !' as statut,
    COUNT(*) as nombre_tables
FROM information_schema.tables
WHERE table_schema = DATABASE()
AND table_name IN ('compteur_jours', 'bateaux_vus', 'bateaux_zone_rouge_actifs');

SELECT
    numero_jour,
    date_jour,
    compteur_passages,
    created_at
FROM compteur_jours
ORDER BY numero_jour;
