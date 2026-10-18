<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261013093411 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE ingredients ADD photos JSON NOT NULL');
        $this->addSql('ALTER TABLE recette_realisations ADD photos JSON NOT NULL');
        $this->addSql('ALTER TABLE recettes ADD photos JSON NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE ingredients DROP photos');
        $this->addSql('ALTER TABLE recette_realisations DROP photos');
        $this->addSql('ALTER TABLE recettes DROP photos');
    }
}
