<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250501152259 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE reservation_transport CHANGE status status ENUM('confirmée', 'en_attente', 'refusée') DEFAULT 'en_attente'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE reservation_transport ADD CONSTRAINT FK_7CEC40B1F6C8AB85 FOREIGN KEY (cinEtudiant) REFERENCES utilisateur (cin)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE reservation_transport ADD CONSTRAINT FK_7CEC40B193BCDE18 FOREIGN KEY (cinTransporteur) REFERENCES utilisateur (cin)
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX fk_reservation_transport_cinetudiant ON reservation_transport
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_7CEC40B1F6C8AB85 ON reservation_transport (cinEtudiant)
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX fk_reservation_transport_cintransporteur ON reservation_transport
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IDX_7CEC40B193BCDE18 ON reservation_transport (cinTransporteur)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE transport CHANGE timestamp timestamp DATETIME NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE transport ADD CONSTRAINT FK_66AB212E377F287F FOREIGN KEY (id_voiture) REFERENCES voiture (id_voiture)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE transport ADD CONSTRAINT FK_66AB212EB83297E7 FOREIGN KEY (reservation_id) REFERENCES reservation_transport (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE utilisateur CHANGE role role ENUM('ETUDIANT', 'TRANSPORTEUR', 'ADMIN') NOT NULL COMMENT '(DC2Type:role_enum)', CHANGE blocked blocked TINYINT(1) NOT NULL, CHANGE created_at created_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', CHANGE reset_code_expires_at reset_code_expires_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', CHANGE isTwoFactorEnabled isTwoFactorEnabled TINYINT(1) NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX email ON utilisateur
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX UNIQ_1D1C63B3E7927C74 ON utilisateur (email)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE voiture ADD CONSTRAINT FK_E9E2810FABE530DA FOREIGN KEY (cin) REFERENCES utilisateur (cin)
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            ALTER TABLE reservation_transport DROP FOREIGN KEY FK_7CEC40B1F6C8AB85
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE reservation_transport DROP FOREIGN KEY FK_7CEC40B193BCDE18
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE reservation_transport DROP FOREIGN KEY FK_7CEC40B1F6C8AB85
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE reservation_transport DROP FOREIGN KEY FK_7CEC40B193BCDE18
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE reservation_transport CHANGE status status VARCHAR(255) DEFAULT 'en_attente'
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX idx_7cec40b193bcde18 ON reservation_transport
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX fk_reservation_transport_cinTransporteur ON reservation_transport (cinTransporteur)
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX idx_7cec40b1f6c8ab85 ON reservation_transport
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX fk_reservation_transport_cinEtudiant ON reservation_transport (cinEtudiant)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE reservation_transport ADD CONSTRAINT FK_7CEC40B1F6C8AB85 FOREIGN KEY (cinEtudiant) REFERENCES utilisateur (cin)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE reservation_transport ADD CONSTRAINT FK_7CEC40B193BCDE18 FOREIGN KEY (cinTransporteur) REFERENCES utilisateur (cin)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE transport DROP FOREIGN KEY FK_66AB212E377F287F
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE transport DROP FOREIGN KEY FK_66AB212EB83297E7
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE transport CHANGE timestamp timestamp DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE utilisateur CHANGE role role VARCHAR(255) NOT NULL, CHANGE reset_code_expires_at reset_code_expires_at DATETIME DEFAULT NULL, CHANGE blocked blocked TINYINT(1) DEFAULT 0, CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE isTwoFactorEnabled isTwoFactorEnabled TINYINT(1) DEFAULT 0 NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX uniq_1d1c63b3e7927c74 ON utilisateur
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX email ON utilisateur (email)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE voiture DROP FOREIGN KEY FK_E9E2810FABE530DA
        SQL);
    }
}
