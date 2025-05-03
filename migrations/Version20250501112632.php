<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250501112632 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE reservation_transport ADD departure_lat DOUBLE PRECISION DEFAULT NULL, ADD departure_lng DOUBLE PRECISION DEFAULT NULL, ADD destination_lat DOUBLE PRECISION DEFAULT NULL, ADD destination_lng DOUBLE PRECISION DEFAULT NULL, CHANGE status status ENUM('confirmée', 'en_attente', 'refusée') DEFAULT 'en_attente'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE reservation_transport RENAME INDEX fk_reservation_transport_cinetudiant TO IDX_7CEC40B1F6C8AB85
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE reservation_transport RENAME INDEX fk_reservation_transport_cintransporteur TO IDX_7CEC40B193BCDE18
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE transport DROP INDEX fk_reservation_id, ADD UNIQUE INDEX UNIQ_66AB212EB83297E7 (reservation_id)
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX fk_cin ON transport
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE transport DROP cin, CHANGE status status VARCHAR(20) NOT NULL COMMENT '(DC2Type:transport_status)', CHANGE timestamp timestamp DATETIME NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE transport ADD CONSTRAINT FK_66AB212E377F287F FOREIGN KEY (id_voiture) REFERENCES voiture (id_voiture)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE transport ADD CONSTRAINT FK_66AB212EB83297E7 FOREIGN KEY (reservation_id) REFERENCES reservation_transport (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE transport RENAME INDEX fk_id_voiture TO IDX_66AB212E377F287F
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE utilisateur ADD theme VARCHAR(20) NOT NULL, ADD image VARCHAR(255) DEFAULT NULL, ADD reset_code_expires_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', CHANGE role role ENUM('ETUDIANT', 'TRANSPORTEUR', 'ADMIN') NOT NULL COMMENT '(DC2Type:role_enum)', CHANGE blocked blocked TINYINT(1) NOT NULL, CHANGE created_at created_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE utilisateur RENAME INDEX email TO UNIQ_1D1C63B3E7927C74
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE voiture ADD image VARCHAR(200) DEFAULT NULL, DROP photo, CHANGE disponibilite disponibilite VARCHAR(20) NOT NULL COMMENT '(DC2Type:voiture_disponibilite)', CHANGE timestamp timestamp DATETIME NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE voiture ADD CONSTRAINT FK_E9E2810FABE530DA FOREIGN KEY (cin) REFERENCES utilisateur (cin)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE voiture RENAME INDEX fk_cin_v TO IDX_E9E2810FABE530DA
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE reservation_transport DROP departure_lat, DROP departure_lng, DROP destination_lat, DROP destination_lng, CHANGE status status VARCHAR(255) DEFAULT 'en_attente'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE reservation_transport RENAME INDEX idx_7cec40b1f6c8ab85 TO fk_reservation_transport_cinEtudiant
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE reservation_transport RENAME INDEX idx_7cec40b193bcde18 TO fk_reservation_transport_cinTransporteur
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE transport DROP INDEX UNIQ_66AB212EB83297E7, ADD INDEX fk_reservation_id (reservation_id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE transport DROP FOREIGN KEY FK_66AB212E377F287F
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE transport DROP FOREIGN KEY FK_66AB212EB83297E7
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE transport ADD cin VARCHAR(8) NOT NULL, CHANGE status status VARCHAR(255) NOT NULL, CHANGE timestamp timestamp DATETIME DEFAULT 'CURRENT_TIMESTAMP(6)' NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX fk_cin ON transport (cin)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE transport RENAME INDEX idx_66ab212e377f287f TO fk_id_voiture
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE utilisateur DROP theme, DROP image, DROP reset_code_expires_at, CHANGE role role VARCHAR(255) NOT NULL, CHANGE blocked blocked TINYINT(1) DEFAULT 0, CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE utilisateur RENAME INDEX uniq_1d1c63b3e7927c74 TO email
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE voiture DROP FOREIGN KEY FK_E9E2810FABE530DA
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE voiture ADD photo VARCHAR(200) NOT NULL, DROP image, CHANGE disponibilite disponibilite VARCHAR(255) NOT NULL, CHANGE timestamp timestamp DATETIME DEFAULT 'CURRENT_TIMESTAMP(6)' NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE voiture RENAME INDEX idx_e9e2810fabe530da TO fk_cin_v
        SQL);
    }
}
