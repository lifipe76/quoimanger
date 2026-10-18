<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261018153309 extends AbstractMigration
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
        $this->addSql('ALTER TABLE frigos DROP FOREIGN KEY `FK_CCA2CC8989312FE9`');
        $this->addSql('ALTER TABLE frigos DROP FOREIGN KEY `FK_CCA2CC8997A77B84`');
        $this->addSql('ALTER TABLE frigos DROP FOREIGN KEY `FK_CCA2CC89A76ED395`');
        $this->addSql('ALTER TABLE repas_boissons DROP FOREIGN KEY `FK_BA29A5D5734B8089`');
        $this->addSql('ALTER TABLE repas_boissons DROP FOREIGN KEY `FK_BA29A5D5B685E551`');
        $this->addSql('DROP TABLE boissons');
        $this->addSql('DROP TABLE frigos');
        $this->addSql('DROP TABLE repas_boissons');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE boissons (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, description LONGTEXT CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, lieu_achat VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, photo VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`, photos JSON NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE frigos (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, recette_id INT NOT NULL, user_id INT NOT NULL, famille_id INT DEFAULT NULL, INDEX IDX_CCA2CC89A76ED395 (user_id), INDEX IDX_CCA2CC8997A77B84 (famille_id), INDEX IDX_CCA2CC8989312FE9 (recette_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE repas_boissons (id INT AUTO_INCREMENT NOT NULL, date_achat DATE DEFAULT NULL, note SMALLINT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, realisation_id INT NOT NULL, boisson_id INT NOT NULL, INDEX IDX_BA29A5D5734B8089 (boisson_id), INDEX IDX_BA29A5D5B685E551 (realisation_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE frigos ADD CONSTRAINT `FK_CCA2CC8989312FE9` FOREIGN KEY (recette_id) REFERENCES recettes (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE frigos ADD CONSTRAINT `FK_CCA2CC8997A77B84` FOREIGN KEY (famille_id) REFERENCES familles (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE frigos ADD CONSTRAINT `FK_CCA2CC89A76ED395` FOREIGN KEY (user_id) REFERENCES users (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE repas_boissons ADD CONSTRAINT `FK_BA29A5D5734B8089` FOREIGN KEY (boisson_id) REFERENCES boissons (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE repas_boissons ADD CONSTRAINT `FK_BA29A5D5B685E551` FOREIGN KEY (realisation_id) REFERENCES recette_realisations (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE realisation_recettes DROP FOREIGN KEY FK_B3471DD67DB58722');
        $this->addSql('ALTER TABLE realisation_recettes DROP FOREIGN KEY FK_B3471DD689312FE9');
        $this->addSql('DROP TABLE realisation_recettes');
    }
}
