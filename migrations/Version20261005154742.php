<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261005154742 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE conversations (id INT AUTO_INCREMENT NOT NULL, titre VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, famille_id INT DEFAULT NULL, INDEX IDX_C2521BF197A77B84 (famille_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE messages (id INT AUTO_INCREMENT NOT NULL, content LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, conversation_id INT NOT NULL, sender_id INT NOT NULL, proposition_id INT DEFAULT NULL, INDEX IDX_DB021E969AC0396 (conversation_id), INDEX IDX_DB021E96F624B39D (sender_id), INDEX IDX_DB021E96DB96F9E (proposition_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE repas_propositions (id INT AUTO_INCREMENT NOT NULL, date_repas DATE NOT NULL, moment VARCHAR(20) NOT NULL, status VARCHAR(30) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, famille_id INT NOT NULL, propose_par_id INT NOT NULL, recette_id INT NOT NULL, realisation_id INT DEFAULT NULL, INDEX IDX_22C5162597A77B84 (famille_id), INDEX IDX_22C51625168A12A2 (propose_par_id), INDEX IDX_22C5162589312FE9 (recette_id), INDEX IDX_22C51625B685E551 (realisation_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE repas_votes (id INT AUTO_INCREMENT NOT NULL, choix VARCHAR(20) NOT NULL, voted_at DATETIME NOT NULL, proposition_id INT NOT NULL, user_id INT NOT NULL, UNIQUE INDEX unique_user_proposition_vote (proposition_id, user_id), INDEX IDX_A1140B5CDB96F9E (proposition_id), INDEX IDX_A1140B5CA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE conversations ADD CONSTRAINT FK_C2521BF197A77B84 FOREIGN KEY (famille_id) REFERENCES familles (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE messages ADD CONSTRAINT FK_DB021E969AC0396 FOREIGN KEY (conversation_id) REFERENCES conversations (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE messages ADD CONSTRAINT FK_DB021E96F624B39D FOREIGN KEY (sender_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE messages ADD CONSTRAINT FK_DB021E96DB96F9E FOREIGN KEY (proposition_id) REFERENCES repas_propositions (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE repas_propositions ADD CONSTRAINT FK_22C5162597A77B84 FOREIGN KEY (famille_id) REFERENCES familles (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE repas_propositions ADD CONSTRAINT FK_22C51625168A12A2 FOREIGN KEY (propose_par_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE repas_propositions ADD CONSTRAINT FK_22C5162589312FE9 FOREIGN KEY (recette_id) REFERENCES recettes (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE repas_propositions ADD CONSTRAINT FK_22C51625B685E551 FOREIGN KEY (realisation_id) REFERENCES recette_realisations (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE repas_votes ADD CONSTRAINT FK_A1140B5CDB96F9E FOREIGN KEY (proposition_id) REFERENCES repas_propositions (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE repas_votes ADD CONSTRAINT FK_A1140B5CA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE familles ADD createur_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE familles ADD CONSTRAINT FK_9F94FD2773A201E5 FOREIGN KEY (createur_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_9F94FD2773A201E5 ON familles (createur_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE conversations DROP FOREIGN KEY FK_C2521BF197A77B84');
        $this->addSql('ALTER TABLE messages DROP FOREIGN KEY FK_DB021E969AC0396');
        $this->addSql('ALTER TABLE messages DROP FOREIGN KEY FK_DB021E96F624B39D');
        $this->addSql('ALTER TABLE messages DROP FOREIGN KEY FK_DB021E96DB96F9E');
        $this->addSql('ALTER TABLE repas_propositions DROP FOREIGN KEY FK_22C5162597A77B84');
        $this->addSql('ALTER TABLE repas_propositions DROP FOREIGN KEY FK_22C51625168A12A2');
        $this->addSql('ALTER TABLE repas_propositions DROP FOREIGN KEY FK_22C5162589312FE9');
        $this->addSql('ALTER TABLE repas_propositions DROP FOREIGN KEY FK_22C51625B685E551');
        $this->addSql('ALTER TABLE repas_votes DROP FOREIGN KEY FK_A1140B5CDB96F9E');
        $this->addSql('ALTER TABLE repas_votes DROP FOREIGN KEY FK_A1140B5CA76ED395');
        $this->addSql('DROP TABLE conversations');
        $this->addSql('DROP TABLE messages');
        $this->addSql('DROP TABLE repas_propositions');
        $this->addSql('DROP TABLE repas_votes');
        $this->addSql('ALTER TABLE familles DROP FOREIGN KEY FK_9F94FD2773A201E5');
        $this->addSql('DROP INDEX IDX_9F94FD2773A201E5 ON familles');
        $this->addSql('ALTER TABLE familles DROP createur_id');
    }
}
