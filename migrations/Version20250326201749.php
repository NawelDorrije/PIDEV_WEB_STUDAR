<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250326201749 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE logement DROP utilisateur_cin, CHANGE nbrChambre nbrChambre INT NOT NULL, CHANGE description description VARCHAR(255) NOT NULL, CHANGE type type VARCHAR(255) NOT NULL, CHANGE statut statut ENUM(\'DISPONIBLE\', \'NON_DISPONIBLE\') NOT NULL COMMENT \'(DC2Type:statut)\', CHANGE localisation localisation POINT NOT NULL COMMENT \'(DC2Type:point)\'');
        $this->addSql('ALTER TABLE logement_options DROP FOREIGN KEY logement_options_ibfk_1');
        $this->addSql('ALTER TABLE logement_options DROP FOREIGN KEY logement_options_ibfk_2');
        $this->addSql('DROP INDEX IDX_AAE2EAD5A026A8C2 ON logement_options');
        $this->addSql('ALTER TABLE logement_options ADD id INT AUTO_INCREMENT NOT NULL, DROP valeur, CHANGE id_logement logement_id INT NOT NULL, DROP PRIMARY KEY, ADD PRIMARY KEY (id)');
        $this->addSql('ALTER TABLE logement_options ADD CONSTRAINT FK_AAE2EAD558ABF955 FOREIGN KEY (logement_id) REFERENCES logement (id)');
        $this->addSql('ALTER TABLE logement_options ADD CONSTRAINT FK_AAE2EAD57CB1B55D FOREIGN KEY (id_option) REFERENCES options (id)');
        $this->addSql('CREATE INDEX IDX_AAE2EAD558ABF955 ON logement_options (logement_id)');
        $this->addSql('ALTER TABLE logement_options RENAME INDEX logement_options_ibfk_2 TO IDX_AAE2EAD57CB1B55D');
        $this->addSql('ALTER TABLE options ADD id INT AUTO_INCREMENT NOT NULL, DROP id_option, DROP PRIMARY KEY, ADD PRIMARY KEY (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE logement ADD utilisateur_cin VARCHAR(8) NOT NULL, CHANGE nbrChambre nbrChambre VARCHAR(255) NOT NULL, CHANGE description description TEXT DEFAULT NULL, CHANGE type type VARCHAR(100) DEFAULT NULL, CHANGE localisation localisation POINT DEFAULT NULL COMMENT \'(DC2Type:point)\', CHANGE statut statut VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE logement_options MODIFY id INT NOT NULL');
        $this->addSql('ALTER TABLE logement_options DROP FOREIGN KEY FK_AAE2EAD558ABF955');
        $this->addSql('ALTER TABLE logement_options DROP FOREIGN KEY FK_AAE2EAD57CB1B55D');
        $this->addSql('DROP INDEX IDX_AAE2EAD558ABF955 ON logement_options');
        $this->addSql('DROP INDEX `PRIMARY` ON logement_options');
        $this->addSql('ALTER TABLE logement_options ADD valeur TINYINT(1) DEFAULT NULL, DROP id, CHANGE logement_id id_logement INT NOT NULL');
        $this->addSql('ALTER TABLE logement_options ADD CONSTRAINT logement_options_ibfk_1 FOREIGN KEY (id_logement) REFERENCES logement (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE logement_options ADD CONSTRAINT logement_options_ibfk_2 FOREIGN KEY (id_option) REFERENCES options (id_option) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_AAE2EAD5A026A8C2 ON logement_options (id_logement)');
        $this->addSql('ALTER TABLE logement_options ADD PRIMARY KEY (id_logement, id_option)');
        $this->addSql('ALTER TABLE logement_options RENAME INDEX idx_aae2ead57cb1b55d TO logement_options_ibfk_2');
        $this->addSql('ALTER TABLE options MODIFY id INT NOT NULL');
        $this->addSql('DROP INDEX `PRIMARY` ON options');
        $this->addSql('ALTER TABLE options ADD id_option INT NOT NULL, DROP id');
        $this->addSql('ALTER TABLE options ADD PRIMARY KEY (id_option)');
    }
}
