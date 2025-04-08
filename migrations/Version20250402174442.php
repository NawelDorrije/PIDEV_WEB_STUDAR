<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250402174442 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE image_logement DROP FOREIGN KEY FK_7F0DCAFF58ABF955');
        $this->addSql('DROP INDEX IDX_7F0DCAFF58ABF955 ON image_logement');
        $this->addSql('ALTER TABLE image_logement CHANGE logement_id logement INT NOT NULL');
        $this->addSql('ALTER TABLE image_logement ADD CONSTRAINT FK_7F0DCAFFF0FD4457 FOREIGN KEY (logement) REFERENCES logement (id)');
        $this->addSql('CREATE INDEX IDX_7F0DCAFFF0FD4457 ON image_logement (logement)');
        $this->addSql('ALTER TABLE logement DROP utilisateur_cin, CHANGE nbrChambre nbrChambre INT NOT NULL, CHANGE description description VARCHAR(255) NOT NULL, CHANGE type type VARCHAR(255) NOT NULL, CHANGE statut statut ENUM(\'DISPONIBLE\', \'NON_DISPONIBLE\') NOT NULL COMMENT \'(DC2Type:statut)\', CHANGE localisation localisation POINT NOT NULL COMMENT \'(DC2Type:point)\'');
        $this->addSql('ALTER TABLE logement_options DROP FOREIGN KEY FK_AAE2EAD57CB1B55D');
        $this->addSql('ALTER TABLE logement_options ADD CONSTRAINT FK_AAE2EAD57CB1B55D FOREIGN KEY (id_option) REFERENCES options (id)');
        $this->addSql('ALTER TABLE options CHANGE id_option id_option INT AUTO_INCREMENT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE image_logement DROP FOREIGN KEY FK_7F0DCAFFF0FD4457');
        $this->addSql('DROP INDEX IDX_7F0DCAFFF0FD4457 ON image_logement');
        $this->addSql('ALTER TABLE image_logement CHANGE logement logement_id INT NOT NULL');
        $this->addSql('ALTER TABLE image_logement ADD CONSTRAINT FK_7F0DCAFF58ABF955 FOREIGN KEY (logement_id) REFERENCES logement (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('CREATE INDEX IDX_7F0DCAFF58ABF955 ON image_logement (logement_id)');
        $this->addSql('ALTER TABLE logement ADD utilisateur_cin VARCHAR(8) NOT NULL, CHANGE nbrChambre nbrChambre VARCHAR(255) NOT NULL, CHANGE description description LONGTEXT DEFAULT NULL, CHANGE type type VARCHAR(100) DEFAULT NULL, CHANGE localisation localisation POINT DEFAULT NULL COMMENT \'(DC2Type:point)\', CHANGE statut statut VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE logement_options DROP FOREIGN KEY FK_AAE2EAD57CB1B55D');
        $this->addSql('ALTER TABLE logement_options ADD CONSTRAINT FK_AAE2EAD57CB1B55D FOREIGN KEY (id_option) REFERENCES options (id_option) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE options CHANGE id_option id_option INT NOT NULL');
    }
}
