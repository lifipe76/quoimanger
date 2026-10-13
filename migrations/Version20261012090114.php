<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261012090114 extends AbstractMigration
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
        $this->addSql('UPDATE ingredients SET photos = CASE WHEN photo IS NOT NULL AND photo != "" THEN JSON_ARRAY(photo) ELSE JSON_ARRAY() END');
        $this->addSql('UPDATE recette_realisations SET photos = CASE WHEN photo IS NOT NULL AND photo != "" THEN JSON_ARRAY(photo) ELSE JSON_ARRAY() END');
        $this->addSql('UPDATE recettes SET photos = CASE WHEN photo IS NOT NULL AND photo != "" THEN JSON_ARRAY(photo) ELSE JSON_ARRAY() END');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE ingredients DROP photos');
        $this->addSql('ALTER TABLE recette_realisations DROP photos');
        $this->addSql('ALTER TABLE recettes DROP photos');
    }
}
