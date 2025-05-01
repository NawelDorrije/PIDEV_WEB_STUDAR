<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250501201759 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE reservation_transport CHANGE status status ENUM(\'confirmée\', \'en_attente\', \'refusée\') DEFAULT \'en_attente\'');
        $this->addSql('ALTER TABLE reservation_transport ADD CONSTRAINT FK_7CEC40B1F6C8AB85 FOREIGN KEY (cinEtudiant) REFERENCES utilisateur (cin)');
        $this->addSql('ALTER TABLE reservation_transport ADD CONSTRAINT FK_7CEC40B193BCDE18 FOREIGN KEY (cinTransporteur) REFERENCES utilisateur (cin)');
        $this->addSql('DROP INDEX fk_reservation_transport_cinetudiant ON reservation_transport');
        $this->addSql('CREATE INDEX IDX_7CEC40B1F6C8AB85 ON reservation_transport (cinEtudiant)');
        $this->addSql('DROP INDEX fk_reservation_transport_cintransporteur ON reservation_transport');
        $this->addSql('CREATE INDEX IDX_7CEC40B193BCDE18 ON reservation_transport (cinTransporteur)');
        $this->addSql('DROP INDEX idx_transport_stripe_invoice ON transport');
        $this->addSql('ALTER TABLE transport ADD stripeInvoiceId VARCHAR(255) DEFAULT NULL, DROP stripe_invoice_id, CHANGE status status VARCHAR(20) NOT NULL COMMENT \'(DC2Type:transport_status)\', CHANGE timestamp timestamp DATETIME NOT NULL');
        $this->addSql('ALTER TABLE transport ADD CONSTRAINT FK_66AB212E377F287F FOREIGN KEY (id_voiture) REFERENCES voiture (id_voiture)');
        $this->addSql('ALTER TABLE transport ADD CONSTRAINT FK_66AB212EB83297E7 FOREIGN KEY (reservation_id) REFERENCES reservation_transport (id)');
        $this->addSql('DROP INDEX email ON utilisateur');
        $this->addSql('ALTER TABLE utilisateur CHANGE role role VARCHAR(20) NOT NULL, CHANGE blocked blocked TINYINT(1) NOT NULL, CHANGE created_at created_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE image image VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE voiture ADD CONSTRAINT FK_E9E2810FABE530DA FOREIGN KEY (cin) REFERENCES utilisateur (cin)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE reservation_transport DROP FOREIGN KEY FK_7CEC40B1F6C8AB85');
        $this->addSql('ALTER TABLE reservation_transport DROP FOREIGN KEY FK_7CEC40B193BCDE18');
        $this->addSql('ALTER TABLE reservation_transport DROP FOREIGN KEY FK_7CEC40B1F6C8AB85');
        $this->addSql('ALTER TABLE reservation_transport DROP FOREIGN KEY FK_7CEC40B193BCDE18');
        $this->addSql('ALTER TABLE reservation_transport CHANGE status status VARCHAR(255) DEFAULT \'en_attente\'');
        $this->addSql('DROP INDEX idx_7cec40b193bcde18 ON reservation_transport');
        $this->addSql('CREATE INDEX fk_reservation_transport_cinTransporteur ON reservation_transport (cinTransporteur)');
        $this->addSql('DROP INDEX idx_7cec40b1f6c8ab85 ON reservation_transport');
        $this->addSql('CREATE INDEX fk_reservation_transport_cinEtudiant ON reservation_transport (cinEtudiant)');
        $this->addSql('ALTER TABLE reservation_transport ADD CONSTRAINT FK_7CEC40B1F6C8AB85 FOREIGN KEY (cinEtudiant) REFERENCES utilisateur (cin)');
        $this->addSql('ALTER TABLE reservation_transport ADD CONSTRAINT FK_7CEC40B193BCDE18 FOREIGN KEY (cinTransporteur) REFERENCES utilisateur (cin)');
        $this->addSql('ALTER TABLE transport DROP FOREIGN KEY FK_66AB212E377F287F');
        $this->addSql('ALTER TABLE transport DROP FOREIGN KEY FK_66AB212EB83297E7');
        $this->addSql('ALTER TABLE transport ADD stripe_invoice_id VARCHAR(255) DEFAULT NULL COMMENT \'Stripe Invoice Reference\', DROP stripeInvoiceId, CHANGE status status VARCHAR(255) DEFAULT \'En attente\' NOT NULL, CHANGE timestamp timestamp DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('CREATE INDEX idx_transport_stripe_invoice ON transport (stripe_invoice_id)');
        $this->addSql('ALTER TABLE utilisateur CHANGE image image VARCHAR(255) DEFAULT NULL, CHANGE role role VARCHAR(255) NOT NULL, CHANGE blocked blocked TINYINT(1) DEFAULT 0, CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX email ON utilisateur (email)');
        $this->addSql('ALTER TABLE voiture DROP FOREIGN KEY FK_E9E2810FABE530DA');
    }
}
