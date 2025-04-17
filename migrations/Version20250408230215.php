<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250408230215 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE logement CHANGE nbrChambre nbrChambre INT NOT NULL, CHANGE description description VARCHAR(255) NOT NULL, CHANGE type type VARCHAR(255) NOT NULL, CHANGE statut statut VARCHAR(255) NOT NULL, CHANGE utilisateur_cin utilisateur_cin VARCHAR(255) NOT NULL, CHANGE localisation localisation POINT NOT NULL COMMENT \'(DC2Type:point)\'');
        $this->addSql('ALTER TABLE options CHANGE id_option id_option INT AUTO_INCREMENT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE logement CHANGE nbrChambre nbrChambre VARCHAR(255) NOT NULL, CHANGE description description LONGTEXT DEFAULT NULL, CHANGE type type VARCHAR(100) DEFAULT NULL, CHANGE localisation localisation POINT DEFAULT NULL COMMENT \'(DC2Type:point)\', CHANGE statut statut VARCHAR(255) DEFAULT \'DISPONIBLE\' NOT NULL, CHANGE utilisateur_cin utilisateur_cin VARCHAR(8) NOT NULL');
        $this->addSql('ALTER TABLE options CHANGE id_option id_option INT NOT NULL');
    }
}
