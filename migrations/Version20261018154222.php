<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261018154222 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE realisation_recettes (recette_realisation_id INT NOT NULL, recette_id INT NOT NULL, INDEX IDX_B3471DD67DB58722 (recette_realisation_id), INDEX IDX_B3471DD689312FE9 (recette_id), PRIMARY KEY (recette_realisation_id, recette_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE realisation_recettes ADD CONSTRAINT FK_B3471DD67DB58722 FOREIGN KEY (recette_realisation_id) REFERENCES recette_realisations (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE realisation_recettes ADD CONSTRAINT FK_B3471DD689312FE9 FOREIGN KEY (recette_id) REFERENCES recettes (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE realisation_recettes DROP FOREIGN KEY FK_B3471DD67DB58722');
        $this->addSql('ALTER TABLE realisation_recettes DROP FOREIGN KEY FK_B3471DD689312FE9');
        $this->addSql('DROP TABLE realisation_recettes');
    }
}
