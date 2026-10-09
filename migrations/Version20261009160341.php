<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261009160341 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE recette_realisations ADD photo VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE recettes ADD photo VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE users CHANGE email_envoio email_envoi VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE recette_realisations DROP photo');
        $this->addSql('ALTER TABLE recettes DROP photo');
        $this->addSql('ALTER TABLE users CHANGE email_envoi email_envoio VARCHAR(255) DEFAULT NULL');
    }
}
